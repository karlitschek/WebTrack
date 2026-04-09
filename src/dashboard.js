import Vue from 'vue'
import { translate, translatePlural } from '@nextcloud/l10n'
import DashboardWidget from './components/DashboardWidget.vue'

Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.config.productionTip = false

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('webtrack-recent-finds', (el) => {
		const View = Vue.extend(DashboardWidget)
		new View().$mount(el)
	})
})
