# Changelog

All notable changes to WebTrack are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added
- `MonitorForm`: source type selector (Custom / Google News / YouTube),
  language/region picker, relevance-score threshold, boost-keyword and
  exclude-pattern inputs, target-table picker, and Campaign pre-fill selector
- `TablesController`: proxies Tables list and column schema to the frontend
  (`GET /api/v1/tables`, `GET /api/v1/tables/{id}/columns`); gracefully
  returns empty array when the Tables app is not installed
- `TablesService`: thin HTTP wrapper around Nextcloud Tables REST API v1
  (`listTables`, `getTableSchema`, `getColumns`, `insertRow`, `searchRows`)
- `DomainLookupService`: maps article URLs to Nextcloud Tables selection IDs
  for Country, Tier, and Category; seeded from ~1 500 historical PR Coverage
  rows with TLD-based fallback for unknown domains
- New `wn_monitors` columns for Google News / YouTube source type, relevance
  scoring, and Nextcloud Tables integration (`source_type`, `source_language`,
  `score_threshold`, `boost_keywords`, `exclude_patterns`, `tables_table_id`,
  `tables_campaign_id`) — database migration Version1003
- `Monitor` entity: getters/setters and `jsonSerialize()` entries for all
  new columns; convenience helpers `getBoostKeywordsArray()` and
  `getExcludePatternsArray()`
- `MonitorService::applyData()` handles new fields from create/update requests

### Changed
- Upgrade frontend from Vue 2.7 to Vue 3 and `@nextcloud/vue` 8 → 9
- Replace `vue-router` 3 with `vue-router` 4 (hash-history mode preserved)
- Replace `$root.$on`/`$root.$emit` global event bus with `@nextcloud/event-bus`
- Update all `@nextcloud/vue` component imports to use named exports from the
  package root (`import { NcButton } from '@nextcloud/vue'`)
- Bump `@nextcloud/vite-config` 1.x → 2.x
- Add `"type": "module"` to `package.json`
