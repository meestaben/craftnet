import Vue from 'vue'
import Vuex from 'vuex'
import invoicesApi from '../../api/invoices'

Vue.use(Vuex)

/**
 * State
 */
const state = {
    subscriptionInvoices: [],
}

/**
 * Getters
 */
const getters = {}

/**
 * Actions
 */
const actions = {
    getSubscriptionInvoices({commit}) {
        return new Promise((resolve, reject) => {
            invoicesApi.getSubscriptionInvoices()
                .then((response) => {
                    if (typeof response.data !== 'undefined' && !response.data.error) {
                        commit('updateSubscriptionInvoices', response.data.invoices)
                        resolve(response)
                    } else {
                        reject(response)
                    }
                })
                .catch((response) => {
                    reject(response)
                })
        })
    },
}

/**
 * Mutations
 */
const mutations = {
    updateSubscriptionInvoices(state, subscriptionInvoices) {
        state.subscriptionInvoices = subscriptionInvoices
    }
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
}
