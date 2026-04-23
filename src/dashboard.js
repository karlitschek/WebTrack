import { createApp } from 'vue'
import { translate, translatePlural } from '@nextcloud/l10n'
import DashboardWidget from './components/DashboardWidget.vue'

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('webtrack-recent-finds', (el) => {
		const app = createApp(DashboardWidget)
		app.config.globalProperties.t = translate
		app.config.globalProperties.n = translatePlural
		app.mount(el)
	})
})
