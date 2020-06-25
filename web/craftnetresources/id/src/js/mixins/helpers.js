import get from 'lodash/get'
import update from 'lodash/update'
import Vue from 'vue'

Vue.use(require('vue-moment'))

var VueApp = new Vue();

export default {
    methods: {
        /**
         * Clones an object without references or bindings.
         * Optionally accepts a filtered property list with dot-syntax
         * for nested properties.
         *
         * Example:
         * ```
         * let obj = {
         *     test: 'value',
         *     foo: {
         *         bar: {
         *             baz: 'one',
         *             boo: 'two'
         *         }
         *     }
         * }
         *
         * // an existing value and a missing value, with default
         * let clone = simpleClone(obj, [
         *     'foo.bar.baz',
         *     ['aList', []]
         * ])
         *
         * clone == {foo: {bar: {baz: 'hello'}}, aList: []} // true
         * ```
         *
         * @param {Object} obj
         * @param {Array} propertyList
         */
        simpleClone(obj, propertyList) {
            let clone = JSON.parse(JSON.stringify(obj))

            if (!propertyList) {
                return clone
            }

            let filteredClone = {}

            for (let i = 0; i < propertyList.length; i++) {
                const path = propertyList[i];

                if (typeof path === 'object') {
                    update(filteredClone, path, () => get(clone, path[0], path[1]))
                } else {
                    update(filteredClone, path, () => get(clone, path, null))
                }
            }

            return filteredClone;
        },

        /**
         * Returns a static image URL.
         *
         * @param {String} url
         * @returns {String}
         */
        staticImageUrl(url) {
            if (process.env.NODE_ENV === 'development') {
                return process.env.BASE_URL + 'img/static/' + url;
            }

            return '/craftnetresources/id/dist/img/static/' + url;
        },

        /**
         * Returns the Craft Plugins URL.
         *
         * @returns {String}
         */
        craftPluginsUrl() {
            return process.env.VUE_APP_CRAFT_PLUGINS_URL;
        },

        expiresSoon(license) {
            if(!license.expiresOn) {
                return false
            }

            const today = new Date()
            let expiryDate = new Date()
            expiryDate.setDate(today.getDate() + 45)

            const expiresOn = new Date(license.expiresOn.date)

            if(expiryDate > expiresOn) {
                return true
            }

            return false
        },

        daysBeforeExpiry(license) {
            const today = new Date()
            const expiresOn = new Date(license.expiresOn.date)
            const diff = expiresOn.getTime() - today.getTime()
            const diffDays = Math.round(diff / (1000 * 60 * 60 * 24))
            return diffDays;
        },

        getRenewableLicenses(license, renew, cartItems) {
            let renewableLicenses = []

            // CMS license
            const renewalOptions = license.renewalOptions
            const renewalOption = renewalOptions[renew]
            const expiryDate = renewalOption.expiryDate

            if (license.expirable) {
                renewableLicenses.push({
                    type: 'cms-renewal',
                    key: license.key,
                    description: 'Craft ' + license.editionDetails.name,
                    renew: renew,
                    expiryDate: expiryDate,
                    expiresOn: license.expiresOn,
                    edition: license.editionDetails,
                    alreadyInCart: this.licenseKeyAlreadyInCart(license.key, cartItems),
                    amount: renewalOption.amount
                })
            }

            // Plugin licenses
            if (license.pluginLicenses.length > 0) {
                // Renewable plugin licenses
                const renewablePluginLicenses = license.pluginLicenses.filter(pluginLicense => {
                    // Plugin licenses with no `expiresOn` are not renewable
                    if (!pluginLicense.expiresOn) {
                        return false
                    }

                    // Ignore the plugin license if it expires after the CMS license
                    if (!pluginLicense.expired) {
                        const pluginExpiresOn = VueApp.$moment(pluginLicense.expiresOn.date)
                        const expiryDateObject = VueApp.$moment(expiryDate)
                        
                        if(expiryDateObject.diff(pluginExpiresOn) <= 0) {
                            return false
                        }
                    }

                    return true
                })

                // Add renewable plugin licenses to the `renewableLicenses` array
                renewablePluginLicenses.forEach(function(renewablePluginLicense) {
                    const pluginHandle = renewablePluginLicense.plugin.handle
                    const pluginRenewalOptions = license.pluginRenewalOptions[pluginHandle]
                    const pluginRenewalOption = pluginRenewalOptions.find(r => r.expiryDate === expiryDate)
                    const amount = pluginRenewalOption.amount

                    renewableLicenses.push({
                        type: 'plugin-renewal',
                        key: renewablePluginLicense.key,
                        description: renewablePluginLicense.plugin.name,
                        expiryDate: expiryDate,
                        expiresOn: renewablePluginLicense.expiresOn,
                        edition: renewablePluginLicense.edition,
                        alreadyInCart: this.licenseKeyAlreadyInCart(renewablePluginLicense.key, cartItems),
                        amount: amount,
                    })
                }.bind(this))
            }

            return renewableLicenses
        },

        licenseKeyAlreadyInCart(licenseKey, cartItems) {
            return !!cartItems.find(item => item.lineItem.options.licenseKey === licenseKey)
        },
    }
}
