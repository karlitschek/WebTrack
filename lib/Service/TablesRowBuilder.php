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
     * @param Monitor $monitor   The monitor that triggered the match.
     * @param string  $entryUrl  Article URL (also the feed entry ID).
     * @param string  $title     Article title.
     * @param string  $pubDate   Article publish date (any format parseable by strtotime).
     * @param int|null $campaignId  Pre-set campaign selection ID (from monitor config).
     *
     * @return array<array{columnId:int,value:mixed}>
     */
    public function build(
        array   $columns,
        Monitor $monitor,
        string  $entryUrl,
        string  $title,
        string  $pubDate,
        ?int    $campaignId,
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

        // Extract the publication name from the article URL hostname.
        $host = strtolower((string) parse_url($entryUrl, PHP_URL_HOST));
        $host = (string) preg_replace('/^www\./', '', $host);
        // Capitalise first segment as a reasonable publication name.
        $publication = ucfirst(explode('.', $host)[0]);

        $countryId  = $this->domainLookup->getCountryId($entryUrl);
        $tierId     = $this->domainLookup->getTierId($entryUrl);
        $categoryId = $this->domainLookup->getCategoryId($entryUrl, $title);

        // Markdown hyperlink for the headline column (rich text).
        $safeTitle   = str_replace(['[', ']', '(', ')'], ['\\[', '\\]', '\\(', '\\)'], $title);
        $mdHeadline  = "[{$safeTitle}]({$entryUrl})";

        // Column value resolvers — keyed by lowercase column title.
        $resolvers = [
            'date'         => $date,
            'country'      => $countryId,
            'publication'  => $publication,
            'headline'     => $mdHeadline,
            'tier'         => $tierId,
            'source'       => DomainLookupService::SOURCE_ORGANIC,
            'category'     => $categoryId,
            'counter'      => 1,
            'actual/plan'  => null,    // leave empty
            'journalist'   => '',      // human review required
            'volume'       => null,    // human review required
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
}
