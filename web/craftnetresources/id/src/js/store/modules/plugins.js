import Vue from 'vue'
import Vuex from 'vuex'
import pluginsApi from '../../api/plugins'

Vue.use(Vuex)

/**
 * State
 */
const state = {
    plugins: [],
}

/**
 * Getters
 */
const getters = {
    repositoryIsInUse(state) {
        return repositoryUrl => {
            return state.plugins.find(plugin => plugin.repository === repositoryUrl)
        }
    },
}

/**
 * Actions
 */
const actions = {
    savePlugin({commit}, {plugin}) {
        return new Promise((resolve, reject) => {
            pluginsApi.save({plugin})
                .then((response) => {
                    if (response.data.success) {
                        commit('savePlugin', {plugin, response})
                        resolve(response)
                    } else {
                        reject(response)
                    }
                })
                .catch((error) => {
                    reject(error.response)
                })
        })
    },

    submitPlugin({commit}, pluginId) {
        return new Promise((resolve, reject) => {
            pluginsApi.submit(pluginId)
                .then((response) => {
                    if (response.data.success) {
                        commit('submitPlugin', {pluginId})
                        resolve(response)
                    } else {
                        reject(response)
                    }
                })
                .catch((error) => {
                    reject(error.response)
                })
        })
    },

    getPlugins({commit}) {
        return new Promise((resolve, reject) => {
            pluginsApi.getPlugins()
                .then((response) => {
                    commit('updatePlugins', {plugins: response.data})
                    resolve(response)
                })
                .catch((error) => {
                    reject(error.response)
                })
        })
    },
}

/**
 * Mutations
 */
const mutations = {
    updatePlugins(state, {plugins}) {
        state.plugins = plugins
    },

    savePlugin(state, {plugin, response}) {
        let newPlugin = false
        let statePlugin = state.plugins.find(p => p.id == plugin.pluginId)

        if (!statePlugin) {
            statePlugin = {
                id: response.data.id,
            }

            newPlugin = true
        }

        let iconUrl = response.data.iconUrl

        if(iconUrl) {
            iconUrl = iconUrl + (iconUrl.match(/\?/g) ? '&' : '?') + Math.floor(Math.random() * 1000000)
        }

        statePlugin.siteId = plugin.siteId
        statePlugin.pluginId = response.data.id
        statePlugin.icon = plugin.icon
        statePlugin.iconUrl = iconUrl
        statePlugin.iconId = response.data.iconId
        statePlugin.developerId = plugin.developerId
        statePlugin.developerName = plugin.developerName
        statePlugin.handle = plugin.handle
        statePlugin.packageName = plugin.packageName
        statePlugin.name = plugin.name
        statePlugin.shortDescription = plugin.shortDescription
        statePlugin.longDescription = plugin.longDescription
        statePlugin.documentationUrl = plugin.documentationUrl
        statePlugin.changelogPath = plugin.changelogPath
        statePlugin.repository = plugin.repository
        statePlugin.license = plugin.license
        statePlugin.keywords = plugin.keywords
        statePlugin.abandoned = plugin.abandoned
        statePlugin.replacementHandle = plugin.replacementHandle

        let price = parseFloat(plugin.price)
        statePlugin.price = (price ? price : null)

        let renewalPrice = parseFloat(plugin.renewalPrice)
        statePlugin.renewalPrice = (renewalPrice ? renewalPrice : null)

        statePlugin.categoryIds = plugin.categoryIds

        let screenshotIds = []
        let screenshotUrls = []

        if (response.data.screenshots.length > 0) {
            for (let i = 0; i < response.data.screenshots.length; i++) {
                screenshotIds.push(response.data.screenshots[i].id)
                screenshotUrls.push(response.data.screenshots[i].url)
            }
        }

        statePlugin.screenshotIds = screenshotIds
        statePlugin.screenshotUrls = screenshotUrls

        statePlugin.editions = response.data.editions

        if (newPlugin) {
            state.plugins.push(statePlugin)
        }
    },

    submitPlugin(state, {pluginId}) {
        let statePlugin = state.plugins.find(p => p.id == pluginId)
        statePlugin.pendingApproval = true
    },
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
}
