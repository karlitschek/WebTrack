<?php

declare(strict_types=1);

namespace OCA\WebTrack\Service;

use OCA\WebTrack\Db\Monitor;

/**
 * Scores RSS/Atom feed entries for relevance before they are acted upon.
 *
 * Algorithm
 * ---------
 * Starting score = 0
 *   +1 for every boost keyword found (case-insensitive) in title + content
 *   -2 for every exclude pattern found in the article URL, title, or content
 *
 * The entry is accepted only when score >= monitor->getScoreThreshold().
 *
 * The service is stateless and has no dependencies beyond the Monitor entity,
 * so it is cheap to construct and easy to unit-test.
 */
class ScoringService {

    /**
     * Calculates a relevance score for a single feed entry.
     *
     * @param Monitor $monitor  Monitor whose boost/exclude lists to use
     * @param string  $url      Article URL (used for exclude-pattern matching)
     * @param string  $title    Article title
     * @param string  $content  Article description / body text (may contain HTML)
     * @return int Calculated score (can be negative)
     */
    public function score(Monitor $monitor, string $url, string $title, string $content): int {
        $boostKeywords   = $monitor->getBoostKeywordsArray();
        $excludePatterns = $monitor->getExcludePatternsArray();

        $haystack = mb_strtolower($title . ' ' . strip_tags($content));
        $urlLower = mb_strtolower($url);

        $score = 0;

        foreach ($boostKeywords as $kw) {
            if ($kw !== '' && str_contains($haystack, mb_strtolower($kw))) {
                $score++;
            }
        }

        foreach ($excludePatterns as $pattern) {
            if ($pattern !== '') {
                $pat = mb_strtolower($pattern);
                if (str_contains($urlLower, $pat) || str_contains($haystack, $pat)) {
                    $score -= 2;
                }
            }
        }

        return $score;
    }

    /**
     * Returns true when the entry's relevance score meets the monitor's threshold.
     *
     * @param Monitor $monitor
     * @param string  $url
     * @param string  $title
     * @param string  $content
     */
    public function isRelevant(Monitor $monitor, string $url, string $title, string $content): bool {
        return $this->score($monitor, $url, $title, $content) >= $monitor->getScoreThreshold();
    }
}
