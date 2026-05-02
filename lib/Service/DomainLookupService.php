<?php

declare(strict_types=1);

namespace OCA\WebTrack\Service;

/**
 * Maps article URLs to PR Coverage table selection IDs.
 *
 * The lookup table is seeded from historical CSV data exported from the PR
 * Coverage table on cloud.nextcloud.com.  Exact domain matches win; falling
 * back to TLD-based heuristics for unknown domains.
 *
 * Selection IDs mirror the options defined in the Nextcloud Tables schema:
 *
 *   Country: Germany=0, Austria=1, Switzerland=2, France=3, Spain=4,
 *            Netherlands=5, Belgium=6, UK=7, US=8, Denmark=9, Sweden=10,
 *            Finland=11, Norway=12, Japan=13, Middle East=14, Italy=15,
 *            EU=16, other=17
 *
 *   Tier:    major business press & newspapers=0,
 *            major tech or industry trade press=1,
 *            YouTube / podcasts / other channels=2,
 *            local tech press=3,
 *            other=4
 *
 *   Category: Media Article=0, YouTube=1, Blog=2, TV=3, Radio=4, other=5,
 *             Podcast=6
 */
class DomainLookupService {

    // -------------------------------------------------------------------------
    // Country IDs
    // -------------------------------------------------------------------------
    public const COUNTRY_GERMANY     = 0;
    public const COUNTRY_AUSTRIA     = 1;
    public const COUNTRY_SWITZERLAND = 2;
    public const COUNTRY_FRANCE      = 3;
    public const COUNTRY_SPAIN       = 4;
    public const COUNTRY_NETHERLANDS = 5;
    public const COUNTRY_BELGIUM     = 6;
    public const COUNTRY_UK          = 7;
    public const COUNTRY_US          = 8;
    public const COUNTRY_DENMARK     = 9;
    public const COUNTRY_SWEDEN      = 10;
    public const COUNTRY_FINLAND     = 11;
    public const COUNTRY_NORWAY      = 12;
    public const COUNTRY_JAPAN       = 13;
    public const COUNTRY_MIDDLE_EAST = 14;
    public const COUNTRY_ITALY       = 15;
    public const COUNTRY_EU          = 16;
    public const COUNTRY_OTHER       = 17;

    // -------------------------------------------------------------------------
    // Tier IDs
    // -------------------------------------------------------------------------
    public const TIER_MAJOR_BUSINESS = 0;   // major business press & newspapers
    public const TIER_MAJOR_TECH     = 1;   // major tech or industry trade press
    public const TIER_YOUTUBE        = 2;   // YouTube, podcasts, other channels
    public const TIER_LOCAL_TECH     = 3;   // local tech press
    public const TIER_OTHER          = 4;   // other

    // -------------------------------------------------------------------------
    // Source IDs
    // -------------------------------------------------------------------------
    public const SOURCE_ORGANIC          = 0;
    public const SOURCE_PRESS_RELEASE    = 1;
    public const SOURCE_INTERVIEW        = 2;
    public const SOURCE_WRITTEN_STATEMENT= 3;
    public const SOURCE_BLOG             = 4;

    // -------------------------------------------------------------------------
    // Category IDs
    // -------------------------------------------------------------------------
    public const CAT_MEDIA_ARTICLE = 0;
    public const CAT_YOUTUBE       = 1;
    public const CAT_BLOG          = 2;
    public const CAT_TV            = 3;
    public const CAT_RADIO         = 4;
    public const CAT_OTHER         = 5;
    public const CAT_PODCAST       = 6;

    // -------------------------------------------------------------------------
    // Volume IDs
    // -------------------------------------------------------------------------
    public const VOLUME_EXCLUSIVE     = 0;  // keyword in title → dedicated/exclusive coverage
    public const VOLUME_MAJOR_MENTION = 1;  // keyword mentioned 5+ times in body
    public const VOLUME_MINOR_MENTION = 2;  // keyword mentioned 1–4 times in body

