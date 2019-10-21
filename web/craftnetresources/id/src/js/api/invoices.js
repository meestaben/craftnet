/* global Craft */

import axios from 'axios'

export default {
    getInvoiceByNumber(number) {
        return axios.get(Craft.actionUrl + '/craftnet/id/invoices/get-invoice-by-number', {params: {number}})
    },

    getSubscriptionInvoices() {
        return axios.get(Craft.actionUrl + '/craftnet/id/invoices/get-subscription-invoices')
    }
}
