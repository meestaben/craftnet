/* global Craft */

import axios from 'axios'
import qs from 'qs'

export default {
    getSubscriptionInfo() {
        return axios.get(Craft.actionUrl + '/craftnet/id/developer-support/get-subscription-info', {}, {
            headers: {
                'X-CSRF-Token': Craft.csrfTokenValue,
            }
        })
    },

    switchPlan(newPlan) {
        const data = {
            newPlan
        }

        return axios.post(Craft.actionUrl + '/craftnet/id/developer-support/switch-plan', qs.stringify(data), {
            headers: {
                'X-CSRF-Token': Craft.csrfTokenValue,
            }
        })
    }
}