    // -------------------------------------------------------------------------
    // Human-readable labels (for text-type columns in Tables)
    // -------------------------------------------------------------------------
    private const COUNTRY_LABELS = [
        self::COUNTRY_GERMANY     => 'Germany',
        self::COUNTRY_AUSTRIA     => 'Austria',
        self::COUNTRY_SWITZERLAND => 'Switzerland',
        self::COUNTRY_FRANCE      => 'France',
        self::COUNTRY_SPAIN       => 'Spain',
        self::COUNTRY_NETHERLANDS => 'Netherlands',
        self::COUNTRY_BELGIUM     => 'Belgium',
        self::COUNTRY_UK          => 'UK',
        self::COUNTRY_US          => 'US',
        self::COUNTRY_DENMARK     => 'Denmark',
        self::COUNTRY_SWEDEN      => 'Sweden',
        self::COUNTRY_FINLAND     => 'Finland',
        self::COUNTRY_NORWAY      => 'Norway',
        self::COUNTRY_JAPAN       => 'Japan',
        self::COUNTRY_MIDDLE_EAST => 'Middle East',
        self::COUNTRY_ITALY       => 'Italy',
        self::COUNTRY_EU          => 'EU',
        self::COUNTRY_OTHER       => 'Other',
    ];

    private const TIER_LABELS = [
        self::TIER_MAJOR_BUSINESS => 'Major business press',
        self::TIER_MAJOR_TECH     => 'Major tech press',
        self::TIER_YOUTUBE        => 'YouTube / podcast',
        self::TIER_LOCAL_TECH     => 'Local tech press',
        self::TIER_OTHER          => 'Other',
    ];

    private const SOURCE_LABELS = [
        self::SOURCE_ORGANIC           => 'Organic',
        self::SOURCE_PRESS_RELEASE     => 'Press release',
        self::SOURCE_INTERVIEW         => 'Interview',
        self::SOURCE_WRITTEN_STATEMENT => 'Written statement',
        self::SOURCE_BLOG              => 'Blog',
    ];

    private const VOLUME_LABELS = [
        self::VOLUME_EXCLUSIVE     => 'Exclusive',
        self::VOLUME_MAJOR_MENTION => 'Major mention',
        self::VOLUME_MINOR_MENTION => 'Minor mention',
    ];

    private const CATEGORY_LABELS = [
        self::CAT_MEDIA_ARTICLE => 'Media Article',
        self::CAT_YOUTUBE       => 'YouTube',
        self::CAT_BLOG          => 'Blog',
        self::CAT_TV            => 'TV',
        self::CAT_RADIO         => 'Radio',
        self::CAT_OTHER         => 'Other',
        self::CAT_PODCAST       => 'Podcast',
    ];

    public static function countryLabel(int $id): string {
        return self::COUNTRY_LABELS[$id] ?? 'Other';
    }

    public static function tierLabel(int $id): string {
        return self::TIER_LABELS[$id] ?? 'Other';
    }

    public static function sourceLabel(int $id): string {
        return self::SOURCE_LABELS[$id] ?? 'Organic';
    }

    public static function volumeLabel(int $id): string {
        return self::VOLUME_LABELS[$id] ?? 'Minor mention';
    }

    public static function categoryLabel(int $id): string {
        return self::CATEGORY_LABELS[$id] ?? 'Media Article';
    }

