<template>
    <div>
        <div v-if="loading" style="text-align:center; padding:20px;">
            <NcLoadingIcon :size="24" />
        </div>

        <p v-else-if="rows.length === 0" class="wn-history-empty">
            {{ t('webtrack', 'No events recorded yet.') }}
        </p>

        <template v-else>
            <div class="wn-timeline">
                <div v-for="row in rows" :key="row.id" class="wn-timeline-item">
                    <div class="wn-timeline-dot" :class="'wn-timeline-dot--' + row.event" />
                    <div class="wn-timeline-content">
                        <div class="wn-timeline-header">
                            <span class="wn-status-badge" :class="eventClass(row.event)">
                                {{ eventLabel(row.event) }}
                            </span>
                            <span class="wn-timeline-time">{{ formatDate(row.createdAt) }}</span>
                        </div>
                        <p v-if="row.snippet" class="wn-snippet" v-html="renderSnippet(row.snippet)" />
                        <p v-else-if="row.errorMsg" class="wn-error-text">{{ row.errorMsg }}</p>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-top:10px;">
                <NcButton v-if="page > 0" type="tertiary" @click="prevPage">
                    ← {{ t('webtrack', 'Previous') }}
                </NcButton>
                <NcButton v-if="rows.length === 50" type="tertiary" @click="nextPage">
                    {{ t('webtrack', 'Next') }} →
                </NcButton>
            </div>
        </template>
    </div>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { getLocale } from '@nextcloud/l10n'
import * as api from '../services/api.js'

export default {
    name: 'HistoryTable',
    components: { NcButton, NcLoadingIcon },
    props: {
        monitorId: { type: Number, required: true },
    },

    data() {
        return {
            rows:    [],
            page:    0,
            loading: true,
        }
    },

    watch: {
        monitorId() {
            this.page = 0
            this.load()
        },
    },

    async created() {
        await this.load()
    },

    methods: {
        async load() {
            this.loading = true
            try {
                const resp = await api.getHistory(this.monitorId, this.page)
                this.rows = resp.data
            } catch (e) {
                showError(this.t('webtrack', 'Failed to load history'))
            } finally {
                this.loading = false
            }
        },

        async nextPage() { this.page++; await this.load() },
        async prevPage() { this.page = Math.max(0, this.page - 1); await this.load() },

        eventClass(event) {
            const map = {
                found:   'wn-status-badge--found',
                error:   'wn-status-badge--error',
                failing: 'wn-status-badge--failing',
                paused:  'wn-status-badge--paused',
                resumed: 'wn-status-badge--ok',
                ok:      'wn-status-badge--ok',
            }
            return map[event] || ''
        },

        eventLabel(event) {
            const map = {
                found:   this.t('webtrack', 'Found'),
                error:   this.t('webtrack', 'Error'),
                failing: this.t('webtrack', 'Failing'),
                paused:  this.t('webtrack', 'Paused'),
                resumed: this.t('webtrack', 'Resumed'),
                ok:      this.t('webtrack', 'OK'),
            }
            return map[event] || event
        },

        /**
         * Converts **bold** markers to <strong> tags and escapes everything else.
         */
        renderSnippet(text) {
            const escaped = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;')
            return escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        },

        formatDate(iso) {
            if (!iso) return ''
            const locale = getLocale().replace(/_/g, '-')
            return new Date(iso).toLocaleString(locale, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            })
        },
    },
}
</script>
