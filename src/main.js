import '../css/style.css'
import { createApp } from 'vue'
import { translate, translatePlural } from '@nextcloud/l10n'
import App from './App.vue'
import router from './router.js'

const app = createApp(App)

// Make l10n helpers available on all components via this.t() / this.n()
app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural

app.use(router).mount('#webtrack-root')