    /**
     * Domain → [country_id, tier_id] lookup table.
     * Seeded from ~1 500 historical PR Coverage rows.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const DOMAIN_MAP = [
        // ---- Germany — local tech ----
        'heise.de'                     => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'cloudcomputing-insider.de'    => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'golem.de'                     => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'winfuture.de'                 => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'computerwoche.de'             => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'chip.de'                      => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'connect-professional.de'      => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'egovernment.de'               => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'speicherguide.de'             => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'linux-magazin.de'             => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'fosstopia.de'                 => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'it-business.de'               => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'stadt-bremerhaven.de'         => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'borncity.com'                 => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'linuxnews.de'                 => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'channelpartner.de'            => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'it-daily.net'                 => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'apfeltalk.de'                 => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'storage-insider.de'           => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'digital-magazin.de'           => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'basicthinking.de'             => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        'pcwelt.de'                    => [self::COUNTRY_GERMANY, self::TIER_LOCAL_TECH],
        // ---- Germany — major tech ----
        't3n.de'                       => [self::COUNTRY_GERMANY, self::TIER_MAJOR_TECH],
        // ---- Germany — major business ----
        'background.tagesspiegel.de'   => [self::COUNTRY_GERMANY, self::TIER_MAJOR_BUSINESS],
        'faz.net'                      => [self::COUNTRY_GERMANY, self::TIER_MAJOR_BUSINESS],
        'handelsblatt.com'             => [self::COUNTRY_GERMANY, self::TIER_MAJOR_BUSINESS],
        'sueddeutsche.de'              => [self::COUNTRY_GERMANY, self::TIER_MAJOR_BUSINESS],
        'wiwo.de'                      => [self::COUNTRY_GERMANY, self::TIER_MAJOR_BUSINESS],
        'spiegel.de'                   => [self::COUNTRY_GERMANY, self::TIER_MAJOR_BUSINESS],
        't-online.de'                  => [self::COUNTRY_GERMANY, self::TIER_MAJOR_BUSINESS],
        'sz-dossier.de'                => [self::COUNTRY_GERMANY, self::TIER_MAJOR_BUSINESS],
        'stern.de'                     => [self::COUNTRY_GERMANY, self::TIER_MAJOR_BUSINESS],
        'www1.wdr.de'                  => [self::COUNTRY_GERMANY, self::TIER_MAJOR_BUSINESS],
        'zeit.de'                      => [self::COUNTRY_GERMANY, self::TIER_MAJOR_BUSINESS],
        // ---- Germany — podcasts/YouTube ----
        'deutschlandfunk.de'           => [self::COUNTRY_GERMANY, self::TIER_YOUTUBE],
        // ---- Netherlands — local tech ----
        'computable.nl'                => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        'dutchitchannel.nl'            => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        'tweakers.net'                 => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        'focuson-it.nl'                => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        'telecompaper.com'             => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        'ictmagazine.nl'               => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        'itchannelpro.nl'              => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        'dcpedia.net'                  => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        'ioplus.nl'                    => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        'ct.nl'                        => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        'techzine.nl'                  => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        'dutchitleaders.nl'            => [self::COUNTRY_NETHERLANDS, self::TIER_LOCAL_TECH],
        // ---- Netherlands — major business ----
        'nrc.nl'                       => [self::COUNTRY_NETHERLANDS, self::TIER_MAJOR_BUSINESS],
        'volkskrant.nl'                => [self::COUNTRY_NETHERLANDS, self::TIER_MAJOR_BUSINESS],
        'trouw.nl'                     => [self::COUNTRY_NETHERLANDS, self::TIER_MAJOR_BUSINESS],
        'nos.nl'                       => [self::COUNTRY_NETHERLANDS, self::TIER_MAJOR_BUSINESS],
        // ---- Netherlands — major tech ----
        'bnr.nl'                       => [self::COUNTRY_NETHERLANDS, self::TIER_MAJOR_TECH],
        // ---- Netherlands — other ----
        'ibestuur.nl'                  => [self::COUNTRY_NETHERLANDS, self::TIER_OTHER],
        'mtsprout.nl'                  => [self::COUNTRY_NETHERLANDS, self::TIER_OTHER],
        // ---- France — local tech ----
        'goodtech.info'                => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        'lemondeinformatique.fr'       => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        'clubic.com'                   => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        'zdnet.fr'                     => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        'linformaticien.com'           => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        'globalsecuritymag.fr'         => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        'cio-online.com'               => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        'infodsi.com'                  => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        'itsocial.fr'                  => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        'distributique.com'            => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        'next.ink'                     => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        'channelnews.fr'               => [self::COUNTRY_FRANCE, self::TIER_LOCAL_TECH],
        // ---- France — major tech ----
        'usine-digitale.fr'            => [self::COUNTRY_FRANCE, self::TIER_MAJOR_TECH],
        // ---- UK — major tech ----
        'theregister.com'              => [self::COUNTRY_UK, self::TIER_MAJOR_TECH],
        // ---- UK — local tech ----
        'techradar.com'                => [self::COUNTRY_UK, self::TIER_LOCAL_TECH],
        'computerweekly.com'           => [self::COUNTRY_UK, self::TIER_LOCAL_TECH],
        // ---- US — major tech ----
        'zdnet.com'                    => [self::COUNTRY_US, self::TIER_MAJOR_TECH],
        'computerworld.com'            => [self::COUNTRY_US, self::TIER_MAJOR_TECH],
        'techcrunch.com'               => [self::COUNTRY_US, self::TIER_MAJOR_TECH],
        'thenewstack.io'               => [self::COUNTRY_US, self::TIER_MAJOR_TECH],
        // ---- US — local tech ----
        'xda-developers.com'           => [self::COUNTRY_US, self::TIER_LOCAL_TECH],
        'fossforce.com'                => [self::COUNTRY_US, self::TIER_LOCAL_TECH],
        'howtogeek.com'                => [self::COUNTRY_US, self::TIER_LOCAL_TECH],
        'webpronews.com'               => [self::COUNTRY_US, self::TIER_LOCAL_TECH],
        'linuxiac.com'                 => [self::COUNTRY_US, self::TIER_LOCAL_TECH],
        'makeuseof.com'                => [self::COUNTRY_US, self::TIER_LOCAL_TECH],
        // ---- US — YouTube/podcasts ----
        'tfir.io'                      => [self::COUNTRY_US, self::TIER_YOUTUBE],
        // ---- Denmark — local tech ----
        'computerworld.dk'             => [self::COUNTRY_DENMARK, self::TIER_LOCAL_TECH],
        'version2.dk'                  => [self::COUNTRY_DENMARK, self::TIER_LOCAL_TECH],
        'itwatch.dk'                   => [self::COUNTRY_DENMARK, self::TIER_LOCAL_TECH],
        // ---- Denmark — major tech ----
        'ing.dk'                       => [self::COUNTRY_DENMARK, self::TIER_MAJOR_TECH],
        // ---- Switzerland — local tech ----
        'gnulinux.ch'                  => [self::COUNTRY_SWITZERLAND, self::TIER_LOCAL_TECH],
        'itmagazine.ch'                => [self::COUNTRY_SWITZERLAND, self::TIER_LOCAL_TECH],
        'inside-it.ch'                 => [self::COUNTRY_SWITZERLAND, self::TIER_LOCAL_TECH],
        'netzwoche.ch'                 => [self::COUNTRY_SWITZERLAND, self::TIER_LOCAL_TECH],
        // ---- Austria — local tech ----
        'trendingtopics.eu'            => [self::COUNTRY_AUSTRIA, self::TIER_LOCAL_TECH],
        // ---- Austria — major business ----
        'derstandard.at'               => [self::COUNTRY_AUSTRIA, self::TIER_MAJOR_BUSINESS],
        'diepresse.com'                => [self::COUNTRY_AUSTRIA, self::TIER_MAJOR_BUSINESS],
        // ---- Spain — local tech ----
        'muycomputerpro.com'           => [self::COUNTRY_SPAIN, self::TIER_LOCAL_TECH],
        'muylinux.com'                 => [self::COUNTRY_SPAIN, self::TIER_LOCAL_TECH],
        'laecuaciondigital.com'        => [self::COUNTRY_SPAIN, self::TIER_LOCAL_TECH],
        'ituser.es'                    => [self::COUNTRY_SPAIN, self::TIER_LOCAL_TECH],
        'softzone.es'                  => [self::COUNTRY_SPAIN, self::TIER_LOCAL_TECH],
        // ---- Spain — major tech ----
        'computerhoy.20minutos.es'     => [self::COUNTRY_SPAIN, self::TIER_MAJOR_TECH],
        // ---- Spain — major business ----
        'elespanol.com'                => [self::COUNTRY_SPAIN, self::TIER_MAJOR_BUSINESS],
        'larazon.es'                   => [self::COUNTRY_SPAIN, self::TIER_MAJOR_BUSINESS],
        // ---- Belgium ----
        'belgiumcloud.com'             => [self::COUNTRY_BELGIUM, self::TIER_LOCAL_TECH],
        // ---- Norway ----
        'digi.no'                      => [self::COUNTRY_NORWAY, self::TIER_LOCAL_TECH],
        // ---- Sweden ----
        'computersweden.se'            => [self::COUNTRY_SWEDEN, self::TIER_LOCAL_TECH],
        'techtidningen.se'             => [self::COUNTRY_SWEDEN, self::TIER_LOCAL_TECH],
        // ---- Italy ----
        'ilsoftware.it'                => [self::COUNTRY_ITALY, self::TIER_LOCAL_TECH],
        'digitalworlditalia.it'        => [self::COUNTRY_ITALY, self::TIER_LOCAL_TECH],
        'miamammausalinux.org'         => [self::COUNTRY_ITALY, self::TIER_LOCAL_TECH],
        // ---- EU ----
        'euractiv.com'                 => [self::COUNTRY_EU, self::TIER_OTHER],
        // ---- YouTube (global) ----
        'youtube.com'                  => [self::COUNTRY_OTHER, self::TIER_YOUTUBE],
        // ---- US — other ----
        'selfh.st'                     => [self::COUNTRY_US, self::TIER_OTHER],
    ];

    /**
     * Country TLD → country_id fallback.
     *
     * @var array<string, int>
     */
    private const TLD_COUNTRY_MAP = [
        'de'  => self::COUNTRY_GERMANY,
        'at'  => self::COUNTRY_AUSTRIA,
        'ch'  => self::COUNTRY_SWITZERLAND,
        'fr'  => self::COUNTRY_FRANCE,
        'es'  => self::COUNTRY_SPAIN,
        'nl'  => self::COUNTRY_NETHERLANDS,
        'be'  => self::COUNTRY_BELGIUM,
        'uk'  => self::COUNTRY_UK,
        'co.uk' => self::COUNTRY_UK,
        'dk'  => self::COUNTRY_DENMARK,
        'se'  => self::COUNTRY_SWEDEN,
        'fi'  => self::COUNTRY_FINLAND,
        'no'  => self::COUNTRY_NORWAY,
        'jp'  => self::COUNTRY_JAPAN,
        'it'  => self::COUNTRY_ITALY,
        'pl'  => self::COUNTRY_OTHER,
        'com' => self::COUNTRY_US,
        'org' => self::COUNTRY_US,
        'net' => self::COUNTRY_US,
        'io'  => self::COUNTRY_US,
        'info'=> self::COUNTRY_OTHER,
    ];

