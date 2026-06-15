<?php
require_once 'includes/app.php';
require_once 'markdown-parser.php';
require_once 'CommentManager.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$config = app_config();

$documentId = app_current_document_id($config);
$documentConfig = $config['content']['documents'][$documentId] ?? [];
$docTheme = $documentConfig['theme'] ?? [];

$pages = [];
$pageError = null;

try {
    $parsedDocument = app_parse_document_cached(app_document_path($config, $documentId), $documentId, $config);
    foreach ($parsedDocument['pages'] as $page) {
        $pages[$page['slug']] = $page;
    }
} catch (RuntimeException $e) {
    $pageError = $e->getMessage();
    $parsedDocument = ['title' => 'Document Error', 'pages' => []];
}

// Current page logic
$currentPageSlug = isset($_GET['section']) ? (string) $_GET['section'] : ($config['content']['default_section'] ?? null);
if (!$currentPageSlug || !isset($pages[$currentPageSlug])) {
    $currentPageSlug = array_key_first($pages);
}

$pageData = $pages[$currentPageSlug] ?? null;
$htmlContent = $pageData['html'] ?? '';

$cm = new CommentManager($config['storage']['database_path']);
$flatComments = $pageError ? [] : $cm->getComments($documentId, $currentPageSlug ?? '');

$commentTree = [];
$replies = [];

foreach ($flatComments as $c) {
    if (empty($c['parent_id'])) {
        $commentTree[$c['id']] = $c;
        $commentTree[$c['id']]['replies'] = [];
    } else {
        $replies[] = $c;
    }
}

$replies = array_reverse($replies);
foreach ($replies as $reply) {
    if (isset($commentTree[$reply['parent_id']])) {
        $commentTree[$reply['parent_id']]['replies'][] = $reply;
    }
}
$comments = array_values($commentTree);

$hasQuestions = false;
$format = $config['content']['documents'][$documentId]['format'] ?? 'section_feedback';

if ($format === 'questionnaire') {
    $questionComments = [];
    foreach ($comments as $c) {
        if (!empty($c['question_id'])) {
            $questionComments[$c['question_id']][] = $c;
        }
    }

    $htmlContent = preg_replace_callback('/<!-- QUESTION_ANSWERS: ([a-z0-9]+) -->/', function ($matches) use ($questionComments) {
        $qId = $matches[1];
        $answers = $questionComments[$qId] ?? [];
        if (empty($answers)) {
            return '<div class="question-answers-container mt-2" id="answers-list-' . $qId . '"></div>';
        }
        $html = '<div class="question-answers-container mt-2" id="answers-list-' . $qId . '">';
        foreach ($answers as $ans) {
            $name = htmlspecialchars($ans['name'], ENT_QUOTES, 'UTF-8');
            $date = htmlspecialchars($ans['created_at'], ENT_QUOTES, 'UTF-8');
            $text = htmlspecialchars($ans['comment'], ENT_QUOTES, 'UTF-8');
            $html .= '<div class="comment-item p-2 mb-2 bg-light rounded border border-light">';
            $html .= '<div class="d-flex justify-content-between"><h6 class="mb-1 small fw-bold">' . $name . '</h6><small class="text-muted" style="font-size: 0.7rem;">' . $date . '</small></div>';
            $html .= '<p class="mb-0 small" style="white-space: pre-line;">' . $text . '</p>';
            if (!empty($ans['replies'])) {
                $html .= '<div class="ms-3 mt-2 border-start ps-2 border-2 border-secondary-subtle">';
                foreach ($ans['replies'] as $reply) {
                    $rname = htmlspecialchars($reply['name'], ENT_QUOTES, 'UTF-8');
                    $rtext = htmlspecialchars($reply['comment'], ENT_QUOTES, 'UTF-8');
                    $html .= '<div class="mb-1"><strong class="small">' . $rname . ':</strong> <span class="small">' . $rtext . '</span></div>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }, $htmlContent);

    $htmlContent = preg_replace_callback('/<!-- QUESTION_FORM: ([a-z0-9]+) \| (.*?) -->/', function ($matches) use (&$hasQuestions, $currentPageSlug, $documentId) {
        $hasQuestions = true;
        $qId = $matches[1];
        $qTextEncoded = $matches[2];
        $qText = htmlspecialchars(base64_decode($qTextEncoded), ENT_QUOTES, 'UTF-8');
        $slug = htmlspecialchars($currentPageSlug, ENT_QUOTES, 'UTF-8');
        
        $userName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') : '';
        $userEmail = isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email'], ENT_QUOTES, 'UTF-8') : '';

        $formHtml = '<div class="question-form-container mt-2 p-3 bg-white rounded border shadow-sm">';
        $formHtml .= '<form class="inline-questionnaire-form">';
        $formHtml .= '<input type="hidden" name="question_id" value="' . $qId . '">';
        $formHtml .= '<input type="hidden" name="question" value="' . $qText . '">';
        $formHtml .= '<input type="hidden" name="section_id" value="' . $slug . '">';
        $formHtml .= '<input type="hidden" name="document_id" value="' . htmlspecialchars($documentId, ENT_QUOTES, 'UTF-8') . '">';
        
        if ($userName) {
            $formHtml .= '<input type="hidden" name="name" value="' . $userName . '">';
            $formHtml .= '<input type="hidden" name="email" value="' . $userEmail . '">';
        } else {
            $formHtml .= '<div class="row g-2 mb-2"><div class="col-md-6"><input type="text" class="form-control form-control-sm" name="name" required placeholder="Your Name"></div><div class="col-md-6"><input type="email" class="form-control form-control-sm" name="email" placeholder="Email (optional)"></div></div>';
        }
        
        $formHtml .= '<div class="mb-2"><textarea name="comment" class="form-control form-control-sm" rows="2" placeholder="Your answer..." required></textarea></div>';
        $formHtml .= '<div class="d-flex justify-content-between align-items-center">';
        $formHtml .= '<div class="formMessage small fw-bold"></div>';
        $formHtml .= '<button type="submit" class="btn btn-sm btn-primary">Submit Answer</button>';
        $formHtml .= '</div>';
        $formHtml .= '</form></div>';
        return $formHtml;
    }, $htmlContent);
}

$commentCounts = $pageError ? [] : $cm->getCommentCounts($documentId);
$previousNext = $pageError ? [] : app_previous_next_pages($pages, $currentPageSlug);

// Pass variables to layout
$pageTitle = $pageData['title'] ?? 'Error';
$navigation = $pages;
$documentTitle = $parsedDocument['title'] ?? $documentConfig['title'] ?? 'Document Error';
$availableDocuments = $config['content']['documents'] ?? [];

require 'layout.php';
