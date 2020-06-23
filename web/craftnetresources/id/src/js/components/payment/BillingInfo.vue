<template>
    <div>
        <textbox placeholder="First Name" id="first-name" v-model="billingInfo.firstName" :errors="errors['billingAddress.firstName']" />
        <textbox placeholder="Last Name" id="last-name" v-model="billingInfo.lastName" :errors="errors['billingAddress.lastName']" />
        <textbox placeholder="Business Name" id="business-name" v-model="billingInfo.businessName" :errors="errors['billingAddress.businessName']" />
        <textbox placeholder="Business Tax ID" id="business-tax-id" v-model="billingInfo.businessTaxId" :errors="errors['billingAddress.businessTaxId']" />
        <textbox placeholder="Address 1" id="address-1" v-model="billingInfo.address1" :errors="errors['billingAddress.address1']" />
        <textbox placeholder="Address 2" id="address-2" v-model="billingInfo.address2" :errors="errors['billingAddress.address2']" />

        <div class="md:flex -mx-2">
            <div class="md:w-1/2 px-2">
                <textbox placeholder="City" id="city" v-model="billingInfo.city" :errors="errors['billingAddress.city']" />
            </div>
            <div class="md:w-1/2 px-2">
                <textbox placeholder="Zip Code" id="zip-code" v-model="billingInfo.zipCode" :errors="errors['billingAddress.zipCode']" />
            </div>
        </div>
        <div class="md:flex -mx-2">
            <div class="md:w-1/2 px-2">
                <template v-if="loading">
                    <spinner></spinner>
                </template>
                <template v-else>
                    <dropdown :fullwidth="true" :options="countryOptions" v-model="billingInfo.country" id="country" :errors="errors['billingAddress.country']" @input="onCountryChange" />
                </template>
            </div>
            <div class="md:w-1/2 px-2">
                <template v-if="!loading">
                    <dropdown :fullwidth="true" :options="stateOptions(billingInfo.country)" v-model="billingInfo.state" id="state" :errors="errors['billingAddress.state']" @input="onStateChange" />
                </template>
            </div>
        </div>
    </div>
</template>

<script>
    import {mapGetters, mapActions} from 'vuex'

    export default {
        props: ['billingInfo', 'errors'],

        data() {
            return {
                loading: false,
            }
        },

        computed: {
            ...mapGetters({
                countryOptions: 'craftId/countryOptions',
                stateOptions: 'craftId/stateOptions',
            }),
        },

        methods: {
            ...mapActions({
                getCountries: 'craftId/getCountries',
            }),

            onCountryChange() {
                this.billingInfo.state = null
                const stateOptions = this.stateOptions(this.billingInfo.country)

                if (stateOptions.length) {
                    this.billingInfo.state = stateOptions[0].value
                }
            },

            onStateChange(value) {
                const billingInfo = JSON.parse(JSON.stringify(this.billingInfo))
                billingInfo.state = value

                this.$emit('update:billingInfo', billingInfo)
            }
        },

        mounted() {
            this.loading = true

            this.getCountries()
                .then(() => {
                    this.loading = false
                })
                .catch(() => {
                    this.loading = false
                    this.$store.dispatch('app/displayNotice', 'Couldn’t get countries.');
                })
        }
    }
</script>