    /**
     * Returns the country selection ID for a given article URL.
     *
     * @param string $url Full article URL
     * @return int Selection ID, defaults to COUNTRY_OTHER
     */
    public function getCountryId(string $url): int {
        [$domain, $tld] = $this->parseDomain($url);

        if (isset(self::DOMAIN_MAP[$domain])) {
            return self::DOMAIN_MAP[$domain][0];
        }

        // TLD fallback: try longest-suffix first (co.uk before uk)
        foreach (['co.uk', $tld] as $suffix) {
            if (isset(self::TLD_COUNTRY_MAP[$suffix])) {
                return self::TLD_COUNTRY_MAP[$suffix];
            }
        }

        return self::COUNTRY_OTHER;
    }

    /**
     * Returns the tier selection ID for a given article URL.
     *
     * @param string $url Full article URL
     * @return int Selection ID, defaults to TIER_OTHER
     */
    public function getTierId(string $url): int {
        [$domain] = $this->parseDomain($url);

        if (isset(self::DOMAIN_MAP[$domain])) {
            return self::DOMAIN_MAP[$domain][1];
        }

        // Heuristic for unknown domains: YouTube → tier 2, else TIER_OTHER
        if (stripos($url, 'youtube.com') !== false) {
            return self::TIER_YOUTUBE;
        }

        return self::TIER_OTHER;
    }

