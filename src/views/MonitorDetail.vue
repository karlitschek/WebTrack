<template>
    <div class="wn-page-content">
        <div v-if="loading" style="text-align:center; padding: 60px;">
            <NcLoadingIcon :size="32" />
        </div>

        <template v-else-if="monitor">
            <!-- Header with accent bar -->
            <div class="wn-detail-card" :class="'wn-detail-card--' + monitor.status">
                <div class="wn-detail-header">
                    <div class="wn-detail-title-row">
                        <h2>{{ monitor.name }}</h2>
                        <StatusBadge :status="monitor.status" />
                    </div>
                    <p class="wn-detail-url">
                        <span class="wn-detail-icon"><LinkVariant :size="20" /></span>
                        <a :href="monitor.url" target="_blank" rel="noopener noreferrer">{{ monitor.url }}</a>
                    </p>
                    <div class="wn-detail-actions">
                        <NcButton type="secondary" @click="openEdit">
                            <template #icon><span class="icon-rename" /></template>
                            {{ t('webtrack', 'Edit') }}
                        </NcButton>
                        <NcButton type="secondary" :disabled="checking" @click="runCheckNow">
                            <template #icon>
                                <NcLoadingIcon v-if="checking" :size="20" />
                                <Refresh v-else :size="20" />
                            </template>
                            {{ checking ? t('webtrack', 'Checking…') : t('webtrack', 'Check now') }}
                        </NcButton>
                        <NcButton :type="monitor.isActive ? 'tertiary' : 'primary'" @click="togglePause">
                            <template #icon>
                                <span :class="monitor.isActive ? 'icon-pause' : 'icon-play'" />
                            </template>
                            {{ monitor.isActive ? t('webtrack', 'Pause') : t('webtrack', 'Resume') }}
                        </NcButton>
                    </div>
                </div>
            </div>

            <!-- Info cards grid -->
            <div class="wn-info-grid">
                <div class="wn-info-card">
                    <span class="wn-info-card-icon"><Magnify :size="24" /></span>
                    <div class="wn-info-card-body">
                        <span class="wn-info-card-label">{{ t('webtrack', 'Keyword') }}</span>
                        <span class="wn-info-card-value">
                            <code v-if="monitor.useRegex">{{ monitor.keyword }}</code>
                            <template v-else>{{ monitor.keyword }}</template>
                            <span v-if="monitor.useRegex" class="wn-regex-badge">{{ t('webtrack', 'regex') }}</span>
                        </span>
                    </div>
                </div>

                <div class="wn-info-card">
                    <span class="wn-info-card-icon"><TimerOutline :size="24" /></span>
                    <div class="wn-info-card-body">
                        <span class="wn-info-card-label">{{ t('webtrack', 'Check interval') }}</span>
                        <span class="wn-info-card-value">{{ formatInterval(monitor.checkInterval) }}</span>
                    </div>
                </div>

                <div class="wn-info-card">
                    <span class="wn-info-card-icon">
                        <Rss v-if="monitor.isFeed" :size="24" />
                        <Web v-else :size="24" />
                    </span>
                    <div class="wn-info-card-body">
                        <span class="wn-info-card-label">{{ t('webtrack', 'Type') }}</span>
                        <span class="wn-info-card-value">{{ monitor.isFeed ? t('webtrack', 'RSS / Atom feed') : t('webtrack', 'Web page') }}</span>
                    </div>
                </div>

                <div class="wn-info-card">
                    <span class="wn-info-card-icon"><ClockOutline :size="24" /></span>
                    <div class="wn-info-card-body">
                        <span class="wn-info-card-label">{{ t('webtrack', 'Last checked') }}</span>
                        <span class="wn-info-card-value">{{ formatDate(monitor.lastCheckAt) }}</span>
                    </div>
                </div>

                <div v-if="monitor.lastFoundAt" class="wn-info-card wn-info-card--highlight">
                    <span class="wn-info-card-icon"><CheckCircleOutline :size="24" /></span>
                    <div class="wn-info-card-body">
                        <span class="wn-info-card-label">{{ t('webtrack', 'Keyword last seen') }}</span>
                        <span class="wn-info-card-value">{{ formatDate(monitor.lastFoundAt) }}</span>
                    </div>
                </div>

                <div v-if="monitor.lastErrorMsg" class="wn-info-card wn-info-card--error">
                    <span class="wn-info-card-icon"><AlertOutline :size="24" /></span>
                    <div class="wn-info-card-body">
                        <span class="wn-info-card-label">{{ t('webtrack', 'Last error') }}</span>
                        <span class="wn-info-card-value">{{ monitor.lastErrorMsg }}</span>
                    </div>
                </div>
            </div>

            <h3 class="wn-section-heading">{{ t('webtrack', 'Event history') }}</h3>
            <HistoryTable ref="history" :monitor-id="monitor.id" />
        </template>

        <NcEmptyContent v-else
            :name="t('webtrack', 'Monitor not found')"
            description="">
            <template #icon><span class="icon-link" /></template>
        </NcEmptyContent>

        <MonitorForm v-if="formOpen"
            :monitor="editingMonitor"
            :talk-rooms="talkRooms"
            @saved="onSaved"
            @close="formOpen = false" />
    </div>
