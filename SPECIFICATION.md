# WebTrack — Application Specification

## Overview

WebTrack is a Nextcloud app that monitors websites, RSS/Atom feeds, Google
News searches, and YouTube channels or searches for keywords. When a keyword
is detected the user receives a Nextcloud notification and, optionally:

- a message in a Nextcloud Talk room, and/or
- a new row in a Nextcloud Tables table.

| Field              | Value                                     |
|--------------------|-------------------------------------------|
| App ID             | `webtrack`                                |
| Version            | 1.0.0                                     |
| License            | AGPL                                      |
| Author             | Frank Karlitschek                         |
| PHP Namespace      | `OCA\WebTrack`                            |
| Category           | tools                                     |
| Nextcloud compat   | 28–34                                     |

---

## 1. Database Schema

The schema is built up by four migrations: `Version1000` (initial), `Version1001`
(adds `use_regex`), `Version1002` (adds `last_found_hash`), and `Version1003`
(adds source/scoring/Tables columns).

### 1.1 `wn_monitors`

| Column                 | Type          | Constraints                          |
|------------------------|---------------|--------------------------------------|
| `id`                   | BIGINT        | PK, auto-increment, unsigned         |
| `user_id`              | STRING(64)    | NOT NULL                             |
| `name`                 | STRING(200)   | NOT NULL                             |
| `url`                  | STRING(2048)  | NOT NULL                             |
| `keyword`              | STRING(500)   | NOT NULL                             |
| `check_interval`       | INTEGER       | NOT NULL, default: 60                |
| `is_active`            | BOOLEAN       | NOT NULL, default: true              |
| `is_feed`              | BOOLEAN       | NOT NULL, default: false             |
| `use_regex`            | SMALLINT      | NOT NULL, default: 0, unsigned       |
| `last_check_at`        | STRING(32)    | NULLABLE                             |
| `last_found_at`        | STRING(32)    | NULLABLE                             |
| `last_found_hash`      | STRING(32)    | NULLABLE                             |
| `last_error_at`        | STRING(32)    | NULLABLE                             |
| `last_error_msg`       | STRING(2048)  | NULLABLE                             |
| `consecutive_errors`   | INTEGER       | NOT NULL, default: 0                 |
| `talk_room_token`      | STRING(32)    | NULLABLE                             |
| `status`               | STRING(20)    | NOT NULL, default: 'ok'              |
| `created_at`           | STRING(32)    | NOT NULL                             |
| `source_type`          | STRING(20)    | NOT NULL, default: 'custom'          |
| `source_language`      | STRING(10)    | NOT NULL, default: 'en-US'           |
| `score_threshold`      | INTEGER       | NOT NULL, default: 2                 |
| `boost_keywords`       | TEXT          | NOT NULL, default: '[]'              |
| `exclude_patterns`     | TEXT          | NOT NULL, default: '["reddit","forum","stackoverflow"]' |
| `tables_table_id`      | BIGINT        | NULLABLE, unsigned                   |
| `tables_campaign_id`   | INTEGER       | NULLABLE                             |

Indexes: `wn_mon_uid` (user_id), `wn_mon_active` (is_active).

`source_type` values currently recognised by the service layer:
`custom`, `google_news`, `youtube`, `youtube_search`. (The `Version1003`
migration header lists `custom`, `google_news`, `youtube`; `youtube_search`
was added later in the service layer using the same column.)

### 1.2 `wn_history`

| Column        | Type          | Constraints                     |
|---------------|---------------|---------------------------------|
| `id`          | BIGINT        | PK, auto-increment, unsigned    |
| `monitor_id`  | BIGINT        | NOT NULL, unsigned              |
| `user_id`     | STRING(64)    | NOT NULL                        |
| `event`       | STRING(20)    | NOT NULL                        |
| `snippet`     | TEXT          | NULLABLE                        |
| `error_msg`   | STRING(2048)  | NULLABLE                        |
| `created_at`  | STRING(32)    | NOT NULL                        |

Indexes: `wn_hist_mid` (monitor_id), `wn_hist_uid` (user_id).

