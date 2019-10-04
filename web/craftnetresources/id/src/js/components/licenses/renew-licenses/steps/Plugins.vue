<template>
    <div>
        <p>Do you want to renew plugin licenses as well?</p>

        <table class="table mb-2">
            <thead>
            <tr>
                <td><input type="checkbox" v-model="checkAllChecked" ref="checkAll" @change="checkAll"></td>
                <th>Item</th>
                <th>Renewal Date</th>
                <th>New Renewal Date</th>
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
            </tbody>
        </table>

        <btn @click="$emit('back')">Back</btn>
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

        props: ['license', 'renew', 'checkedLicenses'],

        components: {
            RenewableLicenseTableRow,
        },

        data() {
            return {
                loading: false,
                checkAllChecked: false,
            }
        },


        computed: {
            ...mapGetters({
                cartItems: 'cart/cartItems',
            }),

            renewableLicenses() {
                return this.getRenewableLicenses(this.license, this.renew, this.cartItems)
            },

            hasCheckedLicenses() {
                return !!this.checkedLicenses.find(checked => checked === 1)
            },
        },

        methods: {
            checkLicense($event, key) {
                let checkedLicenses = JSON.parse(JSON.stringify(this.checkedLicenses))
                checkedLicenses[key] = $event.target.checked ? 1 : 0

                const allChecked = checkedLicenses.find(license => license === 0)

                if (allChecked === undefined) {
                    this.checkAllChecked = true
                } else {
                    this.checkAllChecked = false
                }

                this.$emit('update:checkedLicenses', checkedLicenses)
            },

            checkAll($event) {
                let checkedLicenses = []

                if ($event.target.checked) {
                    this.renewableLicenses.forEach(function(renewableLicense, key) {
                        if (renewableLicense.alreadyInCart) {
                            return false
                        }

                        checkedLicenses[key] = 1
                    }.bind(this))
                } else {
                    checkedLicenses[0] = 1
                }

                this.$emit('update:checkedLicenses', checkedLicenses)
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
