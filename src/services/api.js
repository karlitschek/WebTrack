import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/webtrack/api/v1'

const url = (path) => generateUrl(base + path)

// ---- Monitors ----------------------------------------------------------------

export const getMonitors = () =>
    axios.get(url('/monitors'))

export const createMonitor = (data) =>
    axios.post(url('/monitors'), data)

export const getMonitor = (id) =>
    axios.get(url(`/monitors/${id}`))

export const updateMonitor = (id, data) =>
    axios.put(url(`/monitors/${id}`), data)

export const deleteMonitor = (id) =>
    axios.delete(url(`/monitors/${id}`))

export const pauseMonitor = (id, pause) =>
    axios.post(url(`/monitors/${id}/pause`), { pause })

export const testUrl = (targetUrl) =>
    axios.post(url('/monitors/test'), { url: targetUrl })

// ---- History -----------------------------------------------------------------

export const getHistory = (monitorId, page = 0) =>
    axios.get(url(`/monitors/${monitorId}/history`), { params: { page } })

// ---- Settings ----------------------------------------------------------------

export const getSettings = () =>
    axios.get(url('/settings'))

export const saveSettings = (settings) =>
    axios.put(url('/settings'), settings)

// ---- Talk rooms --------------------------------------------------------------

export const getTalkRooms = () =>
    axios.get(url('/talk/rooms'))
