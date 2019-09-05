<template>
    <div>
        <spinner v-if="loading"></spinner>

        <template v-else>
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
                <tr v-for="(renewableLicense, key) in renewableLicenses(license, renew)" :key="key">
                    <td>
                        <input
                                type="checkbox"
                                :value="1"
                                :disabled="(key === 0 || !renewableLicense.key) ? true : false"
                                :checked="renewableLicense.key ? checkedLicenses[key] : false"
                                @input="checkLicense($event, key)" />
                    </td>
                    <td :class="{'text-grey': !renewableLicense.key}">{{ renewableLicense.description }}</td>
                    <td :class="{'text-grey': !renewableLicense.key}">{{ renewableLicense.expiresOn.date|moment('YYYY-MM-DD') }}</td>
                    <td :class="{'text-grey': !renewableLicense.key}">
                        {{ renewableLicense.expiryDate|moment('YYYY-MM-DD') }}
                    </td>
                    <td></td>
                </tr>
                </tbody>
            </table>

            <btn @click="$emit('back')">Back</btn>
            <btn ref="submitBtn" @click="addToCart()" kind="primary">Add to cart</btn>
        </template>
    </div>
</template>

<script>
    import helpers from '../../../../mixins/helpers'

    export default {
        mixins: [helpers],

        props: ['license', 'renew', 'checkedLicenses'],

        data() {
            return {
                loading: false,
                checkAllChecked: false
            }
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
                    this.renewableLicenses(this.license, this.renew).forEach(function(renewableLicense, key) {
                        checkedLicenses[key] = 1
                    })
                } else {
                    checkedLicenses[0] = 1
                }

                this.$emit('update:checkedLicenses', checkedLicenses)
            },

            addToCart() {
                const renewableLicenses = this.renewableLicenses(this.license, this.renew)
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
                
                this.$store.dispatch('cart/addToCart', items)
                    .then(() => {
                        this.$router.push({path: '/cart'})
                        this.$emit('addToCart')
                    })
                    .catch((errorMessage) => {
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
