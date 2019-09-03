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
                    <th>Subtotal</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>{{ license.plugin.name }}</td>
                    <td>{{ license.expiresOn.date|moment('YYYY-MM-DD') }}</td>
                    <td>{{ expiryDate }}</td>
                    <td>{{ price|currency }}</td>
                </tr>
                </tbody>
            </table>

            <btn @click="$emit('cancel')">Cancel</btn>
            <btn ref="submitBtn" kind="primary" @click="addToCart()">Add to cart</btn>
        </template>
    </div>
</template>

<script>
    import {mapActions} from 'vuex'

    export default {
        props: ['license'],

        data() {
            return {
                loading: false,
                renew: null,
            }
        },

        computed: {
            expiryDateOptions() {
                return this.license.expiryDateOptions
            },

            extendUpdateOptions() {
                if (!this.expiryDateOptions) {
                    return []
                }

                let options = [];

                for (let i = 0; i < this.expiryDateOptions.length; i++) {
                    const expiryDateOption = this.expiryDateOptions[i]
                    const date = expiryDateOption[1]
                    const formattedDate = this.$moment(date).format('YYYY-MM-DD')
                    const label = "Extend updates until " + formattedDate

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
                if (!this.expiryDateOptions) {
                    return null
                }

                if (!this.expiryDateOptions[this.renew]) {
                    return null
                }

                const date = this.expiryDateOptions[this.renew][1]

                return this.$moment(date).format('YYYY-MM-DD')
            }
        },

        methods: {
            ...mapActions({
                getMeta: 'pluginStore/getMeta',
            }),

            addToCart() {
                const expiryDate = this.expiryDateOptions[this.renew][0]
                const item = {
                    type: 'plugin-renewal',
                    licenseKey: this.license.key,
                    expiryDate: expiryDate,
                }

                this.$store.dispatch('cart/addToCart', [item])
                    .then(() => {
                        this.$router.push({path: '/cart'})
                        this.$emit('addToCart')
                    })
                    .catch(errorMessage => {
                        this.$store.dispatch('app/displayError', errorMessage);
                    })
            },
        },

        mounted() {
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
