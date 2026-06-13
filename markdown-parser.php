<?php

declare(strict_types=1);

final class MarkHtmlMarkdownParser
{
    private const DEFAULT_OPTIONS = [
        'splitLevel' => 2,
        'includeIntroPage' => true,
        'commentForm' => [
            'method' => 'post',
            'action' => '',
        ],
    ];

    public static function parseMarkdownFile(string $filePath, array $options = []): array
    {
        if (!is_file($filePath)) {
            throw new InvalidArgumentException("Markdown file not found: {$filePath}");
        }

        $source = file_get_contents($filePath);

        if ($source === false) {
            throw new RuntimeException("Unable to read markdown file: {$filePath}");
        }

        $realPath = realpath($filePath);

        return self::parseMarkdownDocument($source, array_merge($options, [
            'sourcePath' => $realPath !== false ? $realPath : $filePath,
        ]));
    }

    public static function parseMarkdownDocument(string $source, array $options = []): array
    {
        // Strip feedback blocks to prevent double-rendering on the frontend
        $source = preg_replace('/<!-- FEEDBACK_START -->[\s\S]*?<!-- FEEDBACK_END -->/', '', $source);

        $config = self::mergeOptions(self::DEFAULT_OPTIONS, $options);
        $normalized = self::normalizeLineEndings($source);
        $lines = explode("\n", $normalized);
        $headings = self::collectHeadings($lines);
        $documentTitle = self::getDocumentTitle($headings, $config['title'] ?? null);
        $pages = self::extractPages($lines, $headings, $documentTitle, $config);

        return [
            'sourcePath' => $config['sourcePath'] ?? null,
            'title' => $documentTitle,
            'slug' => self::slugify($documentTitle ?: 'document'),
            'pageCount' => count($pages),
            'toc' => array_map(static function (array $page): array {
                return [
                    'id' => $page['id'],
                    'title' => $page['title'],
                    'slug' => $page['slug'],
                    'level' => $page['level'],
                    'order' => $page['order'],
                    'parentSlug' => $page['parentSlug'],
                    'excerpt' => $page['excerpt'],
                ];
            }, $pages),
            'pages' => $pages,
        ];
    }

    public static function markdownToHtml(string $markdown): string
    {
        $lines = explode("\n", self::normalizeLineEndings($markdown));
        $blocks = [];
        $index = 0;
        $lineCount = count($lines);

        while ($index < $lineCount) {
            $line = $lines[$index];

            if (self::isBlank($line)) {
                $index++;
                continue;
            }

            if (preg_match('/^```([A-Za-z0-9_-]+)?\s*$/', $line, $fenceMatch) === 1) {
                $language = $fenceMatch[1] ?? '';
                $codeLines = [];
                $index++;

                while ($index < $lineCount && preg_match('/^```\s*$/', $lines[$index]) !== 1) {
                    $codeLines[] = $lines[$index];
                    $index++;
                }

                if ($index < $lineCount) {
                    $index++;
                }

                $languageClass = $language !== '' ? ' class="language-' . self::escapeAttribute($language) . '"' : '';
                $blocks[] = '<pre><code' . $languageClass . '>' . self::escapeHtml(implode("\n", $codeLines)) . '</code></pre>';
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $headingMatch) === 1) {
                $level = strlen($headingMatch[1]);
                $text = self::cleanHeadingText($headingMatch[2]);
                $blocks[] = '<h' . $level . ' id="' . self::escapeAttribute(self::slugify($text)) . '">' . self::inlineMarkdown($text) . '</h' . $level . '>';
                $index++;
                continue;
            }

            if (preg_match('/^\s*[-*_]\s*([-*_]\s*){2,}$/', $line) === 1) {
                $blocks[] = '<hr>';
                $index++;
                continue;
            }

            if (self::isTableStart($lines, $index)) {
                $table = self::consumeTable($lines, $index);
                $blocks[] = self::renderTable($table['rows'], $table['alignments']);
                $index = $table['nextIndex'];
                continue;
            }

            if (preg_match('/^\s*>\s?/', $line) === 1) {
                $quoteLines = [];

                while ($index < $lineCount && preg_match('/^\s*>\s?/', $lines[$index]) === 1) {
                    $quoteLines[] = preg_replace('/^\s*>\s?/', '', $lines[$index]);
                    $index++;
                }

                $blocks[] = '<blockquote>' . self::markdownToHtml(implode("\n", $quoteLines)) . '</blockquote>';
                continue;
            }

            if (preg_match('/^\s*[-*+]\s+/', $line) === 1) {
                $list = self::consumeList($lines, $index, '/^\s*[-*+]\s+/');
                $blocks[] = self::renderList('ul', $list['items']);
                $index = $list['nextIndex'];
                continue;
            }

            if (preg_match('/^\s*\d+[.)]\s+/', $line) === 1) {
                $list = self::consumeList($lines, $index, '/^\s*\d+[.)]\s+/');
                $blocks[] = self::renderList('ol', $list['items']);
                $index = $list['nextIndex'];
                continue;
            }

            $paragraphLines = [];

            while (
                $index < $lineCount &&
                !self::isBlank($lines[$index]) &&
                !self::isBlockStart($lines, $index)
            ) {
                $paragraphLines[] = trim($lines[$index]);
                $index++;
            }

            $paragraphText = implode(' ', $paragraphLines);
            $classes = [];
            
            $isCentered = preg_match('/^::\s*(.+?)\s*::$/s', $paragraphText, $alignMatch);
            $isRight = !$isCentered && preg_match('/^(.+?)\s*::$/s', $paragraphText, $alignMatch);
            $isLeft = !$isCentered && !$isRight && preg_match('/^::\s*(.+?)$/s', $paragraphText, $alignMatch);
            
            if ($isCentered) {
                $classes[] = 'text-center';
                $paragraphText = trim($alignMatch[1]);
            } elseif ($isRight) {
                $classes[] = 'text-end';
                $paragraphText = trim($alignMatch[1]);
            } elseif ($isLeft) {
                $classes[] = 'text-start';
                $paragraphText = trim($alignMatch[1]);
            }
            
            $classAttr = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
            $blocks[] = '<p' . $classAttr . '>' . self::inlineMarkdown($paragraphText) . '</p>';
        }