</template>

<script>
import {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
} from '@nextcloud/vue'
import { emit } from '@nextcloud/event-bus'
import Magnify            from 'vue-material-design-icons/Magnify.vue'
import TimerOutline       from 'vue-material-design-icons/TimerOutline.vue'
import Web                from 'vue-material-design-icons/Web.vue'
import Rss                from 'vue-material-design-icons/Rss.vue'
import ClockOutline       from 'vue-material-design-icons/ClockOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import AlertOutline       from 'vue-material-design-icons/AlertOutline.vue'
import LinkVariant        from 'vue-material-design-icons/LinkVariant.vue'
import Refresh            from 'vue-material-design-icons/Refresh.vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { getLocale } from '@nextcloud/l10n'
import StatusBadge  from '../components/StatusBadge.vue'
import HistoryTable from '../components/HistoryTable.vue'
import MonitorForm  from '../components/MonitorForm.vue'
import * as api from '../services/api.js'

export default {
    name: 'MonitorDetail',
    components: {
        NcButton,
        NcEmptyContent,
        NcLoadingIcon,
        StatusBadge,
        HistoryTable,
        MonitorForm,
        Magnify,
        TimerOutline,
        Web,
        Rss,
        ClockOutline,
        CheckCircleOutline,
        AlertOutline,
        LinkVariant,
        Refresh,
    },
    props: {
        id: { type: String, required: true },
    },

    data() {
        return {
            monitor:        null,
            talkRooms:      [],
            loading:        true,
            checking:       false,
            formOpen:       false,
            editingMonitor: null,
        }
    },

    watch: {
        id() { this.loadMonitor() },
    },

    async created() {
        await Promise.all([this.loadMonitor(), this.loadTalkRooms()])
    },

    methods: {
        async loadMonitor() {
            this.loading = true
            try {
                const resp = await api.getMonitor(this.id)
                this.monitor = resp.data
            } catch (e) {
                this.monitor = null
            } finally {
                this.loading = false
            }
        },

        async loadTalkRooms() {
            try {
                const resp = await api.getTalkRooms()
                this.talkRooms = resp.data
            } catch (e) { /* Talk not installed */ }
        },

        async runCheckNow() {
            this.checking = true
            try {
                const resp = await api.checkNow(this.monitor.id)
                this.monitor = resp.data
                emit('webtrack:monitors:refresh')
                this.$nextTick(() => { this.$refs.history?.load() })
                showSuccess(this.t('webtrack', 'Check complete'))
            } catch (e) {
                showError(this.t('webtrack', 'Check failed'))
            } finally {
                this.checking = false
            }
        },

        openEdit() {
            this.editingMonitor = { ...this.monitor }
            this.formOpen = true
        },

        async togglePause() {
            try {
                const resp = await api.pauseMonitor(this.monitor.id, this.monitor.isActive)
                this.monitor = resp.data
                emit('webtrack:monitors:refresh')
                this.$nextTick(() => { this.$refs.history?.load() })
                showSuccess(
                    this.monitor.isActive
                        ? this.t('webtrack', 'Monitor resumed')
                        : this.t('webtrack', 'Monitor paused')
                )
            } catch (e) {
                showError(this.t('webtrack', 'Failed to update monitor'))
            }
        },

        onSaved(monitor) {
            this.monitor = monitor
            this.formOpen = false
            emit('webtrack:monitors:refresh')
            this.$nextTick(() => { this.$refs.history?.load() })
        },

        formatDate(iso) {
            if (!iso) return '—'
            const locale = getLocale().replace(/_/g, '-')
            return new Date(iso).toLocaleString(locale, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            })
        },

        formatInterval(minutes) {
            if (minutes >= 1440) return (minutes / 1440) + ' ' + this.t('webtrack', 'days')
            if (minutes >= 60) return (minutes / 60) + ' ' + this.t('webtrack', 'hours')
            return minutes + ' ' + this.t('webtrack', 'minutes')
        },
    },
}
</script>
