import Vue from 'vue'
import Router from 'vue-router'
import MonitorList from './views/MonitorList.vue'
import MonitorDetail from './views/MonitorDetail.vue'

Vue.use(Router)

export default new Router({
    mode: 'hash',
    routes: [
        { path: '/',              component: MonitorList },
        { path: '/monitors/:id', component: MonitorDetail, props: true },
        { path: '*',             redirect: '/' },
    ],
})
