<template>
	<NcDashboardWidget :items="mappedItems"
		:show-more-url="appUrl"
		:loading="loading"
		:empty-content-message="t('webtrack', 'No keyword matches found yet')">
		<template #default="{ item }">
			<NcDashboardWidgetItem :target-url="item.targetUrl"
				:avatar-url="item.avatarUrl"
				:main-text="item.mainText"
				:sub-text="item.subText" />
		</template>
	</NcDashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate } from '@nextcloud/l10n'
import NcDashboardWidget from '@nextcloud/vue/dist/Components/NcDashboardWidget.js'
import NcDashboardWidgetItem from '@nextcloud/vue/dist/Components/NcDashboardWidgetItem.js'

export default {
	name: 'DashboardWidget',

	components: {
		NcDashboardWidget,
		NcDashboardWidgetItem,
	},

	data() {
		return {
			rawItems: [],
			loading: true,
		}
	},

	computed: {
		appUrl() {
			return generateUrl('/apps/webtrack')
		},

		mappedItems() {
			return this.rawItems.map((item) => ({
				id: item.sinceId,
				targetUrl: generateUrl('/apps/webtrack'),
				avatarUrl: item.iconUrl,
				mainText: item.title,
				subText: item.subtitle,
			}))
		},
	},

	mounted() {
		this.fetchItems()
	},

	methods: {
		t: translate,

		async fetchItems() {
			try {
				const response = await axios.get(
					generateUrl('/ocs/v2.php/apps/dashboard/api/v2/widget-items'),
					{
						params: { 'widgets[]': 'webtrack-recent-finds' },
						headers: { 'OCS-APIRequest': 'true' },
					}
				)
				const widgetData = response.data?.ocs?.data?.['webtrack-recent-finds'] ?? {}
				this.rawItems = widgetData.items ?? []
			} catch (e) {
				console.error('WebTrack dashboard: failed to load items', e)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>
