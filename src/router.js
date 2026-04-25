import { createRouter, createWebHashHistory } from 'vue-router'
import MonitorList from './views/MonitorList.vue'
import MonitorDetail from './views/MonitorDetail.vue'

export default createRouter({
    history: createWebHashHistory(),
    routes: [
        { path: '/', component: MonitorList },
        { path: '/monitors/:id', component: MonitorDetail, props: true },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
})
