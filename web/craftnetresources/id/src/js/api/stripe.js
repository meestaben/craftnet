/* global Craft */

import axios from 'axios'
import qs from 'qs'

export default {
    getAccount() {
        return axios.get(window.craftIdUrl + '/stripe/account')
    },

    disconnect() {
        return axios.post(window.craftIdUrl + '/stripe/disconnect', {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

    saveCard(source) {
        const data = {
            paymentMethodId: source.id
        }

        return axios.post(window.craftIdUrl + '/stripe/save-card', qs.stringify(data), {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

    removeCard() {
        return axios.post(window.craftIdUrl + '/stripe/remove-card', {}, {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },
}