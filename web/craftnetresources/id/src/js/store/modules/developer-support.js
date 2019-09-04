import Vue from 'vue'
import Vuex from 'vuex'
import developerSupportApi from '../../api/developer-support'

Vue.use(Vuex)

/**
 * State
 */
const state = {
    subscriptionInfo: null,
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
    currentPlan(state, getters) {
        return state.plans.find(p => p.handle === getters.currentPlanHandle)
    },

    currentPlanHandle(state) {
        if (!state.subscriptionInfo) {
            return null
        }

        const subscriptionData = state.subscriptionInfo.subscriptionData

        // Check if we have an active plan
        for(let planHandle in subscriptionData) {
            if (subscriptionData[planHandle].status === 'active') {
                return planHandle
            }
        }

        // Check if we have an expiring plan
        for(let planHandle in subscriptionData) {
            if (subscriptionData[planHandle].status === 'expiring') {
                return planHandle
            }
        }

        // Otherwise assume we're on basic
        return 'basic'
    },

    subscriptionInfoPlan(state) {
        return (planHandle) => {
            if (!state.subscriptionInfo.plans[planHandle]) {
                return null
            }

            return state.subscriptionInfo.plans[planHandle]
        }
    },

    subscriptionInfoSubscriptionData(state) {
        return (planHandle) => {
            if (!state.subscriptionInfo.subscriptionData[planHandle]) {
                return null
            }

            return state.subscriptionInfo.subscriptionData[planHandle]
        }
    },
}

/**
 * Actions
 */
const actions = {
    cancelSubscription({commit}, subscriptionUid) {
        return developerSupportApi.cancelSubscription(subscriptionUid)
            .then((response) => {
                if (response.data.error) {
                    throw response.data.error
                }

                commit('updateSubscriptionInfo', response.data)
            })
    },

    getSubscriptionInfo({commit}) {
        return developerSupportApi.getSubscriptionInfo()
            .then((response) => {
                if (response.data.error) {
                    throw response.data.error
                }

                commit('updateSubscriptionInfo', response.data)
            })
    },

    reactivateSubscription({commit}, subscriptionUid) {
        return developerSupportApi.reactivateSubscription(subscriptionUid)
            .then((response) => {
                if (response.data.error) {
                    throw response.data.error
                }

                commit('updateSubscriptionInfo', response.data)
            })
    },

    subscribe({commit}, planHandle) {
        return developerSupportApi.subscribe(planHandle)
            .then((response) => {
                if (response.data.error) {
                    throw response.data.error
                }

                commit('updateSubscriptionInfo', response.data)
            })
    },

    switchPlan({commit}, newPlan) {
        return developerSupportApi.switchPlan(newPlan)
            .then((response) => {
                if (response.data.error) {
                    throw response.data.error
                }

                commit('updateSubscriptionInfo', response.data)
            })
    },
}

/**
 * Mutations
 */
const mutations = {
    updateSubscriptionInfo(state, subscriptionInfo){
        state.subscriptionInfo = subscriptionInfo
    },
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
}
