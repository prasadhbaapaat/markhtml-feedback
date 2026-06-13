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

$commentCounts = $pageError ? [] : $cm->getCommentCounts($documentId);
$previousNext = $pageError ? [] : app_previous_next_pages($pages, $currentPageSlug);

// Pass variables to layout
$pageTitle = $pageData['title'] ?? 'Error';
$navigation = $pages;
$documentTitle = $parsedDocument['title'] ?? $documentConfig['title'] ?? 'Document Error';
$availableDocuments = $config['content']['documents'] ?? [];

require 'layout.php';
