# Changelog

All notable changes to WebTrack are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Changed
- Upgrade frontend from Vue 2.7 to Vue 3 and `@nextcloud/vue` 8 → 9
- Replace `vue-router` 3 with `vue-router` 4 (hash-history mode preserved)
- Replace `$root.$on`/`$root.$emit` global event bus with `@nextcloud/event-bus`
- Update all `@nextcloud/vue` component imports to use named exports from the
  package root (`import { NcButton } from '@nextcloud/vue'`)
- Bump `@nextcloud/vite-config` 1.x → 2.x
- Add `"type": "module"` to `package.json`
