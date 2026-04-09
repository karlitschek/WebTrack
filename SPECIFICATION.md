# WebTrack — Application Specification

## Overview

WebTrack is a Nextcloud app that monitors websites and RSS/Atom feeds for
keywords. When a keyword is detected, the user receives a Nextcloud
notification and, optionally, a message in a Nextcloud Talk room.

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

### 1.1 `wn_monitors`

| Column              | Type          | Constraints                     |
|----------------------|---------------|---------------------------------|
| `id`                | BIGINT        | PK, auto-increment, unsigned    |
| `user_id`           | STRING(64)    | NOT NULL                        |
| `name`              | STRING(200)   | NOT NULL                        |
| `url`               | STRING(2048)  | NOT NULL                        |
| `keyword`           | STRING(500)   | NOT NULL                        |
| `check_interval`    | INTEGER       | NOT NULL, default: 60           |
| `is_active`         | BOOLEAN       | NOT NULL, default: true         |
| `is_feed`           | BOOLEAN       | NOT NULL, default: false        |
| `use_regex`         | SMALLINT      | NOT NULL, default: 0, unsigned  |
| `last_check_at`     | STRING(32)    | NULLABLE                        |
| `last_found_at`     | STRING(32)    | NULLABLE                        |
| `last_error_at`     | STRING(32)    | NULLABLE                        |
| `last_error_msg`    | STRING(2048)  | NULLABLE                        |
| `consecutive_errors`| INTEGER       | NOT NULL, default: 0            |
| `talk_room_token`   | STRING(32)    | NULLABLE                        |
| `status`            | STRING(20)    | NOT NULL, default: 'ok'         |
| `created_at`        | STRING(32)    | NOT NULL                        |

Indexes: `wn_mon_uid` (user_id), `wn_mon_active` (is_active).

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

| Status    | Meaning                              | Transitions to         |
|-----------|--------------------------------------|------------------------|
| `ok`      | Healthy, keyword not currently found | `found`, `error`, `paused` |
| `found`   | Keyword currently detected           | `ok`, `error`, `paused`    |
| `error`   | Fetch failed, < 5 consecutive errors | `ok`, `found`, `failing`, `paused` |
| `failing` | ≥ 5 consecutive fetch errors         | `ok`, `found`, `paused`    |
| `paused`  | Manually paused by user              | `ok`                       |

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

| Method | Path                          | Description            | Status Codes          |
|--------|-------------------------------|------------------------|-----------------------|
| GET    | `/monitors`                   | List all user monitors | 200                   |
| POST   | `/monitors`                   | Create a monitor       | 201, 422              |
| GET    | `/monitors/{id}`              | Get single monitor     | 200, 404              |
| PUT    | `/monitors/{id}`              | Update a monitor       | 200, 404, 422         |
| DELETE | `/monitors/{id}`              | Delete a monitor       | 204, 404              |
| POST   | `/monitors/{id}/pause`        | Pause/resume           | 200, 404              |
| POST   | `/monitors/test`              | Test a URL             | 200, 422, 502         |

### 3.2 History

| Method | Path                                 | Description            | Status Codes |
|--------|--------------------------------------|------------------------|--------------|
| GET    | `/monitors/{monitorId}/history`      | Paginated event history| 200, 404     |

Query parameter: `page` (0-indexed, 50 items per page).

### 3.3 Settings

| Method | Path         | Description          |
|--------|--------------|----------------------|
| GET    | `/settings`  | Get user settings    |
| PUT    | `/settings`  | Save user settings   |

User setting: `defaultTalkRoomToken` (string).

### 3.4 Talk Rooms

| Method | Path          | Description                         |
|--------|---------------|-------------------------------------|
| GET    | `/talk/rooms` | List user's Talk rooms (if spreed installed) |

Returns `[{token, name, type}]` or `[]`.

---

## 4. Monitor Create/Update Fields

| Field            | Type    | Validation                                          |
|------------------|---------|-----------------------------------------------------|
| `name`           | string  | Required, trimmed                                   |
| `url`            | string  | Required, must pass `FILTER_VALIDATE_URL`           |
| `keyword`        | string  | Required; if regex, must be a valid PCRE pattern    |
| `useRegex`       | boolean | Optional, default false                             |
| `checkInterval`  | integer | Minimum 1 (minutes)                                 |
| `isFeed`         | boolean | Optional, auto-detected via URL test                |
| `talkRoomToken`  | string  | Optional, nullable (empty string → null)            |

Available check intervals in the UI: 1, 5, 15, 30, 60, 120, 360, 720, 1440 minutes.

Regex patterns without delimiters are auto-wrapped in `/.../iu`.

---

## 5. Background Jobs

### 5.1 CheckMonitorsJob

- Runs every **10 minutes** (Nextcloud cron).
- Iterates all active monitors.
- Per-monitor interval gating: skips if `lastCheckAt + checkInterval > now`.
- Fetches URL, searches for keyword, updates status, sends notifications.

### 5.2 PurgeHistoryJob

- Runs **daily**.
- Deletes history logs older than **100 days**.

---

## 6. Check Logic

### 6.1 Web Page Flow

1. Fetch URL via HTTP GET (timeout 30s, connect 10s, max 5 redirects).
2. Convert HTML to plain text (strip scripts/styles/tags, decode entities).
3. Search for keyword (plain text: case-insensitive `mb_stripos`; regex: `preg_match`).
4. If found and not in cooldown → set status to `found`, extract snippet, send notification, log event.
5. If not found and status was `found` → reset to `ok`.

### 6.2 RSS/Atom Feed Flow

