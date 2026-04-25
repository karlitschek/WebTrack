<template>
    <!-- Mount directly to #content; NcContent is intentionally omitted.
         NcContent would create a second "content app-webtrack" flex root
         nested inside #content, breaking the Nextcloud layout. Instead we
         render NcAppNavigation + NcAppContent as direct flex children of
         #content (Vue 3 fragment — multiple root elements). -->
    <NcAppNavigation>
            <template #list>
                <NcAppNavigationItem
                    :name="t('webtrack', 'New monitor')"
                    @click="openCreate">
                    <template #icon>
                        <span class="icon-add" />
                    </template>
                </NcAppNavigationItem>

                <li v-if="loading" style="text-align:center;padding:12px;">
                    <NcLoadingIcon :size="20" />
                </li>

                <NcAppNavigationItem
                    v-for="monitor in monitors"
                    :key="monitor.id"
                    :name="monitor.name"
                    :to="'/monitors/' + monitor.id">
                    <template #icon>
                        <span class="wn-nav-dot" :class="'wn-nav-dot--' + monitor.status" />
                    </template>
                    <template #counter>
                        <span v-if="monitor.lastCheckAt" class="wn-nav-time">
                            {{ timeAgo(monitor.lastCheckAt) }}
                        </span>
                    </template>
                    <template #actions>
                        <NcActionButton @click="openEdit(monitor)">
                            <template #icon><Pencil :size="20" /></template>
                            {{ t('webtrack', 'Edit') }}
                        </NcActionButton>
                        <NcActionButton @click="togglePause(monitor)">
                            <template #icon>
                                <PauseIcon v-if="monitor.isActive" :size="20" />
                                <PlayIcon v-else :size="20" />
                            </template>
                            {{ monitor.isActive ? t('webtrack', 'Pause') : t('webtrack', 'Resume') }}
                        </NcActionButton>
                        <NcActionButton @click="confirmDelete(monitor)">
                            <template #icon><Delete :size="20" /></template>
                            {{ t('webtrack', 'Delete') }}
                        </NcActionButton>
                    </template>
                </NcAppNavigationItem>
            </template>

            <template #footer>
                <NcAppNavigationSettings :name="t('webtrack', 'Settings')">
                    <div class="wn-settings-content">
                        <div class="wn-form-row">
                            <label>{{ t('webtrack', 'Default Talk room') }}</label>
                            <select v-model="settings.defaultTalkRoomToken" @change="saveSettings">
                                <option value="">{{ t('webtrack', '— none —') }}</option>
                                <option v-for="room in talkRooms" :key="room.token" :value="room.token">
                                    {{ room.name }}
                                </option>
                            </select>
                        </div>
                        <p v-if="talkRooms.length === 0"
                            style="color:var(--color-text-maxcontrast); font-size:0.85em; margin:4px 0 0;">
                            {{ t('webtrack', 'Install Nextcloud Talk to enable room notifications.') }}
                        </p>
                    </div>
                </NcAppNavigationSettings>
            </template>
        </NcAppNavigation>

    <NcAppContent>
        <router-view />
    </NcAppContent>

    <!-- Global create / edit form -->
    <MonitorForm v-if="formOpen"
        :monitor="editingMonitor"
        :talk-rooms="talkRooms"
        @saved="onSaved"
        @close="formOpen = false" />
</template>

<script>
import {
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppNavigationSettings,
    NcAppContent,
    NcActionButton,
    NcLoadingIcon,
} from '@nextcloud/vue'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import Pencil   from 'vue-material-design-icons/Pencil.vue'
import Delete   from 'vue-material-design-icons/Delete.vue'
import PauseIcon from 'vue-material-design-icons/Pause.vue'
import PlayIcon  from 'vue-material-design-icons/Play.vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import MonitorForm from './components/MonitorForm.vue'
import * as api from './services/api.js'

