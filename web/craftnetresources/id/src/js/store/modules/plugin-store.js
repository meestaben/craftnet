import Vue from 'vue'
import Vuex from 'vuex'
import pluginStoreApi from '../../api/plugin-store'

Vue.use(Vuex)

/**
 * State
 */
const state = {
    categories: [],
    featuredPlugins: [],
    plugins: [],
    metaLoaded: false,
    expiryDateOptions: [],
}

/**
 * Getters
 */
const getters = {}

/**
 * Actions
 */
const actions = {
    getCoreData({commit, state}) {
        return new Promise((resolve, reject) => {
            if (!state.metaLoaded) {
                pluginStoreApi.getCoreData()
                    .then((response) => {
                        commit('updatePluginStoreMeta', {response})
                        resolve()
                    })
                    .catch((response) => {
                        reject(response)
                    })
            } else {
                resolve()
            }
        })
    },

    getPlugins({commit, state}, requestedPluginIds) {
        return new Promise((resolve, reject) => {
            const pluginIds = []

            requestedPluginIds.forEach(pluginId => {
                const plugin = state.plugins.find(plugin => plugin.id === pluginId)

                if (!plugin) {
                    pluginIds.push(pluginId)
                }
            })

            pluginStoreApi.getPlugins(requestedPluginIds)
                .then((response) => {
                    commit('updatedPlugins', {response})
                    resolve()
                })
                .catch((response) => {
                    reject(response)
                })
        })
    }
}

/**
 * Mutations
 */
const mutations = {
    updatePluginStoreMeta(state, {response}) {
        state.categories = response.data.categories
        state.featuredPlugins = response.data.featuredPlugins
        state.expiryDateOptions = response.data.expiryDateOptions,
        state.metaLoaded = true
    },

    updatedPlugins(state, {response}) {
        const responsePlugins = response.data

        responsePlugins.forEach(responsePlugin => {
            const alreadyInState = state.plugins.find(plugin => plugin.id === responsePlugin.id)

            if (!alreadyInState) {
                state.plugins.push(responsePlugin)
            }
        })
    }
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
}
