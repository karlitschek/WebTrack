<template>
    <NcModal :name="title" @close="$emit('close')">
        <div class="wn-form-container">
            <h2 class="wn-form-title">{{ title }}</h2>

            <!-- ── Basic info ── -->
            <fieldset class="wn-form-section">

                <div class="wn-form-row">
                    <label for="wn-name">{{ t('webtrack', 'Name') }} *</label>
                    <input id="wn-name" v-model.trim="form.name" type="text"
                        :placeholder="t('webtrack', 'My monitor')" @keydown.enter.prevent />
                </div>

                <div class="wn-form-row">
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

            <!-- ── Source type ── -->
            <fieldset class="wn-form-section">
                <legend>{{ t('webtrack', 'Source type') }}</legend>

                <div class="wn-form-row">
                    <label for="wn-source-type">{{ t('webtrack', 'Source') }}</label>
                    <select id="wn-source-type" v-model="form.sourceType">
                        <option value="custom">{{ t('webtrack', 'Custom URL') }}</option>
                        <option value="google_news">{{ t('webtrack', 'Google News RSS') }}</option>
                        <option value="youtube">{{ t('webtrack', 'YouTube') }}</option>
                    </select>
                </div>

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
                    <span class="wn-form-hint">
                        {{ t('webtrack', 'Used as hl/gl parameters for the Google News feed URL') }}
                    </span>
                </div>
            </fieldset>

            <!-- ── Keyword matching ── -->
            <fieldset class="wn-form-section">
                <legend>{{ t('webtrack', 'Matching') }}</legend>

                <div class="wn-form-row">
                    <label for="wn-keyword">{{ t('webtrack', 'Keyword') }} *</label>
                    <input id="wn-keyword" v-model.trim="form.keyword" type="text"
                        :placeholder="form.useRegex ? t('webtrack', 'breaking\\s+news|alert') : t('webtrack', 'breaking news')" @keydown.enter.prevent />
                </div>

                <div class="wn-form-row wn-form-checkbox">
                    <input id="wn-regex" v-model="form.useRegex" type="checkbox" />
                    <label for="wn-regex">{{ t('webtrack', 'Use regular expression') }}</label>
                </div>

                <!-- Relevance scoring — shown for feed sources -->
                <template v-if="form.sourceType !== 'custom'">
                    <div class="wn-form-row">
                        <label for="wn-score">{{ t('webtrack', 'Minimum relevance score') }}</label>
                        <input id="wn-score" v-model.number="form.scoreThreshold" type="number"
                            min="0" max="20" step="1" style="width:5em" />
                        <span class="wn-form-hint">
                            {{ t('webtrack', 'Each matching boost keyword adds +1; each exclude pattern subtracts 2.') }}
                        </span>
                    </div>

                    <div class="wn-form-row">
                        <label for="wn-boost">{{ t('webtrack', 'Boost keywords') }}</label>
                        <input id="wn-boost" v-model="boostKeywordsRaw" type="text"
                            :placeholder="t('webtrack', 'nextcloud, open source, privacy')"
                            @keydown.enter.prevent />
                        <span class="wn-form-hint">{{ t('webtrack', 'Comma-separated. Each match raises the score by 1.') }}</span>
                    </div>

                    <div class="wn-form-row">
                        <label for="wn-exclude">{{ t('webtrack', 'Exclude patterns') }}</label>
                        <input id="wn-exclude" v-model="excludePatternsRaw" type="text"
                            :placeholder="t('webtrack', 'reddit, forum, stackoverflow')"
                            @keydown.enter.prevent />
                        <span class="wn-form-hint">{{ t('webtrack', 'Comma-separated URL/title substrings. Each match lowers the score by 2.') }}</span>
                    </div>
                </template>
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
        monitor:   { type: Object, default: null },
        talkRooms: { type: Array, default: () => [] },
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
                sourceType:       m ? (m.sourceType    || 'custom') : 'custom',
                sourceLanguage:   m ? (m.sourceLanguage || 'en-US') : 'en-US',
                // Relevance scoring
                scoreThreshold:   m ? (m.scoreThreshold ?? 2) : 2,
                // Tables integration
                tablesTableId:    m ? (m.tablesTableId    || null) : null,
                tablesCampaignId: m ? (m.tablesCampaignId || null) : null,
            },
            // Boost/exclude stored as comma-separated strings in the UI
            boostKeywordsRaw:   m && m.boostKeywords   ? m.boostKeywords.join(', ')   : '',
            excludePatternsRaw: m && m.excludePatterns ? m.excludePatterns.join(', ') : 'reddit, forum, stackoverflow',

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
            if (!this.form.url)     errs.push(this.t('webtrack', 'URL is required'))
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