### 1.3 `wn_feed_state`

| Column        | Type          | Constraints                     |
|---------------|---------------|---------------------------------|
| `id`          | BIGINT        | PK, auto-increment, unsigned    |
| `monitor_id`  | BIGINT        | NOT NULL, unsigned              |
| `seen_ids`    | TEXT          | NOT NULL, default: '[]'         |
| `updated_at`  | STRING(32)    | NOT NULL                        |

Unique index: `wn_fs_mid` (monitor_id).

---

## 2. Monitor Status Model

| Status    | Meaning                              | Transitions to                      |
|-----------|--------------------------------------|-------------------------------------|
| `ok`      | Healthy, keyword not currently found | `found`, `error`, `paused`          |
| `found`   | Keyword currently detected           | `ok`, `error`, `paused`             |
| `error`   | Fetch failed, < 5 consecutive errors | `ok`, `found`, `failing`, `paused`  |
| `failing` | ≥ 5 consecutive fetch errors         | `ok`, `found`, `paused`             |
| `paused`  | Manually paused by user              | `ok`                                |

### History Event Types

| Event     | Logged when                   |
|-----------|-------------------------------|
| `found`   | Keyword match detected        |
| `error`   | Fetch or check failure        |
| `paused`  | User paused monitor           |
| `resumed` | User resumed monitor          |

---

## 3. REST API

Base path: `/apps/webtrack/api/v1`

All endpoints require authentication. All use `#[NoAdminRequired]`.

### 3.1 Monitors

| Method | Path                            | Description                                | Status Codes      |
|--------|---------------------------------|--------------------------------------------|-------------------|
| GET    | `/monitors`                     | List all monitors for the current user     | 200               |
| POST   | `/monitors`                     | Create a monitor                           | 201, 422          |
| GET    | `/monitors/{id}`                | Get a single monitor                       | 200, 404          |
| PUT    | `/monitors/{id}`                | Update a monitor                           | 200, 404, 422     |
| DELETE | `/monitors/{id}`                | Delete a monitor                           | 204, 404          |
| POST   | `/monitors/{id}/pause`          | Pause / resume                             | 200, 404          |
| POST   | `/monitors/{id}/check`          | Run a check immediately (bypass interval)  | 200, 404, 502     |
| POST   | `/monitors/test`                | Test a URL (fetch + feed-detect + preview) | 200, 422, 502     |

### 3.2 History

| Method | Path                                 | Description             | Status Codes |
|--------|--------------------------------------|-------------------------|--------------|
| GET    | `/monitors/{monitorId}/history`      | Paginated event history | 200, 404     |

Query parameter: `page` (0-indexed, 50 items per page).

### 3.3 Settings

| Method | Path         | Description                  |
|--------|--------------|------------------------------|
| GET    | `/settings`  | Get current user's settings  |
| PUT    | `/settings`  | Save current user's settings |

User settings:

- `defaultTalkRoomToken` (string) — default Talk room token used when a
  monitor doesn't specify its own.
- `youtubeApiKey` (string, write-only) — YouTube Data API v3 key, used by
  the YouTube search source. Only updated when the field is included in the
  PUT body. The `GET` response returns `youtubeApiKeySet: true|false`
  instead of the key value.

### 3.4 Talk Rooms

| Method | Path          | Description                                      |
|--------|---------------|--------------------------------------------------|
| GET    | `/talk/rooms` | List the user's Talk rooms (if `spreed` enabled) |

Returns `[{token, name, type}]` or `[]` if Talk is not installed.

### 3.5 Tables

| Method | Path                          | Description                                   |
|--------|-------------------------------|-----------------------------------------------|
| GET    | `/tables`                     | List the user's Nextcloud Tables tables       |
| GET    | `/tables/{id}/columns`        | Return the column schema for one table        |

Returns `[]` if the Tables app is not installed.

### 3.6 Dashboard

| Method | Path                            | Description                                       |
|--------|---------------------------------|---------------------------------------------------|
| GET    | `/dashboard/recent-finds`       | Latest `found` events for the dashboard widget    |

