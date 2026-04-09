<template>
    <span class="wn-status-badge" :class="'wn-status-badge--' + status">
        {{ label }}
    </span>
</template>

<script>
export default {
    name: 'StatusBadge',
    props: {
        status: { type: String, required: true },
    },
    computed: {
        label() {
            const map = {
                ok:      this.t('webtrack', 'OK'),
                found:   this.t('webtrack', 'Found'),
                error:   this.t('webtrack', 'Error'),
                failing: this.t('webtrack', 'Failing'),
                paused:  this.t('webtrack', 'Paused'),
            }
            return map[this.status] || this.status
        },
    },
}
</script>
