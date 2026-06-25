<?php
require_once 'includes/app.php';
require_once 'markdown-parser.php';
require_once 'CommentManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = app_config();

    if (empty($config['comments']['enabled'])) {
        echo json_encode(['success' => false, 'message' => 'Comments are currently disabled.']);
        exit;
    }

    $sectionId = $_POST['section_id'] ?? '';
    $documentId = $_POST['document_id'] ?? app_current_document_id($config);
    $parentId = $_POST['parent_id'] ?? null;
    
    session_start();
    if (isset($_SESSION['user_name'])) {
        $name = $_SESSION['user_name'];
        $email = $_SESSION['user_email'] ?? '';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
    }
    
    $feedbackType = trim($_POST['feedback_type'] ?? '');
    $honeypot = trim($_POST['website'] ?? '');

    if ($honeypot !== '') {
        echo json_encode(['success' => false, 'message' => 'Submission rejected.']);
        exit;
    }

    try {
        $parsedDocument = app_parse_document_cached(app_document_path($config, $documentId), $documentId, $config);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Unable to load document sections.']);
        exit;
    }

    $sectionTitles = [];

    foreach ($parsedDocument['pages'] as $page) {
        $sectionTitles[$page['slug']] = $page['title'];
    }

    if (!isset($sectionTitles[$sectionId])) {
        echo json_encode(['success' => false, 'message' => 'Invalid document section.']);
        exit;
    }
    
    $standardFields = ['document_id', 'section_id', 'parent_id', 'name', 'email', 'feedback_type', 'comment', 'website', 'g-recaptcha-response', 'is_questionnaire', 'question_id', 'question', 'attachment_path', 'attachment_original_name', 'attachment_paths', 'attachment_original_names'];
    $extraData = array_diff_key($_POST, array_flip($standardFields));
    $questionId = trim($_POST['question_id'] ?? '');
    
    $commentText = trim($_POST['comment'] ?? '');
    $customComment = '';

    if (!empty($extraData)) {
        $customComment = "--- ADDITIONAL SUBMITTED DATA ---\n\n";
        foreach ($extraData as $key => $value) {
            $keyName = ucwords(str_replace('_', ' ', $key));
            if (is_array($value)) {
                $customComment .= "[ $keyName ]\n";
                foreach ($value as $index => $item) {
                    if (is_array($item)) {
                        $title = $item['name'] ?? $item['question'] ?? $item['title'] ?? null;
                        
                        $hasValues = false;
                        foreach ($item as $subKey => $subValue) {
                            if (!in_array($subKey, ['name', 'question', 'title'], true) && trim((string)$subValue) !== '') {
                                $hasValues = true;
                                break;
                            }
                        }

                        if ($hasValues) {
                            if ($title) {
                                $customComment .= "- $title\n";
                            } else {
                                $customComment .= "- Item " . ($index + 1) . "\n";
                            }
                            foreach ($item as $subKey => $subValue) {
                                if (in_array($subKey, ['name', 'question', 'title'], true)) continue;
                                if (trim((string)$subValue) !== '') {
                                    $customComment .= "  * " . ucwords(str_replace('_', ' ', $subKey)) . ": " . trim((string)$subValue) . "\n";
                                }
                            }
                        }
                    } else {
                        if (trim((string)$item) !== '') {
                            $customComment .= "- " . trim((string)$item) . "\n";
                        }
                    }
                }
                $customComment .= "\n";
            } else {
                if (trim((string)$value) !== '') {
                    $customComment .= "$keyName: " . trim((string)$value) . "\n\n";
                }
            }
        }
    }

    $attachmentPaths = $_POST['attachment_paths'] ?? [];
    $attachmentNames = $_POST['attachment_original_names'] ?? [];

    $comment = trim($customComment . "\n" . $commentText);

    if (!empty($attachmentPaths) && is_array($attachmentPaths)) {
        foreach ($attachmentPaths as $index => $path) {
            $path = trim((string) $path);
            // Only accept paths to files actually produced by api_upload.php (storage/uploads/<hash>.<ext>).
            // This blocks arbitrary URLs / javascript: payloads from being injected as links.
            if (preg_match('#^storage/uploads/[A-Za-z0-9._-]+$#', $path)) {
                $attachmentName = trim((string) ($attachmentNames[$index] ?? 'Attachment'));
                $comment .= "\n\n[Attached File: " . $attachmentName . "](" . $path . ")";
            }
        }
    }

    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

    if (empty($sectionId) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
        exit;
    }

    if ($comment === '' && $feedbackType === '') {
        echo json_encode(['success' => false, 'message' => 'Please provide a comment or select a feedback type.']);
        exit;
    }

    if (!empty($config['comments']['collect_email']) && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    $allowedTypes = $config['comments']['feedback_types'] ?? [];

    if ($feedbackType !== '' && !in_array($feedbackType, $allowedTypes, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid feedback type.']);
        exit;
    }

    $isRecaptchaValid = true;

    // Inline questionnaire answer forms are posted per-question without a reCAPTCHA widget
    // and are only reachable by logged-in reviewers, so skip enforcement for them.
    if (!empty($config['recaptcha']['enabled']) && $questionId === '') {
        $isRecaptchaValid = false;
        $secret = $config['recaptcha']['secret_key'] ?? '';

        if ($secret !== '' && $recaptchaResponse !== '') {
            $verifyBody = http_build_query([
                'secret' => $secret,
                'response' => $recaptchaResponse,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => $verifyBody,
                    'timeout' => 8,
                ],
            ]);
            $verifyResponse = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
            $verifyJson = $verifyResponse ? json_decode($verifyResponse, true) : null;
            $isRecaptchaValid = is_array($verifyJson) && !empty($verifyJson['success']);
        }
    }

    if (!$isRecaptchaValid) {
        echo json_encode(['success' => false, 'message' => 'reCAPTCHA verification failed.']);
        exit;
    }

    try {
        $cm = new CommentManager($config['storage']['database_path']);
        $status = !empty($config['comments']['require_approval']) ? 'pending' : 'approved';
        $cm->addComment([
            'parent_id' => $parentId,
            'document_id' => $documentId,
            'section_id' => $sectionId,
            'page_title' => $sectionTitles[$sectionId],
            'name' => $name,
            'email' => $email,
            'feedback_type' => $feedbackType,
            'comment' => $comment,
            'status' => $status,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'question_id' => $questionId,
        ]);

        $message = $status === 'pending'
            ? 'Comment submitted successfully and is waiting for approval.'
            : 'Comment added successfully!';

        echo json_encode(['success' => true, 'message' => $message]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