---

## 4. Monitor Create/Update Fields

| Field               | Type            | Validation / behaviour                                                    |
|---------------------|-----------------|---------------------------------------------------------------------------|
| `name`              | string          | Required, trimmed                                                         |
| `sourceType`        | string          | One of `custom`, `google_news`, `youtube`, `youtube_search`. Default `custom`. Unknown values fall back to `custom`. |
| `url`               | string          | Required for `custom`; must pass `FILTER_VALIDATE_URL`. Auto-built by the server for `google_news` and `youtube`; ignored for `youtube_search`. |
| `youtubeChannelId`  | string          | Required for `youtube`; used to build `https://www.youtube.com/feeds/videos.xml?channel_id=…` |
| `keyword`           | string          | Required. For `youtube_search`, this is the search query. If `useRegex` is true, must be a valid PCRE pattern. |
| `useRegex`          | boolean         | Optional, default false. Patterns without delimiters auto-wrapped as `/.../iu`. |
| `checkInterval`     | integer (min)   | Server clamps to a minimum of 5. The frontend exposes 5, 15, 30, 60, 120, 360, 720, 1440. |
| `isFeed`            | boolean         | Optional. Auto-set to true for `google_news` and `youtube`. For `custom` it is auto-detected via `/monitors/test`. |
| `talkRoomToken`     | string \| null  | Optional, nullable (empty string → null)                                  |
| `sourceLanguage`    | string          | BCP-47 tag (e.g. `en-US`); truncated to 10 chars; used for Google News `hl`/`gl`/`ceid` parameters |
| `scoreThreshold`    | integer (min 0) | Minimum relevance score required. Forced to 0 for `google_news`, `youtube`, and `youtube_search` (scoring is bypassed). |
| `boostKeywords`     | string[] / json | Stored as JSON; non-string entries filtered out. Empty default `[]`.      |
| `excludePatterns`   | string[] / json | Stored as JSON; non-string entries filtered out. Default `["reddit","forum","stackoverflow"]`. |
| `tablesTableId`     | int \| null     | Target Nextcloud Tables table ID for matched feed entries.                |
| `tablesCampaignId`  | int \| null     | Selection-option ID pre-filled into the row's `Campaign` column.          |

A user may have at most **100 monitors** (`MonitorService::MAX_MONITORS_PER_USER`).

---

## 5. Background Jobs

### 5.1 CheckMonitorsJob

- Runs every **5 minutes** (`setInterval(300)`).
- Iterates all active monitors via `MonitorMapper::findAllActive()`.
- Per-monitor interval gating: skips the monitor if
  `lastCheckAt + checkInterval > now`.
- Delegates each check to `MonitorService::executeCheck()`.

### 5.2 PurgeHistoryJob

- Runs **daily** (`setInterval(86400)`).
- Deletes `wn_history` rows older than **100 days**.

---

## 6. Check Logic

### 6.1 Per-monitor dispatch

`MonitorService::executeCheck()` switches on `sourceType`:

- `youtube_search`: calls `YouTubeService::search(keyword, apiKey)` against
  the YouTube Data API v3. Each video result is normalised into the same
  entry shape that the feed pipeline uses
  (`id`, `title`, `content`, `pubDate`, `channelTitle`, `channelId`).
- All other source types: `CheckService::fetch(url)` performs an HTTP GET.
  `MonitorService::handleFeedContent()` is used when `isFeed` is true,
  otherwise `MonitorService::handleWebContent()`.

### 6.2 Web Page Flow (`isFeed = false`)

1. Fetch the URL via `CheckService::fetch()` (timeout 30s, connect 10s,
   max 5 redirects, http(s) only, host validated for SSRF).
2. Convert HTML to plain text: strip `<script>` / `<style>` blocks,
   remaining tags, then decode HTML entities.
3. Search for the keyword (`mb_stripos` for plain text, `preg_match` with
   auto-wrapped delimiters for regex).
