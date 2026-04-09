<template>
    <NcModal :name="title" @close="$emit('close')">
        <div class="wn-form-container">
            <h2 class="wn-form-title">{{ title }}</h2>

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
            </fieldset>

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
import NcModal  from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
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
                name:          m ? m.name          : '',
                url:           m ? m.url           : '',
                keyword:       m ? m.keyword       : '',
                useRegex:      m ? !!m.useRegex     : false,
                checkInterval: m ? m.checkInterval : 60,
                isFeed:        m ? m.isFeed        : false,
                talkRoomToken: m ? (m.talkRoomToken || '') : '',
            },
            errors:        [],
            saving:        false,
            urlTesting:    false,
            urlTestResult: null,
            urlTestError:  null,
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
    },

    methods: {
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
                    // Auto-set isFeed based on detection
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

        async save() {
            this.errors = this.validate()
            if (this.errors.length) return

            this.saving = true
            try {
                const payload = { ...this.form }
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
