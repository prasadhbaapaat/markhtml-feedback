<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Returns the current session's CSRF token, generating one if needed.
 * Requires an active session (callers start the session before use).
 */
function app_csrf_token(): string
{
    if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Validates a submitted CSRF token against the one stored in the session.
 */
function app_csrf_verify(?string $token): bool
{
    return is_string($token) && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function app_current_document_id(array $config): string
{
    $default = $config['content']['default_document'] ?? '';
    $docId = isset($_GET['doc']) ? (string) $_GET['doc'] : $default;

    if (!isset($config['content']['documents'][$docId])) {
        return $default;
    }

    return $docId;
}

function app_document_path(array $config, string $docId): string
{
    if (!isset($config['content']['documents'][$docId])) {
        throw new RuntimeException("Document not configured: " . h($docId));
    }

    $documentPath = $config['content']['documents'][$docId]['path'];

    if (is_file($documentPath)) {
        return $documentPath;
    }

    throw new RuntimeException("Markdown file not found for document '{$docId}'. Check content.documents in includes/config.php.");
}

function app_parse_document_cached(string $documentPath, string $docId, array $config): array
{
    $cacheDir = $config['storage']['cache_dir'];

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }

    $cacheKey = md5(implode('|', [
        $docId,
        realpath($documentPath) ?: $documentPath,
        (string) filemtime($documentPath),
        (string) filemtime(dirname(__DIR__) . '/markdown-parser.php'),
        (string) filemtime(__DIR__ . '/config.php'),
        (string) ($config['content']['split_level'] ?? 2),
    ]));
    $cachePath = rtrim($cacheDir, '/\\') . '/document-' . $cacheKey . '.json';

    if (is_file($cachePath)) {
        $cached = json_decode((string) file_get_contents($cachePath), true);

        if (is_array($cached)) {
            return $cached;
        }
    }

    $format = $config['content']['documents'][$docId]['format'] ?? 'section_feedback';

    $parsed = MarkHtmlMarkdownParser::parseMarkdownFile($documentPath, [
        'splitLevel' => (int) ($config['content']['split_level'] ?? 2),
        'format' => $format,
    ]);

    file_put_contents($cachePath, json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $parsed;
}

function app_page_url(string $slug, string $docId = null): string
{
    $query = [];
    if ($docId) {
        $query['doc'] = $docId;
    }
    $query['section'] = $slug;

    return '?' . http_build_query($query);
}

function app_asset(string $path): string
{
    return ltrim($path, '/');
}

function app_theme_style(array $globalTheme, array $docTheme = []): string
{
    $theme = array_merge($globalTheme, $docTheme);
    $variables = [
        '--brand-primary' => $theme['primary'] ?? '#00102d',
        '--brand-accent' => $theme['accent'] ?? '#0d6efd',
        '--brand-accent-soft' => $theme['accent_soft'] ?? '#e7f1ff',
        '--page-bg' => $theme['page_bg'] ?? '#f8f9fa',
        '--panel-bg' => $theme['panel_bg'] ?? '#f1f3f5',
        '--text-main' => $theme['text'] ?? '#333333',
        '--heading-color' => $theme['heading'] ?? '#2c3e50',
        '--sidebar-bg' => $theme['sidebar_bg'] ?? '#ffffff',
        '--sidebar-hover' => $theme['sidebar_hover'] ?? '#e9ecef',
        '--code-bg' => $theme['code_bg'] ?? '#f4f4f4',
        '--code-text' => $theme['code_text'] ?? '#d63384',
        '--pre-bg' => $theme['pre_bg'] ?? '#2b2b2b',
        '--pre-text' => $theme['pre_text'] ?? '#f8f8f2',
    ];
    $css = ':root {';

    foreach ($variables as $name => $value) {
        $css .= $name . ': ' . h($value) . ';';
    }

    return $css . '}';
}

function app_previous_next_pages(array $pages, string $currentSlug): array
{
    $slugs = array_keys($pages);
    $currentIndex = array_search($currentSlug, $slugs, true);

    if ($currentIndex === false) {
        return ['previous' => null, 'next' => null];
    }

    $previousSlug = $slugs[$currentIndex - 1] ?? null;
    $nextSlug = $slugs[$currentIndex + 1] ?? null;

    return [
        'previous' => $previousSlug !== null ? $pages[$previousSlug] : null,
        'next' => $nextSlug !== null ? $pages[$nextSlug] : null,
    ];
}

/**
 * Parses markdown links [text](url) into HTML anchor tags.
 * Designed to be called on text that has already been HTML escaped.
 */
function app_parse_comment_links(string $text): string
{
    return preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', static function (array $m): string {
        $label = $m[1];
        $url = trim($m[2]);

        // Only allow http(s) absolute URLs or relative paths; block javascript:, data:, vbscript:, etc.
        $hasScheme = preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) === 1;
        $isSafe = $hasScheme
            ? (bool) preg_match('#^https?://#i', $url)
            : strpos($url, '//') !== 0; // reject protocol-relative //host

        if (!$isSafe) {
            return $m[0]; // leave as plain (already-escaped) text, no link
        }

        return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-medium"><i class="me-1">📎</i>' . $label . '</a>';
    }, $text);
}
