<template>
    <NcModal :name="title" @close="$emit('close')">
        <div class="wn-form-container">
            <h2 class="wn-form-title">{{ title }}</h2>

            <!-- ── Source type — always first ── -->
            <fieldset class="wn-form-section">
                <legend>{{ t('webtrack', 'Source') }}</legend>

                <div class="wn-form-row">
                    <label for="wn-source-type">{{ t('webtrack', 'Source type') }}</label>
                    <select id="wn-source-type" v-model="form.sourceType" @change="onSourceTypeChange">
                        <option value="google_news">{{ t('webtrack', 'Google News RSS') }}</option>
                        <option value="youtube_search">{{ t('webtrack', 'YouTube — search all') }}</option>
                        <option value="youtube">{{ t('webtrack', 'YouTube — single channel') }}</option>
                        <option value="custom">{{ t('webtrack', 'Custom URL') }}</option>
                    </select>
                </div>

                <!-- Language/region — Google News only -->
                <div v-if="form.sourceType === 'google_news'" class="wn-form-row">
                    <label for="wn-source-lang">{{ t('webtrack', 'Language / region') }}</label>
                    <select id="wn-source-lang" v-model="form.sourceLanguage">
                        <option value="en-US">English – US</option>
                        <option value="en-GB">English – UK</option>
                        <option value="de-DE">Deutsch – DE</option>
                        <option value="de-AT">Deutsch – AT</option>
                        <option value="de-CH">Deutsch – CH</option>
                        <option value="fr-FR">Français – FR</option>
                        <option value="nl-NL">Nederlands – NL</option>
                        <option value="es-ES">Español – ES</option>
                        <option value="it-IT">Italiano – IT</option>
                        <option value="sv-SE">Svenska – SE</option>
                        <option value="da-DK">Dansk – DK</option>
                        <option value="nb-NO">Norsk – NO</option>
                        <option value="fi-FI">Suomi – FI</option>
                        <option value="ja-JP">日本語 – JP</option>
                    </select>
                </div>

                <!-- YouTube search — API key warning -->
                <div v-if="form.sourceType === 'youtube_search'" class="wn-form-row">
                    <p v-if="!youtubeApiKeySet" class="wn-error-text">
                        {{ t('webtrack', 'A YouTube Data API v3 key is required. Add it in Settings below.') }}
                    </p>
                    <p v-else class="wn-form-hint wn-form-hint--success">
                        {{ t('webtrack', '✓ YouTube API key configured') }}
                    </p>
                </div>

                <!-- Channel ID — single-channel YouTube only -->
                <div v-if="form.sourceType === 'youtube'" class="wn-form-row">
                    <label for="wn-yt-channel">{{ t('webtrack', 'Channel ID') }} *</label>
                    <input id="wn-yt-channel" v-model.trim="form.youtubeChannelId" type="text"
                        placeholder="UCxxxxxxxxxxxxxxxxxxxxxx"
                        @keydown.enter.prevent />
                    <span class="wn-form-hint">
                        {{ t('webtrack', 'The YouTube channel ID (starts with UC…). Find it in the channel URL.') }}
                    </span>
                </div>

                <!-- Custom URL — only for custom source type -->
                <div v-if="form.sourceType === 'custom'" class="wn-form-row">
                    <label for="wn-url">{{ t('webtrack', 'URL') }} *</label>
                    <input id="wn-url" v-model.trim="form.url" type="url"
                        :placeholder="t('webtrack', 'https://example.com/feed.rss')"
                        @blur="onUrlBlur" @keydown.enter.prevent />
                    <span v-if="urlTesting" class="wn-form-hint">
                        {{ t('webtrack', 'Testing URL…') }}
                        <span class="wn-inline-spinner" />
                    </span>
                    <span v-else-if="urlTestResult" class="wn-form-hint wn-form-hint--success">
                        {{ urlTestResult.isFeed
                            ? t('webtrack', '✓ RSS/Atom feed detected')
                            : t('webtrack', '✓ Page reachable') }}
                    </span>
                    <span v-else-if="urlTestError" class="wn-error-text">{{ urlTestError }}</span>
                    <div v-if="urlTestResult && urlTestResult.preview" class="wn-preview-box">{{ urlTestResult.preview }}</div>
                </div>
            </fieldset>

            <!-- ── Basic info ── -->
            <fieldset class="wn-form-section">
                <div class="wn-form-row">
                    <label for="wn-name">{{ t('webtrack', 'Name') }} *</label>
                    <input id="wn-name" v-model.trim="form.name" type="text"
                        :placeholder="t('webtrack', 'My monitor')" @keydown.enter.prevent />
                </div>
            </fieldset>

            <!-- ── Keyword matching ── -->
            <fieldset class="wn-form-section">
                <legend>{{ t('webtrack', 'Keywords') }}</legend>

                <div class="wn-form-row">
                    <label for="wn-keyword">
                        {{ isAutoUrl
                            ? t('webtrack', 'Keyword to detect') + ' *'
                            : t('webtrack', 'Keyword') + ' *' }}
                    </label>
                    <input id="wn-keyword" v-model.trim="form.keyword" type="text"
                        :placeholder="form.useRegex
                            ? t('webtrack', 'breaking\\s+news|alert')
                            : t('webtrack', 'Nextcloud')"
                        @keydown.enter.prevent />
                    <span v-if="isAutoUrl" class="wn-form-hint">
                        {{ form.sourceType === 'google_news'
                            ? t('webtrack', 'Also used as the main search term in the Google News URL.')
                            : t('webtrack', 'Matched against video titles from the channel.') }}
                    </span>
                </div>

                <!-- Regex toggle — custom URL only (auto-URL sources match titles literally) -->
                <div v-if="!isAutoUrl" class="wn-form-row wn-form-checkbox">
                    <input id="wn-regex" v-model="form.useRegex" type="checkbox" />
                    <label for="wn-regex">{{ t('webtrack', 'Use regular expression') }}</label>
                </div>

                <!-- Positive keywords — Google News: extra search terms; custom: boost scoring -->
                <div v-if="form.sourceType !== 'youtube'" class="wn-form-row">
                    <label for="wn-boost">{{ t('webtrack', 'Positive keywords') }}</label>
                    <input id="wn-boost" v-model="boostKeywordsRaw" type="text"
                        :placeholder="t('webtrack', 'open source, privacy, self-hosted')"
                        @keydown.enter.prevent />
                    <span class="wn-form-hint">
                        {{ form.sourceType === 'google_news'
                            ? t('webtrack', 'Comma-separated. Added as extra search terms in the Google News URL.')
                            : t('webtrack', 'Comma-separated. Each match raises the relevance score by 1.') }}
                    </span>
                </div>

                <!-- Negative keywords — Google News: excluded from URL; others: filter items -->
                <div class="wn-form-row">
                    <label for="wn-exclude">{{ t('webtrack', 'Negative keywords') }}</label>
                    <input id="wn-exclude" v-model="excludePatternsRaw" type="text"
                        :placeholder="t('webtrack', 'shorts, live, reaction')"
                        @keydown.enter.prevent />
                    <span class="wn-form-hint">
                        {{ form.sourceType === 'google_news'
                            ? t('webtrack', 'Comma-separated. Excluded from the Google News search (prepended with -).')
                            : t('webtrack', 'Comma-separated. Items whose title or URL contains these are skipped.') }}
                    </span>
                </div>

                <!-- Relevance score — custom URL only -->
                <div v-if="!isAutoUrl" class="wn-form-row">
                    <label for="wn-score">{{ t('webtrack', 'Minimum relevance score') }}</label>
                    <input id="wn-score" v-model.number="form.scoreThreshold" type="number"
                        min="0" max="20" step="1" style="width:5em" />
                    <span class="wn-form-hint">
                        {{ t('webtrack', 'Each positive keyword match adds +1; each negative keyword match subtracts 2.') }}
                    </span>
                </div>

                <!-- Generated feed URL preview -->
                <div v-if="feedPreviewUrl" class="wn-form-row">
                    <label>{{ t('webtrack', 'Generated feed URL') }}</label>
                    <div class="wn-preview-box wn-preview-url">{{ feedPreviewUrl }}</div>
                </div>
            </fieldset>

            <!-- ── Schedule & notifications ── -->
            <fieldset class="wn-form-section">
                <legend>{{ t('webtrack', 'Schedule & notifications') }}</legend>

                <div class="wn-form-row">
                    <label for="wn-interval">{{ t('webtrack', 'Check interval') }}</label>
                    <select id="wn-interval" v-model.number="form.checkInterval">
                        <option :value="5">5 {{ t('webtrack', 'minutes') }}</option>
                        <option :value="15">15 {{ t('webtrack', 'minutes') }}</option>
                        <option :value="30">30 {{ t('webtrack', 'minutes') }}</option>
                        <option :value="60">60 {{ t('webtrack', 'minutes') }}</option>
                        <option :value="120">2 {{ t('webtrack', 'hours') }}</option>
                        <option :value="360">6 {{ t('webtrack', 'hours') }}</option>
                        <option :value="720">12 {{ t('webtrack', 'hours') }}</option>
                        <option :value="1440">24 {{ t('webtrack', 'hours') }}</option>
                    </select>
                </div>

                <div v-if="talkRooms.length > 0" class="wn-form-row">
                    <label for="wn-talk">{{ t('webtrack', 'Talk room (optional)') }}</label>
                    <select id="wn-talk" v-model="form.talkRoomToken">
                        <option value="">{{ t('webtrack', '— none —') }}</option>
                        <option v-for="room in talkRooms" :key="room.token" :value="room.token">
                            {{ room.name }}
                        </option>
                    </select>
                </div>
            </fieldset>

            <!-- ── Tables integration ── -->
            <fieldset class="wn-form-section">
                <legend>{{ t('webtrack', 'Write to Nextcloud Tables (optional)') }}</legend>

                <div v-if="tablesLoading" class="wn-form-hint">
                    <span class="wn-inline-spinner" /> {{ t('webtrack', 'Loading tables…') }}
                </div>

                <template v-else-if="tables.length > 0">
                    <div class="wn-form-row">
                        <label for="wn-table">{{ t('webtrack', 'Target table') }}</label>
                        <select id="wn-table" v-model.number="form.tablesTableId" @change="onTableChange">
                            <option :value="null">{{ t('webtrack', '— none —') }}</option>
                            <option v-for="tbl in tables" :key="tbl.id" :value="tbl.id">
                                {{ tbl.emoji ? tbl.emoji + ' ' : '' }}{{ tbl.title }}
                            </option>
                        </select>
                    </div>

                    <!-- Campaign column picker — shown when a table is selected and it has a Campaign column -->
                    <div v-if="form.tablesTableId && campaignOptions.length > 0" class="wn-form-row">
                        <label for="wn-campaign">{{ t('webtrack', 'Campaign (pre-fill)') }}</label>
                        <select id="wn-campaign" v-model.number="form.tablesCampaignId">
                            <option :value="null">{{ t('webtrack', '— none —') }}</option>
                            <option v-for="opt in campaignOptions" :key="opt.id" :value="opt.id">
                                {{ opt.label }}
                            </option>
                        </select>
                        <span class="wn-form-hint">{{ t('webtrack', 'Automatically set in every new row.') }}</span>
                    </div>
                </template>

                <p v-else class="wn-form-hint">
                    {{ t('webtrack', 'Install the Nextcloud Tables app to enable automatic row insertion.') }}
                </p>
            </fieldset>

            <ul v-if="errors.length" class="wn-form-errors">
                <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
            </ul>

            <div class="wn-form-actions">
                <NcButton type="tertiary" @click="$emit('close')">
                    {{ t('webtrack', 'Cancel') }}
                </NcButton>
                <NcButton type="primary" :disabled="saving" @click="save">
                    <template v-if="saving">
                        <span class="wn-inline-spinner" /> {{ t('webtrack', 'Saving…') }}
                    </template>
                    <template v-else>{{ t('webtrack', 'Save') }}</template>
                </NcButton>
            </div>
        </div>
    </NcModal>
