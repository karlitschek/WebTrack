<?php

declare(strict_types=1);

namespace OCA\WebTrack\Service;

class SnippetService {
    private const CONTEXT_CHARS = 100;

    /**
     * Returns a context snippet around the first occurrence of $keyword in $text,
     * with the keyword wrapped in **bold** markers. Returns null if not found.
     */
    public function findSnippet(string $text, string $keyword, bool $useRegex = false): ?string {
        if ($useRegex) {
            return $this->findSnippetRegex($text, $keyword);
        }

        $pos = mb_stripos($text, $keyword);
        if ($pos === false) {
            return null;
        }

        $start  = max(0, $pos - self::CONTEXT_CHARS);
        $length = mb_strlen($keyword) + self::CONTEXT_CHARS * 2;
        $snippet = mb_substr($text, $start, $length);

        // Re-find keyword position within snippet for bold wrapping
        $kwPos = mb_stripos($snippet, $keyword);
        if ($kwPos !== false) {
            $actual = mb_substr($snippet, $kwPos, mb_strlen($keyword));
            $snippet = mb_substr($snippet, 0, $kwPos)
                . '**' . $actual . '**'
                . mb_substr($snippet, $kwPos + mb_strlen($keyword));
        }

        // Trim whitespace and collapse internal whitespace
        $snippet = preg_replace('/\s+/', ' ', trim($snippet));

        return $snippet;
    }

    private function findSnippetRegex(string $text, string $pattern): ?string {
        $delimiter = '/';
        $escaped = $pattern;
        // If the pattern doesn't already include delimiters, wrap it
        if (!preg_match('#^/.+/[a-z]*$#s', $pattern)) {
            $escaped = $delimiter . $pattern . $delimiter . 'iu';
        }

        if (@preg_match($escaped, $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $matchText = $matches[0][0];
        $byteOffset = $matches[0][1];
        // Convert byte offset to character offset
        $pos = mb_strlen(substr($text, 0, $byteOffset));
        $matchLen = mb_strlen($matchText);

        $start  = max(0, $pos - self::CONTEXT_CHARS);
        $length = $matchLen + self::CONTEXT_CHARS * 2;
        $snippet = mb_substr($text, $start, $length);

        // Re-find match within snippet for bold wrapping
        if (@preg_match($escaped, $snippet, $m2, PREG_OFFSET_CAPTURE) === 1) {
            $mPos = mb_strlen(substr($snippet, 0, $m2[0][1]));
            $mText = $m2[0][0];
            $snippet = mb_substr($snippet, 0, $mPos)
                . '**' . $mText . '**'
                . mb_substr($snippet, $mPos + mb_strlen($mText));
        }

        $snippet = preg_replace('/\s+/', ' ', trim($snippet));
        return $snippet;
    }

    /**
     * Returns an MD5 hash of the 10 characters before and after the keyword match.
     * Used to detect whether the keyword has moved to a new location on the page.
     * Returns null if the keyword is not found.
     */
    public function findContextHash(string $text, string $keyword, bool $useRegex = false): ?string {
        if ($useRegex) {
            $escaped = $keyword;
            if (!preg_match('#^/.+/[a-z]*$#s', $keyword)) {
                $escaped = '/' . $keyword . '/iu';
            }
            if (@preg_match($escaped, $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                return null;
            }
            $matchText = $matches[0][0];
            $pos       = mb_strlen(substr($text, 0, $matches[0][1]));
            $matchLen  = mb_strlen($matchText);
        } else {
            $pos = mb_stripos($text, $keyword);
            if ($pos === false) {
                return null;
            }
            $matchLen = mb_strlen($keyword);
        }

        $start   = max(0, $pos - 10);
        $context = mb_substr($text, $start, 10 + $matchLen + 10);
        return md5($context);
    }

    public function containsKeyword(string $text, string $keyword, bool $useRegex = false): bool {
        if ($useRegex) {
            $escaped = $keyword;
            if (!preg_match('#^/.+/[a-z]*$#s', $keyword)) {
                $escaped = '/' . $keyword . '/iu';
            }
            return @preg_match($escaped, $text) === 1;
        }
        return mb_stripos($text, $keyword) !== false;
    }
}
