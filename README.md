# WebTrack

**Monitor websites, RSS/Atom feeds, Google News, and YouTube for keywords — get notified when they appear.**

WebTrack is a [Nextcloud](https://nextcloud.com) app that watches web pages, RSS/Atom feeds, Google News searches, and YouTube channels or searches for specific keywords or regex patterns. When a match is detected, you receive a Nextcloud notification and, optionally, a message in a Nextcloud Talk room or a row in a Nextcloud Tables table.

![WebTrack welcome screen](screenshots/Screenshot%202026-04-09%20at%2022.35.17.png)

---

## Features

### Source types

WebTrack supports four source types per monitor:

- **Custom URL** — any web page or RSS/Atom feed URL you provide. WebTrack auto-detects whether the URL is a feed (`<rss>`, `<feed>`, or `<rdf:RDF>` root element) or a regular HTML page.
- **Google News** — WebTrack builds a Google News RSS search URL from the monitor's keyword, optional boost keywords, exclude patterns, and language/region (e.g. `en-US`).
- **YouTube channel** — WebTrack builds the channel's RSS feed URL from the channel ID you provide.
- **YouTube search** — WebTrack queries the YouTube Data API v3 for new videos matching the keyword. Requires a per-user YouTube API key configured in WebTrack settings.

### Web Page Monitoring
For non-feed URLs, WebTrack fetches the page, strips scripts/styles/tags, decodes HTML entities, and searches the resulting plain text. Only visible content is matched.

### RSS / Atom Feed Monitoring
For feed URLs, WebTrack parses entries via SimpleXML and only notifies you about **new entries**. The first time a feed is added, WebTrack seeds its state with up to 5 of the most recent entries so the monitor fires immediately rather than silently. After that, only entries whose ID has not been seen before (up to the last 500 IDs are remembered per monitor) trigger notifications.

### Keyword & Regex Search
- Plain text search is case-insensitive (`mb_stripos`).
- Enable the **Use Regex** toggle for PCRE patterns. Patterns without delimiters are auto-wrapped as `/pattern/iu`. Patterns are validated server-side before saving.

### Relevance Scoring (custom feeds only)
For custom RSS/Atom feeds you can configure:

- **Boost keywords** — `+1` to the entry score for each boost term that appears in title or content.
- **Exclude patterns** — `-2` for each pattern that appears in the URL, title, or content.
- **Score threshold** — minimum score required for the entry to be acted on.

For Google News and YouTube sources the upstream URL/API already filters by topic, so the score threshold is bypassed and only the exclude-pattern list runs as a safeguard against the entry's title and URL.

### Nextcloud Notifications
You receive a native Nextcloud notification when a keyword is found. Notifications link directly to the monitor detail page.

### Nextcloud Talk Integration
Optionally post an alert to a Talk room when a keyword match is detected. Feed entries also include the article URL as a Markdown link:

> 🔔 WebTrack: keyword "Linux" found on Heise Linux — [*…snippet…*](https://example.com/article)

Set a per-monitor Talk room, or configure a global default in Settings. Requires the `spreed` (Talk) app.

### Nextcloud Tables Integration
Optionally write each matched feed entry as a new row in a Nextcloud Tables table. WebTrack:

- Reads the target table's column schema.
- If the table has no columns yet, auto-creates a "PR Coverage" column set.
- Skips inserts whose article URL already appears in the `Headline` column (duplicate detection).
- Looks up Country, Tier, and Category selection IDs from the article hostname via a built-in domain map (with TLD-based fallback for unknown domains).
- Pre-fills a configurable `Campaign` column.

### Dashboard Widget
A **Recent Finds** widget on the Nextcloud Dashboard shows your latest keyword matches at a glance.

### Event History
Every match, error, and status change is recorded in a per-monitor event timeline. Entries show event type, timestamp, and either a snippet (with the matched keyword in bold) or the error message. History is retained for 100 days; a daily background job purges older entries.

### Repeat-match Detection (web pages)
For web-page monitors, WebTrack stores an MD5 hash of the 10 characters before and after the matched keyword (`lastFoundHash`). On the next check, if the keyword is still present **and the surrounding context hash is unchanged**, no new notification is sent — the page hasn't really changed. If the surrounding context changes (the keyword moved, or the surrounding text changed), a new notification fires.

There is no time-based notification cooldown. For RSS/Atom and YouTube sources, every new entry that matches generates one notification.

### Error Escalation
- Fetch failures are tracked per monitor.
- Status escalates from `error` (1–4 consecutive failures) to `failing` (≥ 5 consecutive failures).
- Notifications are sent on the **3rd** and **5th** consecutive error only (not on every failure).
- A successful check resets the consecutive-error counter and restores `ok`.

### CLI tools (`occ`)
- `occ webtrack:check` — run monitor checks now (`--monitor-id`, `--user`, `--debug`, `--dry-run`)
- `occ webtrack:test-tables` — dry-run a Tables row insertion (`--list-tables`, `--check-dup`, `--insert`)
- `occ webtrack:score-url` — score a URL against a monitor's boost/exclude rules with per-rule breakdown

---

## Screenshots

### Welcome Screen
![WebTrack welcome screen](screenshots/Screenshot%202026-04-09%20at%2022.35.17.png)

### Monitor Detail
![WebTrack monitor detail](screenshots/Screenshot%202026-04-09%20at%2022.35.48.png)

### Monitor Settings
![WebTrack monitor settings](screenshots/Screenshot%202026-04-09%20at%2022.36.06.png)

### Notification
![WebTrack notification](screenshots/Screenshot%202026-04-09%20at%2022.36.42.png)

### Talk Integration
![WebTrack Talk integration](screenshots/Screenshot%202026-04-09%20at%2022.37.07.png)

### Dashboard Widget
![WebTrack dashboard widget](screenshots/Screenshot%202026-04-10%20at%2000.12.29.png)

---

## Installation

1. Download or clone this repository into your Nextcloud `apps/` directory as `webtrack`.
2. Run `composer install` (no external runtime dependencies — only Nextcloud OCP APIs are used).
3. Build the frontend assets:
   ```bash
   npm install
   npm run build
   ```
4. Enable the app in **Nextcloud Apps → Tools → WebTrack**.

---

## Usage

1. Open WebTrack from the Nextcloud navigation bar.
2. Click **New monitor** in the sidebar.
3. Pick a source type (Custom / Google News / YouTube channel / YouTube search) and fill in the relevant fields:
   - **Name** — a label for the monitor
   - **URL** — the web page or feed URL (Custom only; auto-tested on entry)
   - **Channel ID** — the YouTube channel ID (YouTube channel only)
   - **Keyword** — the text or regex pattern to watch for
   - **Check interval** — how often to check
   - **Boost / exclude / score threshold** — optional relevance tuning
   - **Talk room** — optional, receive alerts in a Talk room
   - **Target table / Campaign** — optional, write matches to Nextcloud Tables
4. Save. WebTrack will start checking on the next background job run.

---

## Check Intervals

The intervals selectable in the UI are:

| Interval | Value (minutes) |
|----------|-----------------|
| 5 minutes | 5 |
| 15 minutes | 15 |
| 30 minutes | 30 |
| 1 hour | 60 |
| 2 hours | 120 |
| 6 hours | 360 |
| 12 hours | 720 |
| 24 hours | 1440 |

The server clamps the minimum interval to 5 minutes; lower values submitted via the API are raised to 5. The check background job itself runs every 5 minutes; per-monitor intervals are enforced individually based on each monitor's `lastCheckAt`.

---

## Monitor Statuses

| Status | Meaning |
|--------|---------|
| 🟢 `ok` | Healthy, keyword not currently present |
| 🔵 `found` | Keyword is currently detected |
| 🟠 `error` | Fetch failed (< 5 consecutive errors) |
| 🔴 `failing` | Fetch failing repeatedly (≥ 5 errors) |
| ⚪ `paused` | Manually paused |

---

## Security

- Only `http` and `https` URL schemes are permitted.
- The fetch host is validated against Nextcloud's `IRemoteHostValidator` for the initial URL **and on every redirect hop**, so user-supplied URLs cannot be used to probe loopback / link-local / private-network targets through the Nextcloud server (SSRF protection). Admins who explicitly set `allow_local_remote_servers=true` opt out, and the validator honours that flag.
- Feed parsing uses `LIBXML_NONET` to prevent XXE.
- All API endpoints require authentication. Monitors are scoped per user — users can only see and modify their own monitors.
- HTML snippet markers (`**bold**`) are entity-escaped before rendering as `<strong>` in the frontend (XSS-safe).
- Regex patterns are validated before saving.

---

## Requirements

- Nextcloud 28 – 34
- PHP (standard Nextcloud requirements)
- Nextcloud Talk (`spreed` app) — optional, required for Talk notifications
- Nextcloud Tables — optional, required for the Tables integration
- A YouTube Data API v3 key — optional, required for the **YouTube search** source type

---

## License

[AGPL v3](https://www.gnu.org/licenses/agpl-3.0.html) — © Frank Karlitschek