    /**
     * Returns the category selection ID derived from the URL and title.
     *
     * @param string $url   Full article URL
     * @param string $title Article title (optional, used for podcast detection)
     * @return int Selection ID
     */
    public function getCategoryId(string $url, string $title = ''): int {
        $urlLower   = strtolower($url);
        $titleLower = strtolower($title);

        if (stripos($urlLower, 'youtube.com') !== false || stripos($urlLower, 'youtu.be') !== false) {
            return self::CAT_YOUTUBE;
        }

        if (preg_match('/podcast|episode|ep\.\s*\d+/i', $urlLower . ' ' . $titleLower)) {
            return self::CAT_PODCAST;
        }

        if (preg_match('/\/(blog|blogs?)\//i', $urlLower)) {
            return self::CAT_BLOG;
        }

        // Check tier from domain — YouTube tier → YouTube category for unknown domains
        [$domain] = $this->parseDomain($url);
        if (isset(self::DOMAIN_MAP[$domain]) && self::DOMAIN_MAP[$domain][1] === self::TIER_YOUTUBE) {
            return self::CAT_YOUTUBE;
        }

        return self::CAT_MEDIA_ARTICLE;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Parses a URL into [stripped_host, top_level_domain].
     *
     * @param string $url
     * @return array{0: string, 1: string}
     */
    private function parseDomain(string $url): array {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        // Extract TLD (last label)
        $parts = explode('.', $host);
        $tld   = end($parts);

        // Check for two-part TLDs like co.uk
        if (count($parts) >= 3) {
            $twoPartTld = implode('.', array_slice($parts, -2));
            if (isset(self::TLD_COUNTRY_MAP[$twoPartTld])) {
                $tld = $twoPartTld;
            }
        }

        return [$host, $tld];
    }
}
