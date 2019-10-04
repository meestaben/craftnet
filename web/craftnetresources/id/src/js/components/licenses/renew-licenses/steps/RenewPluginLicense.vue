<template>
    <div>
        <spinner v-if="loading"></spinner>

        <template v-else>
            <dropdown v-model="renew" :options="extendUpdateOptions" />

            <table class="table mb-2">
                <thead>
                <tr>
                    <th>Item</th>
                    <th>Renewal Date</th>
                    <th>New Renewal Date</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>
                        {{ license.plugin.name }}
                        <div v-if="alreadyInCart" class="text-grey-dark">Already in cart.</div>
                    </td>
                    <td>{{ license.expiresOn.date|moment('YYYY-MM-DD') }}</td>
                    <td>{{ expiryDate }}</td>
                    <td>{{ price|currency }}</td>
                </tr>
                <tr>
                    <th colspan="3" class="text-right">Total</th>
                    <td><strong>{{ price|currency }}</strong></td>
                </tr>
                </tbody>
            </table>

            <btn @click="$emit('cancel')">Cancel</btn>
            <btn ref="submitBtn" kind="primary" @click="addToCart()" :disabled="alreadyInCart || addToCartLoading">Add to cart</btn>
            <spinner v-if="addToCartLoading"></spinner>
        </template>
    </div>
</template>

<script>
    import {mapGetters, mapActions} from 'vuex'

    export default {
        props: ['license'],

        data() {
            return {
                loading: false,
                addToCartLoading: false,
                renew: null,
                alreadyInCart: false,
            }
        },

        computed: {
            ...mapGetters({
                cartItems: 'cart/cartItems',
            }),

            renewalOptions() {
                return this.license.renewalOptions
            },

            extendUpdateOptions() {
                if (!this.renewalOptions) {
                    return []
                }

                let options = [];

                for (let i = 0; i < this.renewalOptions.length; i++) {
                    const renewalOption = this.renewalOptions[i]
                    const date = renewalOption.expiryDate
                    const formattedDate = this.$moment(date).format('YYYY-MM-DD')
                    const label = "Extend updates until " + formattedDate  + ' (' + this.$options.filters.currency(renewalOption.amount) +')'

                    options.push({
                        label: label,
                        value: i,
                    })
                }

                return options;
            },

            newExpiresOn() {
                const expiresOn = this.$moment(this.license.expiresOn.date)
                return expiresOn.add(this.renew, 'years')
            },

            price() {
                return (parseFloat(this.renew) + 1) * this.license.edition.renewalPrice;
            },

            expiryDate() {
                if (!this.renewalOptions) {
                    return null
                }

                if (!this.renewalOptions[this.renew]) {
                    return null
                }

                const date = this.renewalOptions[this.renew].expiryDate

                return this.$moment(date).format('YYYY-MM-DD')
            },
        },

        methods: {
            ...mapActions({
                getMeta: 'pluginStore/getMeta',
            }),

            addToCart() {
                const expiryDate = this.renewalOptions[this.renew].expiryDate
                const item = {
                    type: 'plugin-renewal',
                    licenseKey: this.license.key,
                    expiryDate: expiryDate,
                }

                this.addToCartLoading = true

                this.$store.dispatch('cart/addToCart', [item])
                    .then(() => {
                        this.addToCartLoading = false
                        this.$router.push({path: '/cart'})
                        this.$emit('addToCart')
                    })
                    .catch(errorMessage => {
                        this.addToCartLoading = false
                        this.$store.dispatch('app/displayError', errorMessage);
                    })
            },

            isAlreadyInCart() {
                const licenseKey = this.license.key
                const cartItems = this.cartItems

                return !!cartItems.find(item => item.lineItem.options.licenseKey === licenseKey)
            }
        },

        mounted() {
            // Make a copy of `alreadyInCart` value to prevent “Already in cart” text to show up when item gets added to the cart.
            this.alreadyInCart = this.isAlreadyInCart()

            // Get meta
            this.loading = true

            this.getMeta()
                .then(() => {
                    this.loading = false
                    this.renew = 0
                    this.$nextTick(() => {
                        this.$refs.submitBtn.$el.focus()
                    })
                })
                .catch(() => {
                    this.loading = false
                })
        }
    }
</script>