        return implode("\n", $blocks);
    }

    public static function markdownToPlainText(string $markdown): string
    {
        $text = self::normalizeLineEndings($markdown);
        $text = preg_replace('/```[\s\S]*?```/', ' ', $text);
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);
        $text = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\{\.underline\}/', '$1', $text);
        $text = preg_replace('/[*_`>#|-]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text ?? '');
    }

    public static function slugify(string $value): string
    {
        $slug = strtolower($value);
        $slug = str_replace('&', ' and ', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug ?? '', '-');

        return $slug !== '' ? $slug : 'section';
    }

    private static function extractPages(array $lines, array $headings, string $documentTitle, array $config): array
    {
        $splitLevel = self::chooseSplitLevel($headings, (int) $config['splitLevel']);
        $splitHeadings = array_values(array_filter($headings, static function (array $heading) use ($splitLevel): bool {
            return $heading['level'] === $splitLevel;
        }));
        $usedSlugs = [];

        if (count($splitHeadings) === 0) {
            $markdown = implode("\n", self::trimOuterBlankLines($lines));

            return [
                self::buildPage([
                    'title' => $documentTitle ?: 'Document',
                    'level' => 1,
                    'order' => 1,
                    'markdown' => $markdown,
                    'usedSlugs' => &$usedSlugs,
                    'config' => $config,
                ]),
            ];
        }

        $pages = [];
        $firstSplitLine = $splitHeadings[0]['line'];
        $introLines = self::removeDocumentTitle(array_slice($lines, 0, $firstSplitLine), $headings);
        $introMarkdown = implode("\n", self::trimOuterBlankLines($introLines));

        if (!empty($config['includeIntroPage']) && trim($introMarkdown) !== '') {
            $pages[] = self::buildPage([
                'title' => 'Overview',
                'level' => $splitLevel,
                'order' => count($pages) + 1,
                'markdown' => $introMarkdown,
                'usedSlugs' => &$usedSlugs,
                'config' => $config,
            ]);
        }

        foreach ($splitHeadings as $index => $heading) {
            $nextHeading = $splitHeadings[$index + 1] ?? null;
            $length = $nextHeading !== null ? $nextHeading['line'] - $heading['line'] : count($lines) - $heading['line'];
            $sectionLines = array_slice($lines, $heading['line'], $length);

            $pages[] = self::buildPage([
                'title' => self::cleanHeadingText($heading['text']),
                'level' => $heading['level'],
                'order' => count($pages) + 1,
                'markdown' => implode("\n", self::trimOuterBlankLines($sectionLines)),
                'parentSlug' => self::findParentSlug($headings, $heading, $usedSlugs),
                'usedSlugs' => &$usedSlugs,
                'config' => $config,
            ]);
        }

        return $pages;
    }

    private static function buildPage(array $data): array
    {
        $title = $data['title'];
        $markdown = $data['markdown'];
        $usedSlugs = &$data['usedSlugs'];
        $config = $data['config'];
        $slug = self::uniqueSlug(self::slugify($title), $usedSlugs);
        $html = self::markdownToHtml($markdown);
        $plainText = self::markdownToPlainText($markdown);

        return [
            'id' => 'page-' . $data['order'],
            'title' => $title,
            'level' => $data['level'],
            'slug' => $slug,
            'order' => $data['order'],
            'parentSlug' => $data['parentSlug'] ?? null,
            'markdown' => $markdown,
            'html' => $html,
            'plainText' => $plainText,
            'excerpt' => self::createExcerpt($plainText),
            'headings' => array_map(static function (array $heading): array {
                return [
                    'level' => $heading['level'],
                    'title' => self::cleanHeadingText($heading['text']),
                    'slug' => self::slugify($heading['text']),
                ];
            }, self::collectHeadings(explode("\n", $markdown))),
            'feedback' => self::createFeedbackFormModel($slug, $config['commentForm']),
        ];
    }

    private static function createFeedbackFormModel(string $pageSlug, array $formConfig): array
    {
        return [
            'id' => 'feedback-' . $pageSlug,
            'pageSlug' => $pageSlug,
            'method' => $formConfig['method'] ?? 'post',
            'action' => $formConfig['action'] ?? '',
            'fields' => [
                [
                    'name' => 'name',
                    'label' => 'Name',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'name' => 'email',
                    'label' => 'Email',
                    'type' => 'email',
                    'required' => false,
                ],
                [
                    'name' => 'feedback_type',
                    'label' => 'Feedback Type',
                    'type' => 'select',
                    'required' => false,
                    'options' => ['Looks good', 'Needs changes', 'Needs discussion'],
                ],
                [
                    'name' => 'comment',
                    'label' => 'Feedback',
                    'type' => 'textarea',
                    'required' => true,
                ],
            ],
        ];
    }

    private static function consumeList(array $lines, int $startIndex, string $markerPattern): array
    {
        $items = [];
        $index = $startIndex;
        $lineCount = count($lines);

        while ($index < $lineCount && preg_match($markerPattern, $lines[$index]) === 1) {
            $items[] = trim(preg_replace($markerPattern, '', $lines[$index]) ?? '');
            $index++;
        }

        return [
            'items' => $items,
            'nextIndex' => $index,
        ];
    }

    private static function renderList(string $tagName, array $items): string
    {
        $body = array_map(static function (string $item): string {
            return '<li>' . self::inlineMarkdown($item) . '</li>';
        }, $items);

        return '<' . $tagName . ">\n" . implode("\n", $body) . "\n</" . $tagName . '>';
    }

    private static function isTableStart(array $lines, int $index): bool
    {
        return isset($lines[$index + 1]) &&
            self::lineLooksLikeTableRow($lines[$index]) &&
            preg_match('/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/', $lines[$index + 1]) === 1;
    }

    private static function consumeTable(array $lines, int $startIndex): array
    {
        $rows = [self::splitTableRow($lines[$startIndex])];
        $alignments = array_map([self::class, 'getColumnAlignment'], self::splitTableRow($lines[$startIndex + 1]));
        $index = $startIndex + 2;
        $lineCount = count($lines);

        while ($index < $lineCount && self::lineLooksLikeTableRow($lines[$index])) {
            $rows[] = self::splitTableRow($lines[$index]);
            $index++;
        }

        return [
            'rows' => $rows,
            'alignments' => $alignments,
            'nextIndex' => $index,
        ];
    }

    private static function renderTable(array $rows, array $alignments): string
    {
        $header = array_shift($rows) ?? [];
        $headerHtml = '';

        foreach ($header as $index => $cell) {
            $headerHtml .= self::renderTableCell('th', $cell, $alignments[$index] ?? '');
        }

        $bodyRows = [];

        foreach ($rows as $row) {
            $cells = '';

            foreach ($row as $index => $cell) {
                $cells .= self::renderTableCell('td', $cell, $alignments[$index] ?? '');
            }

            $bodyRows[] = '<tr>' . $cells . '</tr>';
        }

        return "<table>\n<thead><tr>{$headerHtml}</tr></thead>\n<tbody>\n" . implode("\n", $bodyRows) . "\n</tbody>\n</table>";
    }

    private static function renderTableCell(string $tagName, string $cell, string $alignment): string
    {
        $alignAttribute = $alignment !== '' ? ' style="text-align: ' . self::escapeAttribute($alignment) . '"' : '';

        return '<' . $tagName . $alignAttribute . '>' . self::inlineMarkdown(trim($cell)) . '</' . $tagName . '>';
    }

    private static function getColumnAlignment(string $separator): string
    {
        $value = trim($separator);
        $startsWithColon = substr($value, 0, 1) === ':';
        $endsWithColon = substr($value, -1) === ':';

        if ($startsWithColon && $endsWithColon) {
            return 'center';
        }

        if ($endsWithColon) {
            return 'right';
        }

        if ($startsWithColon) {
            return 'left';
        }

        return '';
    }

    private static function lineLooksLikeTableRow(string $line): bool
    {
        return strpos($line, '|') !== false && !self::isBlank($line);
    }

    private static function splitTableRow(string $line): array
    {
        $trimmed = trim($line);
        $trimmed = preg_replace('/^\|/', '', $trimmed);
        $trimmed = preg_replace('/\|$/', '', $trimmed ?? '');

        return array_map('trim', explode('|', $trimmed ?? ''));
    }

    private static function isBlockStart(array $lines, int $index): bool
    {
        $line = $lines[$index];

        return preg_match('/^```/', $line) === 1 ||
            preg_match('/^(#{1,6})\s+/', $line) === 1 ||
            preg_match('/^\s*[-*_]\s*([-*_]\s*){2,}$/', $line) === 1 ||
            preg_match('/^\s*>\s?/', $line) === 1 ||
            preg_match('/^\s*[-*+]\s+/', $line) === 1 ||
            preg_match('/^\s*\d+[.)]\s+/', $line) === 1 ||
            self::isTableStart($lines, $index);
    }

    private static function inlineMarkdown(string $text): string
    {
        $codeTokens = [];
        $output = preg_replace_callback('/`([^`]+)`/', static function (array $match) use (&$codeTokens): string {
            $token = '@@CODE' . count($codeTokens) . '@@';
            $codeTokens[] = '<code>' . self::escapeHtml($match[1]) . '</code>';

            return $token;
        }, $text);

        $output = self::escapeHtml($output ?? '');
        $output = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+&quot;([^&]*)&quot;)?\)/', static function (array $match): string {
            $titleAttribute = isset($match[3]) ? ' title="' . self::escapeAttribute($match[3]) . '"' : '';

            return '<img src="' . self::escapeAttribute($match[2]) . '" alt="' . self::escapeAttribute($match[1]) . '"' . $titleAttribute . '>';
        }, $output);
        $output = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)(?:\s+&quot;([^&]*)&quot;)?\)/', static function (array $match): string {
            $titleAttribute = isset($match[3]) ? ' title="' . self::escapeAttribute($match[3]) . '"' : '';

            return '<a href="' . self::escapeAttribute($match[2]) . '"' . $titleAttribute . '>' . $match[1] . '</a>';
        }, $output ?? '');
        $output = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $output ?? '');
        $output = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $output ?? '');
        $output = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $output ?? '');
        $output = preg_replace('/_([^_]+)_/', '<em>$1</em>', $output ?? '');
        $output = preg_replace('/\[([^\]]+)\]\{\.underline\}/', '<u>$1</u>', $output ?? '');

        foreach ($codeTokens as $index => $html) {
            $output = str_replace('@@CODE' . $index . '@@', $html, $output ?? '');
        }

        return $output ?? '';
    }

    private static function collectHeadings(array $lines): array
    {
        $headings = [];
        $inFence = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^```/', trim($line)) === 1) {
                $inFence = !$inFence;
                continue;
            }

            if ($inFence) {
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $match) === 1) {
                $headings[] = [
                    'level' => strlen($match[1]),
                    'text' => self::cleanHeadingText($match[2]),
                    'line' => $index,
                ];
            }
        }

        return $headings;
    }

    private static function chooseSplitLevel(array $headings, int $preferredLevel): int
    {
        foreach ($headings as $heading) {
            if ($heading['level'] === $preferredLevel) {
                return $preferredLevel;
            }
        }

        $availableLevels = array_values(array_unique(array_map(static function (array $heading): int {
            return $heading['level'];
        }, $headings)));
        sort($availableLevels);

        return $availableLevels[0] ?? 1;
    }

    private static function getDocumentTitle(array $headings, ?string $explicitTitle): string
    {
        if ($explicitTitle !== null && $explicitTitle !== '') {
            return $explicitTitle;
        }

        foreach ($headings as $heading) {
            if ($heading['level'] === 1) {
                return self::cleanHeadingText($heading['text']);
            }
        }

        return 'Untitled Document';
    }

    private static function removeDocumentTitle(array $lines, array $headings): array
    {
        $firstHeading = $headings[0] ?? null;

        if ($firstHeading === null || $firstHeading['level'] !== 1 || $firstHeading['line'] >= count($lines)) {
            return $lines;
        }

        return array_values(array_filter($lines, static function ($_line, int $index) use ($firstHeading): bool {
            return $index !== $firstHeading['line'];
        }, ARRAY_FILTER_USE_BOTH));
    }

    private static function findParentSlug(array $headings, array $heading, array $usedSlugs): ?string
    {
        $headingIndex = -1;

        foreach ($headings as $index => $candidate) {
            if ($candidate['line'] === $heading['line'] && $candidate['text'] === $heading['text']) {
                $headingIndex = $index;
                break;
            }
        }

        if ($headingIndex <= 0) {
            return null;
        }

        for ($index = $headingIndex - 1; $index >= 0; $index--) {
            if ($headings[$index]['level'] < $heading['level']) {
                $baseSlug = self::slugify($headings[$index]['text']);

                return array_key_exists($baseSlug, $usedSlugs) ? $baseSlug : null;
            }
        }

        return null;
    }

    private static function createExcerpt(string $text, int $maxLength = 180): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(substr($text, 0, $maxLength - 1)) . '...';
    }

    private static function normalizeLineEndings(string $text): string
    {
        return preg_replace("/\r\n?/", "\n", $text) ?? $text;
    }

    private static function trimOuterBlankLines(array $lines): array
    {
        $start = 0;
        $end = count($lines);

        while ($start < $end && self::isBlank($lines[$start])) {
            $start++;
        }

        while ($end > $start && self::isBlank($lines[$end - 1])) {
            $end--;
        }

        return array_slice($lines, $start, $end - $start);
    }

    private static function cleanHeadingText(string $text): string
    {
        return trim(preg_replace('/\s+#*$/', '', $text) ?? $text);
    }

    private static function isBlank(string $line): bool
    {
        return preg_match('/^\s*$/', $line) === 1;
    }

    private static function uniqueSlug(string $baseSlug, array &$usedSlugs): string
    {
        $currentCount = $usedSlugs[$baseSlug] ?? 0;
        $usedSlugs[$baseSlug] = $currentCount + 1;

        return $currentCount === 0 ? $baseSlug : $baseSlug . '-' . ($currentCount + 1);
    }

    private static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function escapeAttribute(string $value): string
    {
        return str_replace('`', '&#96;', self::escapeHtml($value));
    }

    private static function mergeOptions(array $base, array $override): array
    {
        $merged = array_merge($base, $override);
        $merged['commentForm'] = array_merge($base['commentForm'], $override['commentForm'] ?? []);

        return $merged;
    }
}