4. If found:
   - Compute a context hash via
     `SnippetService::findContextHash()` — the MD5 of the 10 characters
     before and after the match.
   - If the hash differs from the stored `last_found_hash`, update the
     hash and `last_found_at`, set status to `found`, send a notification,
     and append a `found` history event.
   - If the hash is unchanged, **no new notification is sent** — the
     match is unchanged.
5. If not found and the previous status was `found`, reset to `ok` and
   clear `last_found_at` / `last_found_hash`.

### 6.3 RSS / Atom Feed Flow (`isFeed = true`)

1. Fetch the URL.
2. Parse entries via SimpleXML (handles RSS `<item>`, Atom `<entry>`,
   `content:encoded`). Each entry carries `id`, `title`, `content`,
   `pubDate`. RSS prefers `<link>` over `<guid>` for the `id` (Google News
   guids are bare base64 IDs without a scheme).
3. `FeedService::filterNewEntries()` keeps only entries whose `id` is not
   in `wn_feed_state.seen_ids` (capped at 500 IDs, newest kept).
4. **First run** (no existing `wn_feed_state` row): up to 5 of the latest
   entries are returned as new so the monitor fires immediately rather
   than silently. Subsequent runs return only newly-seen entries.
5. For each new entry:
   - **Custom feeds**: if `scoreThreshold > 0`, `ScoringService::isRelevant()`
     scores the entry (+1 per boost keyword, −2 per exclude pattern) and
     drops it if the score is below the threshold.
   - **Google News / YouTube / YouTube search**: scoring is skipped; the
     exclude-pattern list is run as a safeguard against the entry's
     title and URL.
   - `SnippetService::findSnippet()` searches `title + body`. If a match
     is found, the entry triggers a notification and history log, and
     (if configured) a Nextcloud Tables row insert.

### 6.4 YouTube Search Flow (`source_type = youtube_search`)

1. Look up the user's YouTube API key from user-config
   (`OCA\WebTrack:youtube_api_key`).
2. `YouTubeService::search()` GETs
   `https://www.googleapis.com/youtube/v3/search?type=video&part=snippet&order=date&maxResults=50&q=…`.
3. Each video is normalised into a feed-shape entry; the dedup, scoring
   safeguard, snippet, notification, and Tables-insertion paths all share
   the same code as the feed flow.

### 6.5 Error Handling

- On any thrown error during a check: increment `consecutiveErrors`,
  set `lastErrorMsg` (truncated to 2048 chars), set `lastErrorAt`.
- Status escalation: `error` at 1–4 failures, `failing` at ≥ 5.
- Error notifications are sent only on the **3rd** and **5th** consecutive
  error (see `NotificationService::notifyErrorIfNeeded()`).
- A successful check resets `consecutiveErrors` to 0 and restores
  `ok` if the previous status was `error`/`failing`.

### 6.6 Re-notification behaviour

WebTrack does **not** implement a time-based re-notification cooldown.
Re-notification suppression is source-specific:

- **Web pages**: a new notification is sent only when the MD5 hash of the
  10-character context window around the match changes
  (`last_found_hash`). A persistent match in a stable surrounding text
  produces exactly one notification.
- **RSS / Atom feeds and YouTube**: every new entry that matches generates
  one notification. Already-seen entry IDs are deduplicated through
  `wn_feed_state.seen_ids` (up to 500 IDs per monitor); old matches are
  not re-notified.

---

## 7. Notifications

### 7.1 Nextcloud Notifications

| Subject          | Message Template                                |
|------------------|-------------------------------------------------|
| `keyword_found`  | Keyword "%keyword" found on %monitorName        |
| `check_error`    | Monitor "%monitorName" failed %errorCount times |

Notifications link to `/apps/webtrack/#/monitors/{monitorId}`.

### 7.2 Talk Integration

When a monitor has a `talkRoomToken` set and a keyword is found, a message
is posted to the Talk room:

> 🔔 WebTrack: keyword "%keyword" found on %monitorName — %snippet

For feed/YouTube sources where an article URL is available, the snippet is
embedded as a Markdown link `[snippet](articleUrl)` so Talk renders it as
a clickable link.

