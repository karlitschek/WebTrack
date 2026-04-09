import '../css/style.css'
import Vue from 'vue'
import App from './App.vue'
import router from './router.js'
import { translate, translatePlural } from '@nextcloud/l10n'

// Make l10n helpers available on all components
Vue.prototype.t  = translate
Vue.prototype.n  = translatePlural

// Suppress Vue production tip
Vue.config.productionTip = false

new Vue({
    el: '#app-content-vue',
    router,
    render: h => h(App),
})
