# WebTrack

**Monitor websites and RSS/Atom feeds for keywords — get notified when they appear.**

WebTrack is a [Nextcloud](https://nextcloud.com) app that watches web pages and RSS/Atom feeds for specific keywords or regex patterns. When a match is detected, you receive a Nextcloud notification and, optionally, a message in a Nextcloud Talk room.

![WebTrack welcome screen](screenshots/Screenshot%202026-04-09%20at%2022.35.17.png)

---

## Features

### Web Page Monitoring
Watch any public web page for a keyword or phrase. WebTrack fetches the page, strips HTML, and searches the plain text — so you only match visible content, not markup.

### RSS / Atom Feed Monitoring
Track news feeds, blogs, and any RSS or Atom source. WebTrack only notifies you about **new entries** (already-seen items are remembered), so you won't get spammed when you first add a feed.

### Keyword & Regex Search
- Plain text search is case-insensitive by default.
- Enable the **Use Regex** toggle for advanced patterns (PCRE, auto-wrapped with `/.../iu` flags if no delimiters are provided).

### Nextcloud Notifications
Receive native Nextcloud notifications when a keyword is found. Notifications link directly to the monitor detail page.

### Nextcloud Talk Integration
Optionally post an alert to a Talk room when a keyword match is detected:
> 🔔 WebTrack: keyword "Linux" found on Heise Linux — *…snippet with context…*

Set a per-monitor Talk room, or configure a global default in Settings.

### Dashboard Widget
A **Recent Finds** widget on the Nextcloud Dashboard shows your latest keyword matches at a glance.

### Event History
Every match, error, and status change is recorded in a per-monitor event timeline. Entries show the event type, timestamp, and a snippet with the matched keyword highlighted. History is retained for 100 days.

### Smart Re-notification Cooldown
Once a keyword is found, re-notifications are suppressed for `checkInterval × 3` minutes — so persistently present keywords don't flood your inbox.

### Error Escalation
- Fetch failures are tracked per monitor.
- Status escalates from `error` (1–4 failures) to `failing` (≥ 5 failures).
- Notifications are sent at the 3rd and 5th consecutive error only.

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
3. Fill in:
   - **Name** — a label for the monitor
   - **URL** — the web page or feed URL (auto-tested on entry)
   - **Keyword** — the text or regex pattern to watch for
   - **Check interval** — how often to check (1 min – 24 hours)
   - **Talk room** *(optional)* — receive alerts in a Talk room
4. Save. WebTrack will start checking on the next background job run.

---

## Check Intervals

| Interval | Value |
|----------|-------|
| 1 minute | 1 min |
| 5 minutes | 5 min |
| 15 minutes | 15 min |
| 30 minutes | 30 min |
| 1 hour | 60 min |
| 2 hours | 120 min |
| 6 hours | 360 min |
| 12 hours | 720 min |
| 24 hours | 1440 min |

> The background job runs every 10 minutes. Per-monitor intervals are enforced individually.

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

## Requirements

- Nextcloud 28 – 34
- PHP (standard Nextcloud requirements)
- Nextcloud Talk (`spreed` app) — optional, required for Talk notifications

---

## License

[AGPL v3](https://www.gnu.org/licenses/agpl-3.0.html) — © Frank Karlitschek