</template>

<script>
import { NcModal, NcButton } from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import * as api from '../services/api.js'

export default {
    name: 'MonitorForm',
    components: { NcModal, NcButton },
    props: {
        monitor:          { type: Object,  default: null },
        talkRooms:        { type: Array,   default: () => [] },
        youtubeApiKeySet: { type: Boolean, default: false },
    },
    emits: ['saved', 'close'],

    data() {
        const m = this.monitor
        return {
            form: {
                name:             m ? m.name             : '',
                url:              m ? m.url              : '',
                keyword:          m ? m.keyword          : '',
                useRegex:         m ? !!m.useRegex        : false,
                checkInterval:    m ? m.checkInterval    : 60,
                isFeed:           m ? m.isFeed           : false,
                talkRoomToken:    m ? (m.talkRoomToken || '') : '',
                // Source configuration
                sourceType:       m ? (m.sourceType    || 'google_news') : 'google_news',
                sourceLanguage:   m ? (m.sourceLanguage || 'en-US') : 'en-US',
                // YouTube: channel ID extracted from stored URL on edit
                youtubeChannelId: m ? this.extractYouTubeChannelId(m.url) : '',
                // Relevance scoring — only meaningful for custom URL monitors
                scoreThreshold:   m ? (m.scoreThreshold ?? 2) : 2,
                // Tables integration
                tablesTableId:    m ? (m.tablesTableId    || null) : null,
                tablesCampaignId: m ? (m.tablesCampaignId || null) : null,
            },
            // Boost/exclude stored as comma-separated strings in the UI
            boostKeywordsRaw:   m && m.boostKeywords   ? m.boostKeywords.join(', ')   : '',
            excludePatternsRaw: m && m.excludePatterns ? m.excludePatterns.join(', ') : '',

            errors:        [],
            saving:        false,
            urlTesting:    false,
            urlTestResult: null,
            urlTestError:  null,

            // Tables data
            tables:         [],
            tablesLoading:  true,
            tableColumns:   [],   // columns of the currently-selected table
        }
    },

    computed: {
        title() {
            return this.monitor
                ? this.t('webtrack', 'Edit monitor')
                : this.t('webtrack', 'New monitor')
        },
        isEdit() {
            return !!this.monitor
        },
        /** Selection options from the Campaign column of the selected table, if present. */
        campaignOptions() {
            const col = this.tableColumns.find(c => c.title.toLowerCase().includes('campaign'))
            return col?.selectionOptions ?? []
        },
        /** True for source types where the URL is auto-built (no manual URL field). */
        isAutoUrl() {
            return ['google_news', 'youtube', 'youtube_search'].includes(this.form.sourceType)
        },
        /** Live preview of the auto-built feed URL (Google News or YouTube). */
        feedPreviewUrl() {
            const type = this.form.sourceType
            if (type === 'google_news') {
                const terms = []
                const kw = this.form.keyword.trim()
                if (kw) terms.push(kw)
                this.splitRaw(this.boostKeywordsRaw).forEach(k => { if (k) terms.push(k) })
                this.splitRaw(this.excludePatternsRaw).forEach(k => { if (k) terms.push('-' + k) })
                if (!terms.length) return ''
                const q    = terms.map(encodeURIComponent).join('+')
                const lang = this.form.sourceLanguage || 'en-US'
                const parts = lang.split('-')
                const hl   = lang
                const gl   = (parts[1] || parts[0]).toUpperCase()
                const ceid = gl + ':' + parts[0].toLowerCase()
                return `https://news.google.com/rss/search?q=${q}&hl=${hl}&gl=${gl}&ceid=${ceid}`
            }
            if (type === 'youtube') {
                const ch = this.form.youtubeChannelId.trim()
                if (!ch) return ''
                return `https://www.youtube.com/feeds/videos.xml?channel_id=${encodeURIComponent(ch)}`
            }
            if (type === 'youtube_search') {
                const kw = this.form.keyword.trim()
                if (!kw) return ''
                return `YouTube Data API v3 — search: "${kw}"`
            }
            return ''
        },
    },

    async created() {
        await this.loadTables()
        if (this.form.tablesTableId) {
            await this.loadTableColumns(this.form.tablesTableId)
        }
    },

    methods: {
        async loadTables() {
            this.tablesLoading = true
            try {
                const resp = await api.getTables()
                this.tables = resp.data
            } catch {
                this.tables = []
            } finally {
                this.tablesLoading = false
            }
        },

        async loadTableColumns(tableId) {
            if (!tableId) { this.tableColumns = []; return }
            try {
                const resp = await api.getTableColumns(tableId)
                this.tableColumns = resp.data
            } catch {
                this.tableColumns = []
            }
        },

        async onTableChange() {
            this.form.tablesCampaignId = null
            await this.loadTableColumns(this.form.tablesTableId)
        },

        /** Extract the channel_id query param from a YouTube feed URL (used when editing). */
        extractYouTubeChannelId(url) {
            if (!url) return ''
            try {
                return new URL(url).searchParams.get('channel_id') || ''
            } catch { return '' }
        },

        onSourceTypeChange() {
            // Clear URL test state when switching source types
            this.urlTestResult = null
            this.urlTestError  = null
        },

        async onUrlBlur() {
            const url = this.form.url
            if (!url || !url.startsWith('http')) return
            this.urlTesting    = true
            this.urlTestResult = null
            this.urlTestError  = null
            try {
                const resp = await api.testUrl(url)
                if (resp.data.ok) {
                    this.urlTestResult = resp.data
                    this.form.isFeed = !!resp.data.isFeed
                } else {
                    this.urlTestError = resp.data.error || this.t('webtrack', 'URL unreachable')
                }
            } catch (e) {
                this.urlTestError = this.t('webtrack', 'Could not reach URL')
            } finally {
                this.urlTesting = false
            }
        },

        validate() {
            const errs = []
            if (!this.form.name)    errs.push(this.t('webtrack', 'Name is required'))
            if (this.form.sourceType === 'custom' && !this.form.url) {
                errs.push(this.t('webtrack', 'URL is required'))
            }
            if (this.form.sourceType === 'youtube' && !this.form.youtubeChannelId) {
                errs.push(this.t('webtrack', 'Channel ID is required'))
            }
            // youtube_search: keyword is the search query, no URL or channel ID needed
            if (!this.form.keyword) errs.push(this.t('webtrack', 'Keyword is required'))
            return errs
        },

        /** Split a raw comma-separated string into a clean array of strings. */
        splitRaw(raw) {
            return raw.split(',')
                .map(s => s.trim())
                .filter(Boolean)
        },

        async save() {
            this.errors = this.validate()
            if (this.errors.length) return

            this.saving = true
            try {
                const payload = {
                    ...this.form,
                    boostKeywords:   this.splitRaw(this.boostKeywordsRaw),
                    excludePatterns: this.splitRaw(this.excludePatternsRaw),
                }
                if (payload.talkRoomToken === '') payload.talkRoomToken = null

                let resp
                if (this.isEdit) {
                    resp = await api.updateMonitor(this.monitor.id, payload)
                } else {
                    resp = await api.createMonitor(payload)
                }
                showSuccess(this.t('webtrack', 'Monitor saved'))
                this.$emit('saved', resp.data)
            } catch (e) {
                const msg = e.response?.data?.errors?.join(', ')
                    || this.t('webtrack', 'Failed to save monitor')
                showError(msg)
            } finally {
                this.saving = false
            }
        },
    },
}
</script>

<style scoped>
/* URL preview in Google News mode — allow long URLs to wrap */
.wn-preview-url {
    word-break: break-all;
    font-family: monospace;
    font-size: 0.8em;
    max-height: none;
}
</style>