export default {
    name: 'WebTrackApp',
    components: {
        NcAppNavigation,
        NcAppNavigationItem,
        NcAppNavigationSettings,
        NcAppContent,
        NcActionButton,
        NcLoadingIcon,
        Pencil,
        Delete,
        PauseIcon,
        PlayIcon,
        MonitorForm,
    },

    data() {
        return {
            monitors:       [],
            talkRooms:      [],
            settings:       { defaultTalkRoomToken: '' },
            loading:        true,
            formOpen:       false,
            editingMonitor: null,
        }
    },

    async created() {
        subscribe('webtrack:monitors:refresh', this.loadMonitors)
        await Promise.all([this.loadMonitors(), this.loadTalkRooms(), this.loadSettings()])
    },

    beforeUnmount() {
        unsubscribe('webtrack:monitors:refresh', this.loadMonitors)
    },

    methods: {
        async loadMonitors() {
            this.loading = true
            try {
                const resp = await api.getMonitors()
                this.monitors = resp.data
            } catch (e) {
                showError(this.t('webtrack', 'Failed to load monitors'))
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

        async loadSettings() {
            try {
                const resp = await api.getSettings()
                this.settings = resp.data
            } catch (e) { /* ignore */ }
        },

        async saveSettings() {
            try {
                await api.saveSettings(this.settings)
                showSuccess(this.t('webtrack', 'Settings saved'))
            } catch (e) {
                showError(this.t('webtrack', 'Failed to save settings'))
            }
        },

        openCreate() {
            this.editingMonitor = null
            this.formOpen = true
        },

        openEdit(monitor) {
            this.editingMonitor = { ...monitor }
            this.formOpen = true
        },

        async togglePause(monitor) {
            try {
                const resp = await api.pauseMonitor(monitor.id, monitor.isActive)
                const idx = this.monitors.findIndex(m => m.id === monitor.id)
                if (idx !== -1) {
                    this.monitors[idx] = resp.data
                }
            } catch (e) {
                showError(this.t('webtrack', 'Failed to update monitor'))
            }
        },

        async confirmDelete(monitor) {
            if (!window.confirm(
                this.t('webtrack', 'Delete monitor "{name}"?', { name: monitor.name })
            )) return

            try {
                await api.deleteMonitor(monitor.id)
                this.monitors = this.monitors.filter(m => m.id !== monitor.id)
                showSuccess(this.t('webtrack', 'Monitor deleted'))
                // Navigate back to list if we were viewing this monitor
                if (this.$route.params.id == monitor.id) {
                    this.$router.push('/')
                }
            } catch (e) {
                showError(this.t('webtrack', 'Failed to delete monitor'))
            }
        },

        onSaved(monitor) {
            const idx = this.monitors.findIndex(m => m.id === monitor.id)
            if (idx !== -1) {
                this.monitors[idx] = monitor
            } else {
                this.monitors.push(monitor)
                this.$router.push('/monitors/' + monitor.id)
                // Kick off an immediate check without blocking the UI.
                // When it resolves, replace the monitor in the list so the
                // nav dot and lastCheckAt reflect the real status right away.
                api.checkNow(monitor.id).then(resp => {
                    const i = this.monitors.findIndex(m => m.id === monitor.id)
                    if (i !== -1) this.monitors[i] = resp.data
                }).catch(() => { /* ignore — background job will retry later */ })
            }
            this.formOpen = false
        },

        timeAgo(iso) {
            if (!iso) return ''
            const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
            if (seconds < 60) return this.t('webtrack', 'just now')
            const minutes = Math.floor(seconds / 60)
            if (minutes < 60) return this.n('webtrack', '%n min', '%n min', minutes)
            const hours = Math.floor(minutes / 60)
            if (hours < 24) return this.n('webtrack', '%n hr', '%n hrs', hours)
            const days = Math.floor(hours / 24)
            return this.n('webtrack', '%n day', '%n days', days)
        },
    },
}
</script>

<style scoped>
.wn-settings-content {
    padding: 4px 0 8px;
}
</style>