1. Fetch URL.
2. Detect feed format (`<rss>`, `<feed>`, `<rdf:RDF>`).
3. Parse entries via SimpleXML (handles RSS `<item>`, Atom `<entry>`, `content:encoded`).
4. Filter to only new entries using `wn_feed_state` (tracks up to 500 seen IDs).
5. First run: seed state, return no matches (avoids notification spam).
6. Search each new entry for keyword. If found → notify and log.

### 6.3 Error Handling

- On fetch failure: increment `consecutiveErrors`, set `lastErrorMsg` (max 2048 chars).
- Status escalation: `error` at 1–4 failures, `failing` at ≥ 5.
- Error notifications sent only on the **3rd** and **5th** consecutive error.
- On successful check after errors: reset `consecutiveErrors` to 0, restore status.

### 6.4 Re-notification Cooldown

After a keyword match is found, re-notification is suppressed for
`checkInterval × 3` minutes. This prevents repeated notifications for
persistently present keywords.

---

## 7. Notifications

### 7.1 Nextcloud Notifications

| Subject          | Message Template                                   |
|------------------|----------------------------------------------------|
| `keyword_found`  | Keyword "%keyword" found on %monitorName           |
| `check_error`    | Monitor "%monitorName" failed %errorCount times    |

Notifications link to `/apps/webtrack/#/monitors/{monitorId}`.

### 7.2 Talk Integration

When a monitor has a `talkRoomToken` set and a keyword is found, a message
is posted to the specified Talk room:

> 🔔 WebTrack: keyword "%keyword" found on %monitorName — %snippet

Requires the `spreed` (Talk) app to be installed. Uses the Talk internal
ChatManager API with the monitor owner as the actor.

---

## 8. Frontend

### 8.1 Technology Stack

- Vue 2.7, Vue Router 3 (hash mode)
- Nextcloud Vue component library (`@nextcloud/vue` 8.x)
- Material Design Icons (`vue-material-design-icons`)
- Vite build with `@nextcloud/vite-config`

### 8.2 Views

**Welcome Screen** (`/`): Displays app name, description, and a 2×2 feature
grid (Web pages, RSS/Atom feeds, Notifications, Talk integration) with
monochrome icons. Instructs users to select a monitor or create a new one.

**Monitor Detail** (`/monitors/:id`): Shows a header card with status badge,
URL, and Edit/Pause actions. Below that, a responsive info card grid
displaying keyword, check interval, type, last checked, keyword last seen,
and last error. Followed by the event history timeline.

### 8.3 Sidebar Navigation

Lists all monitors with a colored status dot and a relative "time ago"
indicator for the last check. Each item has an action menu with Edit,
Pause/Resume, and Delete options. A "New monitor" button is at the top.

A settings section at the bottom allows setting a default Talk room.

### 8.4 Monitor Form (Modal)

Fields: Name, URL (with auto-test on blur and preview), Keyword, Use Regex
toggle, Check interval dropdown, Talk room selector (if Talk installed).

URL testing: on blur, the URL is tested via the `/monitors/test` endpoint.
Feedback is shown inline (feed detected / page reachable / error). If a feed
is detected, `isFeed` is auto-set.

### 8.5 Event History Timeline

Vertical timeline with colored dots per event type. Each entry shows the
event badge, timestamp, and either a snippet (with keyword highlighted in
bold) or an error message. Paginated at 50 items per page.

---

## 9. Snippet Extraction

When a keyword match is found, a snippet is extracted with **100 characters**
of context on each side. The matched keyword is wrapped in `**bold**` markers.
In the frontend, these markers are rendered as `<strong>` tags after HTML
entity escaping (XSS-safe).

---

## 10. Security

- Only `http` and `https` URL schemes are permitted.
- All API endpoints require authentication (`#[NoAdminRequired]`).
- Monitors are scoped per user — users can only access their own monitors.
- HTML snippets are entity-escaped before rendering (XSS prevention).
- Feed parsing uses `LIBXML_NONET` to prevent XXE attacks.
- Regex patterns are validated before saving.

---

## 11. Key Limits & Constants

| Parameter                        | Value     |
|----------------------------------|-----------|
| Max monitors per user            | 100       |
| Default check interval           | 60 min    |
| Minimum check interval           | 1 min     |
| Background job frequency         | 10 min    |
| Re-notification cooldown         | interval × 3 |
| Error notification thresholds    | 3rd, 5th  |
| Failing status threshold         | ≥ 5 errors|
| History retention                | 100 days  |
| History page size                | 50        |
| Snippet context window           | 100 chars |
| Max tracked feed entry IDs       | 500       |
| HTTP fetch timeout               | 30s       |
| HTTP connect timeout             | 10s       |
| Max HTTP redirects               | 5         |
| Error message max (DB)           | 2048 chars|
| Error message max (notification) | 200 chars |
| URL preview max                  | 500 chars |

---

## 12. Internationalization

All user-facing strings are wrapped in translation functions:

- **Vue/JS**: `t('webtrack', '...')` and `n('webtrack', singular, plural, count)`
- **PHP**: `$this->l->t('...')` via `OCP\IL10N` dependency injection

This covers UI labels, validation messages, error messages, notification
text, and Talk messages.

---

## 13. Dependencies

### PHP (via Composer)

- Runtime: Nextcloud OCP APIs only (no external dependencies)
- Dev: `nextcloud/ocp`

### JavaScript (via npm)

- `vue` 2.7, `vue-router` 3.6
- `@nextcloud/vue` 8.37, `@nextcloud/axios`, `@nextcloud/dialogs`, `@nextcloud/l10n`, `@nextcloud/router`
- `vue-material-design-icons` 5.3
- Build: `vite` 7.1, `@nextcloud/vite-config` 1.7
