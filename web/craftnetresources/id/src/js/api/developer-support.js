/* global Craft */

import axios from 'axios'

export default {
    mockedSubscriptionInfo(currentPlan) {
        return {
            currentPlan,
            pro: {
                uid: 1234,
                canceled: false,
                cycleEnd: '2019-09-19',
            },
            premium: {
                uid: 4567,
                canceled: false,
                cycleEnd: '2019-09-19',
                upgradeCost: 412.5,
            },
        }
    },

    getSubscriptionInfo() {
        console.log('api:getSubscriptionInfo')
        return new Promise((resolve, reject) => {
            setTimeout(function() {
                // reject('Some error')
                // return null

                const response = this.mockedSubscriptionInfo('pro')

                resolve(response)
            }.bind(this), 2000)
        })

        // return axios.get(Craft.actionUrl + '/craftnet/id/developer-support/get-subscription-info', {}, {
        //     headers: {
        //         'X-CSRF-Token': Craft.csrfTokenValue,
        //     }
        // })
    },

    switchPlan(newPlan) {
        console.log('api:switchPlan', newPlan)
        return new Promise((resolve) => {
            setTimeout(function() {
                const response = this.mockedSubscriptionInfo(newPlan)

                resolve(response)
            }.bind(this), 2000)
        })
        // const data = {
        //     newPlan
        // }
        //
        // return axios.post(Craft.actionUrl + '/craftnet/id/developer-support/switch-plan', qs.stringify(data), {
        //     headers: {
        //         'X-CSRF-Token': Craft.csrfTokenValue,
        //     }
        // })
    }
}
