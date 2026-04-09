<template>
    <div class="wn-page-content">
        <div class="wn-welcome">
            <div class="wn-welcome-icon"><AccessPoint :size="48" /></div>
            <h2>{{ t('webtrack', 'WebTrack') }}</h2>
            <p class="wn-welcome-desc">
                {{ t('webtrack', 'Monitor websites and feeds for keywords — get notified when they appear.') }}
            </p>
            <p class="wn-welcome-hint">
                {{ t('webtrack', 'Select a monitor from the sidebar, or click «New monitor» to get started.') }}
            </p>
            <div class="wn-welcome-features">
                <div class="wn-feature">
                    <span class="wn-feature-icon"><Web :size="24" /></span>
                    <span>{{ t('webtrack', 'Web pages') }}</span>
                </div>
                <div class="wn-feature">
                    <span class="wn-feature-icon"><Rss :size="24" /></span>
                    <span>{{ t('webtrack', 'RSS / Atom feeds') }}</span>
                </div>
                <div class="wn-feature">
                    <span class="wn-feature-icon"><Bell :size="24" /></span>
                    <span>{{ t('webtrack', 'Notifications') }}</span>
                </div>
                <div class="wn-feature">
                    <span class="wn-feature-icon"><Forum :size="24" /></span>
                    <span>{{ t('webtrack', 'Talk integration') }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import AccessPoint from 'vue-material-design-icons/AccessPoint.vue'
import Web from 'vue-material-design-icons/Web.vue'
import Rss from 'vue-material-design-icons/Rss.vue'
import Bell from 'vue-material-design-icons/Bell.vue'
import Forum from 'vue-material-design-icons/Forum.vue'

export default {
    name: 'MonitorList',
    components: {
        AccessPoint,
        Web,
        Rss,
        Bell,
        Forum,
    },
}
</script>
