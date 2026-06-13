<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);

return [
    'site_name' => 'MarkHTML Feedback',
    'title_suffix' => 'Review',
    'brand_name' => 'MarkHTML Feedback',
    'logo_path' => '', // e.g. 'assets/images/logo.png'
    'logo_alt' => 'Logo',
    'footer_text' => '2026 - MarkHTML Open Source',

    'content' => [
        'default_document' => 'sample-doc',
        'documents' => [
            'sample-doc' => [
                'title' => 'Sample Documentation',
                'path' => $rootPath . '/content/sample.md',
                'theme' => [
                    // You can override global theme variables per document
                    // 'primary' => '#005500',
                ]
            ],
        ],
        'split_level' => 2,
        'default_section' => null,
        'show_page_numbers' => true,
        'show_previous_next' => true,
    ],

    'storage' => [
        'database_path' => $rootPath . '/storage/database.sqlite',
        'cache_dir' => $rootPath . '/storage/cache',
    ],

    // Users are managed via the SQLite Database now.

    'comments' => [
        'enabled' => true,
        'require_approval' => false,
        'collect_email' => false,
        'collect_feedback_type' => true,
        'feedback_types' => ['Looks good', 'Needs changes', 'Needs discussion'],
    ],

    'recaptcha' => [
        'enabled' => false,
        'site_key' => 'YOUR_RECAPTCHA_SITE_KEY',
        'secret_key' => 'YOUR_RECAPTCHA_SECRET_KEY',
    ],

    'special_forms' => [
        // Map the slug of the section to the custom PHP form
        'project-feedback-survey' => 'questionnaire_form.php',
    ],

    'theme' => [
        'primary' => '#020617', // Very dark slate (Navbar)
        'accent' => '#0EA5E9',  // Sky blue (Buttons/Links)
        'accent_soft' => '#e0f2fe',
        'page_bg' => '#f8f9fa',
        'panel_bg' => '#f1f3f5',
        'text' => '#1e293b',    // Slate 800
        'heading' => '#0B1120', // Dark slate (Headings)
        'sidebar_bg' => '#ffffff',
        'sidebar_hover' => '#f1f5f9',
        'code_bg' => '#f1f5f9',
        'code_text' => '#0EA5E9',
        'pre_bg' => '#0B1120',
        'pre_text' => '#f8f8f2',
    ],
];
