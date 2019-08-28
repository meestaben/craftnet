import Vue from 'vue'
import Vuex from 'vuex'
import developerSupportApi from '../../api/developer-support'

Vue.use(Vuex)

/**
 * State
 */
const state = {
    showModal: false,
    subscriptionInfo: null,
    selectedPlanHandle: null,
    plans: [
        {
            icon: 'support-plan-basic',
            handle: "basic",
            name: "Basic",
            price: 0,
            features: [
                'Utilize Discord and Stack Exchange',
                'While you can contact the team at CMS directly, there is no guaranteed response time',
            ]
        },
        {
            icon: 'support-plan-pro',
            handle: "pro",
            name: "Pro",
            price: 75,
            features: [
                'Contact the team at Craft CMS directly via email',
                'Guaranteed 12 hour or less time to first response (M-F)',
            ]
        },
        {
            icon: 'support-plan-premium',
            handle: "premium",
            name: "Premium",
            price: 750,
            features: [
                'Contact the team at Craft CMS directly via email',
                'Tickets go to top of queue',
                'Guaranteed 2 hour or less time to first response (M-F), 12 hours on weekends',
            ]
        },
    ]
}

/**
 * Getters
 */
const getters = {
    currentPlan(state) {
        return state.subscriptionInfo.currentPlan
    },

    newSubscriptionInfo(state, getters) {
        if (!state.selectedPlanHandle) {
            return null
        }

        return getters.planSubscriptionInfo(state.selectedPlanHandle)
    },

    planSubscriptionInfo(state) {
        return (planHandle) => {
            if (!state.subscriptionInfo[planHandle]) {
                return null
            }

            return state.subscriptionInfo[planHandle]
        }
    },

    selectedPlan(state) {
        if (!state.selectedPlanHandle) {
            return null
        }

        return state.plans.find(plan => plan.handle === state.selectedPlanHandle)
    },
}

/**
 * Actions
 */
const actions = {
    getSubscriptionInfo({commit}) {
        return developerSupportApi.getSubscriptionInfo()
            .then((response) => {
                commit('updateSubscriptionInfo', response.data)
            })
    },

    switchPlan({commit}, newPlan) {
        return developerSupportApi.switchPlan(newPlan)
            .then((response) => {
                commit('updateSubscriptionInfo', response.data)
            })
    },
}

/**
 * Mutations
 */
const mutations = {
    updateSelectedPlan(state, planHandle){
        state.selectedPlanHandle = planHandle
    },

    updateSubscriptionInfo(state, subscriptionInfo){
        state.subscriptionInfo = subscriptionInfo
    },

    updateShowModal(state, showModal){
        state.showModal = showModal
    },
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
}