Talk integration uses the internal `OCA\Talk\Manager` and
`OCA\Talk\Chat\ChatManager` APIs with the monitor owner as the actor. If
the `spreed` app is not installed, the Talk send is skipped.

### 7.3 Tables Integration

If the monitor has a `tablesTableId` set:

- The target table's column schema is loaded once per check cycle.
- If the table has zero columns, `TablesService::ensurePrCoverageColumns()`
  bootstraps a "PR Coverage" column set so the first match writes a row.
- Before insertion, the `Headline` column is searched (contains-match)
  for the article URL; matching rows cause the insert to be skipped
  (duplicate detection).
- `TablesRowBuilder` assembles the row payload:
  - `Headline` — Markdown link `[title](url)`
  - `Publication` — derived from the article hostname
  - `Country`, `Tier`, `Category` — selection IDs resolved by
    `DomainLookupService` from the article URL with TLD-based fallback
  - `Campaign` — `tablesCampaignId` if set
  - `Source` — `Organic`
  - `Counter` — `1`
- Failures during column lookup, schema bootstrap, or row insertion are
  logged and swallowed; the notification is still delivered.

---

## 8. Frontend

### 8.1 Technology Stack

- Vue 3, Vue Router 4 (hash-history mode)
- Nextcloud Vue component library (`@nextcloud/vue` 9.x, named imports
  from the package root)
- `@nextcloud/event-bus` for cross-component events (replaced
  `$root.$on`/`$root.$emit`)
- Material Design Icons (`vue-material-design-icons`)
- Vite build with `@nextcloud/vite-config` 2.x; `package.json` is
  `"type": "module"`

### 8.2 Views

**Welcome Screen** (`/`): Displays app name, description, and a 2×2 feature
grid (Web pages, RSS/Atom feeds, Notifications, Talk integration) with
monochrome icons.

**Monitor Detail** (`/monitors/:id`): Header card with status badge, URL,
and Edit/Pause actions. Below that, an info card grid showing keyword,
check interval, source type, last checked, keyword last seen, and last
error. Followed by the event history timeline.

### 8.3 Sidebar Navigation

Lists all monitors with a colored status dot and a relative "time ago"
indicator for the last check. Each item has an action menu with Edit,
Pause/Resume, and Delete options. A "New monitor" button is at the top.

A settings section at the bottom allows setting a default Talk room and
a YouTube API key.

### 8.4 Monitor Form (Modal)

Fields, organised into source / matching / scoring / schedule fieldsets:

- Source type selector (Custom / Google News / YouTube channel /
  YouTube search), language/region picker (Google News).
- URL (Custom only, with auto-test on blur and preview), Channel ID
  (YouTube channel only).
- Keyword, Use Regex toggle.
- Score threshold, boost keywords, exclude patterns (Custom feeds only).
- Check interval dropdown (5, 15, 30, 60, 120, 360, 720, 1440 minutes).
- Talk room selector (if Talk installed), default-room fallback.
- Target table picker and Campaign pre-fill selector (if Tables installed).

URL testing: on blur, the URL is tested via `POST /monitors/test`. The
response shows whether the URL is reachable and whether it was detected
as a feed (`<rss>`, `<feed>`, `<rdf:RDF>`).

### 8.5 Event History Timeline

Vertical timeline with colored dots per event type. Each entry shows the
event badge, timestamp, and either a snippet (with `**keyword**` rendered
as `<strong>` after HTML-escaping) or an error message. Paginated at
50 items per page.

---

## 9. Snippet Extraction

When a keyword match is found, `SnippetService::findSnippet()` returns
**100 characters** of context on each side of the match. The matched
keyword is wrapped in `**bold**` markers in the stored snippet. The
frontend renders the markers as `<strong>` tags after entity-escaping
the surrounding text.

For web-page monitors a separate
`SnippetService::findContextHash()` returns the MD5 of the 10 characters
before and after the match, used to detect whether the match has moved or
its surroundings have changed (see §6.6).

---

## 10. Security

- **URL scheme allow-list** — only `http` and `https` are accepted; any
  other scheme is rejected before the HTTP client is invoked.