function markhtml_markdown_parser_print_usage(): void
{
    echo "Usage:\n";
    echo "  php markdown-parser.php <file.md> [--out parsed.json] [--split-level 2]\n\n";
    echo "Examples:\n";
    echo "  php markdown-parser.php feedback-document.md\n";
    echo "  php markdown-parser.php feedback-document.md --out parsed-site-php.json\n";
    echo "  php markdown-parser.php feedback-document.md --split-level 3\n";
}

function markhtml_markdown_parser_parse_cli_args(array $argv): array
{
    $args = [
        'input' => null,
        'out' => null,
        'splitLevel' => 2,
        'help' => false,
    ];

    for ($index = 0, $count = count($argv); $index < $count; $index++) {
        $value = $argv[$index];

        if ($value === '--help' || $value === '-h') {
            $args['help'] = true;
            continue;
        }

        if ($value === '--out') {
            $args['out'] = $argv[$index + 1] ?? null;
            $index++;
            continue;
        }

        if ($value === '--split-level') {
            $args['splitLevel'] = (int) ($argv[$index + 1] ?? 2);
            $index++;
            continue;
        }

        if ($args['input'] === null) {
            $args['input'] = $value;
        }
    }

    return $args;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $args = markhtml_markdown_parser_parse_cli_args(array_slice($argv, 1));

    if ($args['help'] || $args['input'] === null) {
        markhtml_markdown_parser_print_usage();
        exit($args['help'] ? 0 : 1);
    }

    try {
        $parsed = MarkHtmlMarkdownParser::parseMarkdownFile($args['input'], [
            'splitLevel' => $args['splitLevel'],
        ]);
        $json = json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Unable to encode parser output as JSON.');
        }

        $json .= "\n";

        if ($args['out'] !== null) {
            file_put_contents($args['out'], $json);
            echo 'Parsed ' . $parsed['pageCount'] . ' pages from ' . $args['input'] . ' into ' . $args['out'] . "\n";
        } else {
            echo $json;
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
}
