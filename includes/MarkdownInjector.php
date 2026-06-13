<?php

declare(strict_types=1);

require_once __DIR__ . '/../markdown-parser.php';
require_once __DIR__ . '/../CommentManager.php';

class MarkdownInjector
{
    private $cm;

    public function __construct(CommentManager $cm)
    {
        $this->cm = $cm;
    }

    public function syncComments(string $documentId, string $documentPath, int $splitLevel): int
    {
        if (!is_file($documentPath)) {
            throw new Exception("Document file not found: $documentPath");
        }

        $source = file_get_contents($documentPath);
        
        // Strip existing feedback blocks so we can cleanly re-inject
        $source = preg_replace('/\n*<!-- FEEDBACK_START -->[\s\S]*?<!-- FEEDBACK_END -->\n*/', "\n", $source);
        $source = str_replace("\r\n", "\n", $source);
        
        // Parse the document using the existing parser to determine the slugs reliably
        $parsed = MarkHtmlMarkdownParser::parseMarkdownDocument($source, ['splitLevel' => $splitLevel]);
        
        $feedbackBlocks = [];
        $totalInjected = 0;
        
        foreach ($parsed['pages'] as $page) {
            $slug = $page['slug'];
            // Fetch approved comments
            $comments = $this->cm->getComments($documentId, $slug, true);
            
            if (empty($comments)) {
                continue;
            }
            
            // Build the block
            $block = "\n\n<!-- FEEDBACK_START -->\n";
            $block .= "> **Reviewer Feedback**\n>\n";
            
            $commentTree = [];
            $replies = [];
            foreach ($comments as $c) {
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
            
            $commentsToRender = array_values($commentTree);
            
            foreach ($commentsToRender as $c) {
                $feedbackTypeStr = !empty($c['feedback_type']) ? " [{$c['feedback_type']}]" : "";
                $dateStr = date('Y-m-d', strtotime($c['created_at']));
                $block .= "> **{$c['name']}** ({$dateStr}){$feedbackTypeStr}:\n";
                
                $lines = explode("\n", $c['comment']);
                foreach ($lines as $line) {
                    $block .= "> " . trim($line) . "\n";
                }
                
                if (!empty($c['replies'])) {
                    foreach ($c['replies'] as $reply) {
                        $replyDate = date('Y-m-d', strtotime($reply['created_at']));
                        $block .= ">\n> > **{$reply['name']}** ({$replyDate}):\n";
                        $rLines = explode("\n", $reply['comment']);
                        foreach ($rLines as $rLine) {
                            $block .= "> > " . trim($rLine) . "\n";
                        }
                    }
                }
                $block .= ">\n";
            }
            $block .= "<!-- FEEDBACK_END -->\n\n";
            
            $feedbackBlocks[$slug] = $block;
            $totalInjected += count($comments);
        }
        
        if (empty($feedbackBlocks)) {
            // Just write back the stripped version
            file_put_contents($documentPath, rtrim($source) . "\n");
            return 0;
        }

        $lines = explode("\n", $source);
        
        // Find headings to know where to insert
        $headings = [];
        $inFence = false;
        foreach ($lines as $index => $line) {
            if (preg_match('/^```/', trim($line)) === 1) {
                $inFence = !$inFence;
                continue;
            }
            if ($inFence) continue;

            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $match) === 1) {
                if (strlen($match[1]) === $splitLevel) {
                    $headings[] = [
                        'line' => $index,
                        'text' => trim($match[2])
                    ];
                }
            }
        }
        
        $pageIndex = 0;
        $insertions = [];
        
        if (count($headings) === 0) {
            if (isset($parsed['pages'][0]) && isset($feedbackBlocks[$parsed['pages'][0]['slug']])) {
                $insertions[count($lines)] = $feedbackBlocks[$parsed['pages'][0]['slug']];
            }
        } else {
            $firstHeadingLine = $headings[0]['line'];
            
            $hasIntro = false;
            if ($firstHeadingLine > 0) {
                $introLines = array_slice($lines, 0, $firstHeadingLine);
                $introMarkdown = implode("\n", $introLines);
                // Strip level 1 headings (title) to check if there's actual intro content
                $introMarkdown = preg_replace('/^#\s+.+$/m', '', $introMarkdown);
                if (trim($introMarkdown) !== '') {
                    $hasIntro = true;
                }
            }
            
            if ($hasIntro) {
                if (isset($parsed['pages'][0]) && isset($feedbackBlocks[$parsed['pages'][0]['slug']])) {
                    $insertions[$firstHeadingLine] = $feedbackBlocks[$parsed['pages'][0]['slug']];
                }
                $pageIndex++;
            }
            
            for ($i = 0; $i < count($headings); $i++) {
                if (isset($parsed['pages'][$pageIndex])) {
                    $slug = $parsed['pages'][$pageIndex]['slug'];
                    if (isset($feedbackBlocks[$slug])) {
                        $nextLine = isset($headings[$i + 1]) ? $headings[$i + 1]['line'] : count($lines);
                        $insertions[$nextLine] = $feedbackBlocks[$slug];
                    }
                }
                $pageIndex++;
            }
        }
        
        $out = "";
        for ($i = 0; $i <= count($lines); $i++) {
            if (isset($insertions[$i])) {
                $out .= $insertions[$i];
            }
            if ($i < count($lines)) {
                $out .= $lines[$i] . "\n";
            }
        }
        
        file_put_contents($documentPath, rtrim($out) . "\n");
        return $totalInjected;
    }
}