- **SSRF protection** — `CheckService::fetch()` validates the host against
  `OCP\Security\IRemoteHostValidator` for the initial URL **and inside an
  `on_redirect` callback for every redirect hop**, so user-supplied URLs
  cannot be used to probe loopback, link-local, or private-network
  targets through the Nextcloud server. Redirects are also restricted to
  the `http`/`https` protocols and capped at 5. Admins who explicitly set
  `allow_local_remote_servers=true` opt out, and the validator honours
  that flag.
- **Authentication & isolation** — all API endpoints are
  `#[NoAdminRequired]` and authenticated. Mappers and controllers always
  scope queries by `userId`; users cannot see or modify monitors they
  don't own.
- **XSS** — snippets are stored with `**bold**` markers and entity-escaped
  in the frontend before the markers are rendered as `<strong>`.
- **XXE** — feed parsing uses `LIBXML_NONET`. (Note: the legacy
  `libxml_disable_entity_loader()` call is also present but is a no-op on
  PHP 8.0+ / libxml ≥ 2.9.)
- **Regex validation** — patterns are validated before saving via
  `preg_match($pattern, '')`, with auto-delimiter wrapping if the pattern
  has no `/.../` delimiters.

---

## 11. Key Limits & Constants

| Parameter                        | Value      |
|----------------------------------|------------|
| Max monitors per user            | 100        |
| Default check interval           | 60 min     |
| Minimum check interval (server)  | 5 min      |
| Background job frequency         | 5 min      |
| Re-notification cooldown         | none (hash-based for web; per-entry-id for feeds) |
| Error notification thresholds    | 3rd, 5th consecutive error |
| Failing status threshold         | ≥ 5 errors |
| History retention                | 100 days   |
| History page size                | 50         |
| Snippet context window           | 100 chars per side |
| Context-hash window              | 10 chars per side  |
| Max tracked feed entry IDs       | 500        |
| First-run feed seed              | up to 5 latest entries |
| HTTP fetch timeout               | 30 s       |
| HTTP connect timeout             | 10 s       |
| Max HTTP redirects               | 5          |
| YouTube search timeout           | 20 s (5 s connect) |
| YouTube search results per call  | 50 (max)   |
| Error message max (DB)           | 2048 chars |
| Error message max (notification) | 200 chars  |
| URL preview max                  | 500 chars  |

---

## 12. CLI commands (`occ`)

| Command                  | Description                                                                                       |
|--------------------------|---------------------------------------------------------------------------------------------------|
| `occ webtrack:check`     | Run monitor checks now. Flags: `--monitor-id`, `--user`, `--debug` (per-entry score + match), `--dry-run`. Bypasses the per-monitor interval gate. |
| `occ webtrack:test-tables` | Dry-run a Tables row insertion. Flags: `--list-tables`, `--check-dup`, `--insert`. Shows domain-lookup results and the full payload. |
| `occ webtrack:score-url` | Score an article URL against a monitor's boost/exclude rules with a per-rule breakdown.           |

---

## 13. Internationalization

All user-facing strings are wrapped in translation functions:

- **Vue/JS**: `t('webtrack', '...')` and `n('webtrack', singular, plural, count)`
- **PHP**: `$this->l->t('...')` via `OCP\IL10N` dependency injection

This covers UI labels, validation messages, error messages, notification
text, and Talk messages.

---

## 14. Dependencies

### PHP (via Composer)

- Runtime: Nextcloud OCP APIs only (no external dependencies). `spreed`
  (Talk) and `tables` are soft dependencies — code paths handle their
  absence gracefully.
- Dev: `nextcloud/ocp`

### JavaScript (via npm)

- `vue` 3.x, `vue-router` 4.x
- `@nextcloud/vue` 9.x, `@nextcloud/axios`, `@nextcloud/dialogs`,
  `@nextcloud/l10n`, `@nextcloud/router`, `@nextcloud/event-bus`
- `vue-material-design-icons` 5.x
- Build: `vite`, `@nextcloud/vite-config` 2.x; `package.json` declares
  `"type": "module"`
