import Vue from 'vue'
import Vuex from 'vuex'

Vue.use(Vuex)

/**
 * State
 */
const state = {
    subscriptionInfo: {
        currentPlan: 'premium',
        priority: {
            uid: 1234,
            canceled: false,
            cycleEnd: '2019-09-19',
        },
        premium: {
            uid: 4567,
            canceled: false,
            cycleEnd: '2019-09-19',
            upgradeCost: 29,
        },
    },
    plans: [
        {
            icon: 'support-plan-standard',
            handle: "standard",
            name: "Standard",
            price: 0,
            features: [
                'Utilize Discord and Stack Exchange',
                'While you can contact the team at CMS directly, there is no guaranteed response time',
            ]
        },
        {
            icon: 'support-plan-premium',
            handle: "premium",
            name: "Premium",
            price: 75,
            features: [
                'Contact the team at Craft CMS directly via email',
                'Guaranteed 12 hour or less time to first response (M-F)',
            ]
        },
        {
            icon: 'support-plan-priority',
            handle: "priority",
            name: "Priority",
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
}

/**
 * Actions
 */
const actions = {}

/**
 * Mutations
 */
const mutations = {
    updateCurrentPlan(state, plan){
        state.subscriptionInfo.currentPlan = plan
    },
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
}
