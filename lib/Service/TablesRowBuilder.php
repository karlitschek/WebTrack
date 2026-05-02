<?php

declare(strict_types=1);

namespace OCA\WebTrack\Service;

use OCA\WebTrack\Db\Monitor;

/**
 * Builds the `data` payload for a Nextcloud Tables row from a feed entry.
 *
 * Column IDs are resolved by matching column titles from the table schema;
 * the builder is therefore table-agnostic and works with any PR Coverage-style
 * table as long as the column names match (case-insensitively) the keys in
 * COLUMN_BUILDERS.
 *
 * Columns that cannot be derived automatically (Journalist, Volume, Primary
 * Topic) are left empty so the human reviewer can fill them in later.
 */
class TablesRowBuilder {

    public function __construct(
        private DomainLookupService $domainLookup,
    ) {
    }

    /**
     * Builds the `data` array for TablesService::insertRow().
     *
     * @param array<array{id:int,title:string,type:string,selectionOptions:array<array{id:int,label:string}>}> $columns
     *   Full column schema as returned by TablesService::getColumns().
     * @param Monitor  $monitor      The monitor that triggered the match.
     * @param string   $entryUrl     Article URL (also the feed entry ID).
     * @param string   $title        Article title.
     * @param string   $pubDate      Article publish date (any format parseable by strtotime).
     * @param string   $body         Raw article body / feed description (HTML ok).
     * @param int|null $campaignId   Pre-set campaign selection ID (from monitor config).
     * @param string   $channelTitle YouTube channel name (youtube_search only).
     *
     * @return array<array{columnId:int,value:mixed}>
     */
    public function build(
        array   $columns,
        Monitor $monitor,
        string  $entryUrl,
        string  $title,
        string  $pubDate,
        string  $body         = '',
        ?int    $campaignId   = null,
        string  $channelTitle = '',
    ): array {
        // Index columns by normalised title for O(1) lookup.
        $byTitle = [];
        foreach ($columns as $col) {
            $key = strtolower(trim($col['title']));
            $byTitle[$key] = $col;
        }

        $today = date('Y-m-d');
        $date  = $pubDate ? (date('Y-m-d', strtotime($pubDate)) ?: $today) : $today;

        // ── Publication name ──────────────────────────────────────────────────
        // Google News (and many RSS aggregators) append " - PublicationName" to
        // article titles.  Extract the last " - …" segment when the URL is a
        // Google News proxy URL; fall back to hostname parsing for direct URLs.
        $host = strtolower((string) parse_url($entryUrl, PHP_URL_HOST));
        $host = (string) preg_replace('/^www\./', '', $host);

        if (str_contains($host, 'news.google.com') || str_contains($host, 'google.com')) {
            // Try " - Publication" suffix in the title (last occurrence).
            if (preg_match('/ - ([^-]{2,60})$/', $title, $m)) {
                $publication = trim($m[1]);
            } else {
                $publication = '';
            }
        } else {
            // Direct URL: derive from hostname (e.g. "theregister.com" → "Theregister").
            $publication = ucfirst(explode('.', $host)[0]);
        }

        $countryId  = $this->domainLookup->getCountryId($entryUrl);
        $tierId     = $this->domainLookup->getTierId($entryUrl);
        $categoryId = $this->domainLookup->getCategoryId($entryUrl, $title);

        // ── Volume detection ──────────────────────────────────────────────────
        $volumeId = $this->detectVolume($monitor->getKeyword(), $title, $body);

        // Markdown hyperlink for the headline column (rich text).
        $safeTitle   = str_replace(['[', ']', '(', ')'], ['\\[', '\\]', '\\(', '\\)'], $title);
        $mdHeadline  = "[{$safeTitle}]({$entryUrl})";

        // Column value resolvers — keyed by lowercase column title.
        // All lookup columns are plain text in the table schema, so we send
        // human-readable labels rather than integer selection IDs.
        $resolvers = [
            'date'         => $date,
            'country'      => DomainLookupService::countryLabel($countryId),
            'publication'  => $publication,
            'headline'     => $mdHeadline,
            'tier'         => DomainLookupService::tierLabel($tierId),
            'source'       => DomainLookupService::sourceLabel(DomainLookupService::SOURCE_ORGANIC),
            'category'     => DomainLookupService::categoryLabel($categoryId),
            'volume'       => DomainLookupService::volumeLabel($volumeId),
            'counter'      => 1,
            'actual/plan'  => null,    // leave empty
            'journalist'   => '',      // human review required
            'primary topic'=> null,    // human review required
            'comment'      => '',
            'campaign'     => $campaignId,
            // YouTube search: the channel name (e.g. "Nextcloud GmbH")
            'channel'      => $channelTitle !== '' ? $channelTitle : null,
            'youtube channel' => $channelTitle !== '' ? $channelTitle : null,
        ];

        $data = [];
        foreach ($resolvers as $titleKey => $value) {
            if (!isset($byTitle[$titleKey])) {
                continue;   // column not present in this table
            }
            if ($value === null) {
                continue;   // skip optional empty columns
            }
            $data[] = [
                'columnId' => $byTitle[$titleKey]['id'],
                'value'    => $value,
            ];
        }

        return $data;
    }

    /**
     * Infers the "Volume" of coverage from how prominently the tracked keyword
     * appears in the article:
     *
     *   Exclusive     — keyword appears in the headline (title)
     *   Major mention — keyword appears 5 or more times in the body text
     *   Minor mention — keyword appears 1–4 times in the body text
     *
     * Returns a DomainLookupService::VOLUME_* constant.
     */
    private function detectVolume(string $keyword, string $title, string $body): int {
        if ($keyword === '') {
            return DomainLookupService::VOLUME_MINOR_MENTION;
        }

        $lkw = mb_strtolower($keyword);

        // Exclusive: keyword is in the article title.
        if (mb_stripos($title, $lkw) !== false) {
            return DomainLookupService::VOLUME_EXCLUSIVE;
        }

        // Count occurrences in the plain-text body.
        $plainBody = strip_tags($body);
        $count     = substr_count(mb_strtolower($plainBody), $lkw);

        return $count >= 5
            ? DomainLookupService::VOLUME_MAJOR_MENTION
            : DomainLookupService::VOLUME_MINOR_MENTION;
    }
}
