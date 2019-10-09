<template>
    <div>
        <dropdown :value="renew" @input="onRenewChange" :options="renewOptions" />

        <p>Do you want to renew plugin licenses as well?</p>

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
                    v-for="(renewableLicense, key) in renewableLicenses"
                    :renewableLicense="renewableLicense"
                    :key="key"
                    :itemKey="key"
                    :isChecked="checkedLicenses[key]"
                    @checkLicense="checkLicense($event, key)"
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
                checkedLicenses: [],
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

                    // plugin amounts
                    this.renewableLicenses.forEach((renewableLicense, j) => {
                        // only keep checked licenses
                        if (this.checkedLicenses[j]) {
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
                return !!this.checkedLicenses.find(checked => checked === 1)
            },

            total() {
                let total = 0

                this.renewableLicenses.forEach(function(renewableLicense , key) {
                    if (!this.checkedLicenses[key]) {
                        return
                    }

                    total += renewableLicense.amount
                }.bind(this))

                return total
            }
        },

        methods: {
            onRenewChange($event) {
                this.renew = $event

                let checkedLicenses = JSON.parse(JSON.stringify(this.checkedLicenses))
                checkedLicenses.splice(this.renewableLicenses.length)
                this.checkedLicenses = checkedLicenses

                this.$nextTick(() => {
                    if (checkedLicenses.length < this.renewableLicenses.length) {
                        this.checkAllChecked = false
                    }
                })
            },

            checkLicense($event, key) {
                let checkedLicenses = JSON.parse(JSON.stringify(this.checkedLicenses))
                checkedLicenses[key] = $event.target.checked ? 1 : 0

                const allChecked = checkedLicenses.find(license => license === 0)

                if (allChecked === undefined) {
                    this.checkAllChecked = true
                } else {
                    this.checkAllChecked = false
                }

                this.checkedLicenses = checkedLicenses
            },

            checkAll($event) {
                let checkedLicenses = []

                if ($event.target.checked) {
                    this.renewableLicenses.forEach(function(renewableLicense, key) {
                        checkedLicenses[key] = 1
                    }.bind(this))
                } else {
                    if (!$event.target.disabled) {
                        checkedLicenses[0] = this.checkedLicenses[0]
                    }
                }

                this.checkedLicenses = checkedLicenses
            },

            addToCart() {
                const renewableLicenses = this.renewableLicenses
                const items = []

                renewableLicenses.forEach(function(renewableLicense, key) {
                    if (!this.checkedLicenses[key]) {
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
        },

        mounted() {
            this.$refs.checkAll.click()
            this.$refs.submitBtn.$el.focus()
        }
    }
</script>