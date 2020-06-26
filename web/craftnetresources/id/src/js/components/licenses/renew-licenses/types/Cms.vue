<template>
    <div>
        <dropdown :value="renew" @input="onRenewChange" :options="renewOptions" />

        <table class="table mb-2">
            <thead>
            <tr>
                <td><input type="checkbox" v-model="checkAllChecked" ref="checkAll" @change="checkAll"></td>
                <th>Item</th>
                <th>Renewal Date</th>
                <th>New Renewal Date</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <renewable-license-table-row
                    v-for="renewableLicense in renewableLicenses"
                    :renewableLicense="renewableLicense"
                    :key="getRenewableLicenseKey(renewableLicense)"
                    :itemKey="getRenewableLicenseKey(renewableLicense)"
                    :isChecked="checkedLicenses[getRenewableLicenseKey(renewableLicense)]"
                    @checkLicense="checkLicense($event)"
            ></renewable-license-table-row>
            <tr>
                <th colspan="4" class="text-right">Total</th>
                <td><strong>{{total|currency}}</strong></td>
            </tr>
            </tbody>
        </table>

        <btn @click="$emit('cancel')">Cancel</btn>
        <btn ref="submitBtn" @click="addToCart()" kind="primary" :disabled="!hasCheckedLicenses">Add to cart</btn>
        <spinner v-if="loading"></spinner>
    </div>
</template>

<script>
    import {mapGetters} from 'vuex'
    import helpers from '../../../../mixins/helpers'
    import RenewableLicenseTableRow from '../RenewableLicenseTableRow'

    export default {
        mixins: [helpers],

        props: ['license'],

        components: {
            RenewableLicenseTableRow,
        },

        data() {
            return {
                loading: false,
                checkAllChecked: false,
                renew: 0,
                checkedLicenses: {},
            }
        },

        computed: {
            ...mapGetters({
                cartItems: 'cart/cartItems',
            }),

            renewOptions() {
                const renewalOptions = this.license.renewalOptions

                if (!renewalOptions) {
                    return []
                }

                const pluginRenewalOptions = this.license.pluginRenewalOptions
                let options = [];

                for (let i = 0; i < renewalOptions.length; i++) {
                    const renewalOption = renewalOptions[i]
                    const date = renewalOption.expiryDate
                    const formattedDate = this.$moment(date).format('YYYY-MM-DD')
                    let label = "Extend updates until " + formattedDate

                    // cms amount
                    let currentAmount = renewalOption.amount

                    if (!this.license.expirable) {
                        currentAmount = 0
                    }

                    const renewableLicenses = this.getRenewableLicenses(this.license, i, this.cartItems)

                    // plugin amounts
                    renewableLicenses.forEach((renewableLicense) => {
                        // only keep checked licenses
                        if (this.checkedLicenses[this.getRenewableLicenseKey(renewableLicense)]) {
                            let pluginHandle = null

                            // extract plugin handle from the plugin licenses
                            this.license.pluginLicenses.forEach(pluginLicense => {
                                if (pluginLicense.key === renewableLicense.key) {
                                    pluginHandle = pluginLicense.plugin.handle

                                    // find plugin renewal options matching this plugin handle
                                    const option = pluginRenewalOptions[pluginHandle][i]

                                    // add plugin option amount
                                    currentAmount += option.amount
                                }
                            })
                        }
                    })

                    // amount difference
                    const amountDiff = currentAmount - this.total

                    if (amountDiff !== 0) {
                        let prefix = ''

                        if (amountDiff > 0) {
                            prefix = '+'
                        }

                        label += ' (' + prefix + this.$options.filters.currency(amountDiff) +')'
                    }

                    options.push({
                        label: label,
                        value: i,
                    })
                }

                return options
            },

            renewableLicenses() {
                return this.getRenewableLicenses(this.license, this.renew, this.cartItems)
            },

            hasCheckedLicenses() {
                return this.hasCheckValue(1)
            },

            total() {
                let total = 0

                this.renewableLicenses.forEach(function(renewableLicense) {
                    if (!this.checkedLicenses[this.getRenewableLicenseKey(renewableLicense)]) {
                        return
                    }

                    total += renewableLicense.amount
                }.bind(this))

                return total
            }
        },

        methods: {
            hasCheckValue(checkValue) {
                let found = false

                for (const property in this.checkedLicenses) {
                    const checked = this.checkedLicenses[property]

                    if (checked === checkValue) {
                        found = true
                    }
                }

                return found
            },

            onRenewChange($event) {
                this.renew = $event

                let checkedLicenses = {}

                this.renewableLicenses.forEach(function(renewableLicense) {
                    let value = 0

                    if (this.checkedLicenses[this.getRenewableLicenseKey(renewableLicense)] === 1) {
                        value = 1
                    }

                    checkedLicenses[this.getRenewableLicenseKey(renewableLicense)] = value
                }.bind(this))

                this.checkedLicenses = checkedLicenses
                this.checkAllChecked = true

                this.$nextTick(() => {
                    if (this.hasCheckValue(0)) {
                        this.checkAllChecked = false
                    }
                })
            },

            checkLicense({$event, key}) {
                this.checkedLicenses[key] = $event.target.checked ? 1 : 0

                if (!this.hasCheckValue(0)) {
                    this.checkAllChecked = true
                } else {
                    this.checkAllChecked = false
                }
            },

            checkAll($event) {
                let checkedLicenses = {}
                this.renewableLicenses.forEach(function(renewableLicense) {
                    let value

                    if (renewableLicense.type === 'cms-renewal') {
                        value = 1
                    } else {
                        value = $event.target.checked ? 1 : 0
                    }

                    checkedLicenses[this.getRenewableLicenseKey(renewableLicense)] = value
                }.bind(this))

                this.checkedLicenses = checkedLicenses
            },

            addToCart() {
                const renewableLicenses = this.renewableLicenses
                const items = []

                renewableLicenses.forEach(function(renewableLicense) {
                    if (!this.checkedLicenses[this.getRenewableLicenseKey(renewableLicense)]) {
                        return
                    }

                    if(!renewableLicense.key) {
                        return
                    }

                    const type = renewableLicense.type
                    const licenseKey = renewableLicense.key
                    const expiryDate = renewableLicense.expiryDate

                    const item = {
                        type,
                        licenseKey,
                        expiryDate,
                    }

                    items.push(item)
                }.bind(this))

                this.loading = true

                this.$store.dispatch('cart/addToCart', items)
                    .then(() => {
                        this.loading = false
                        this.$router.push({path: '/cart'})
                        this.$emit('addToCart')
                    })
                    .catch((errorMessage) => {
                        this.loading = false
                        this.$store.dispatch('app/displayError', errorMessage)
                    })
            },

            getRenewableLicenseKey(renewableLicense) {
                return renewableLicense.type + renewableLicense.key
            }
        },

        mounted() {
            this.$refs.checkAll.click()
            this.$refs.submitBtn.$el.focus()
        }
    }
</script>