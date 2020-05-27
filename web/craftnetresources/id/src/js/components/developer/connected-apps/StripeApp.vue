<template>
    <connected-app
            :name="appType.name"
            :description="'Connect to your ' + appType.name + ' account.'"
            connected-description="Automatic Stripe payouts only occur for U.S., European, Australian, and New Zealand based accounts."
            :icon="staticImageUrl('' + appType.handle + '.svg')"
            :account-name="(stripeAccount ? stripeAccount.display_name : '')"
            :connected="stripeAccount"
            :buttonLoading="disconnectLoading"
            @connect="connect()"
            @disconnect="disconnect()"
    ></connected-app>
</template>

<script>
    import {mapState} from 'vuex'
    import ConnectedApp from './ConnectedApp'
    import helpers from '../../../mixins/helpers'

    export default {
        mixins: [helpers],

        data() {
            return {
                appType: {
                    name: "Stripe",
                    handle: 'stripe',
                },
                disconnectLoading: false,
            }
        },

        components: {
            ConnectedApp
        },

        computed: {
            ...mapState({
                stripeAccount: state => state.stripe.stripeAccount,
            }),

            stripeConnectUrl() {
                return window.stripeConnectUrl;
            }
        },

        methods: {
            /**
             * Connect to Stripe account.
             */
            connect() {
                window.location.href = this.stripeConnectUrl
            },

            /**
             * Disconnect Stripe account.
             */
            disconnect() {
                this.disconnectLoading = true;

                this.$store.dispatch('stripe/disconnectStripeAccount').then(() => {
                    this.disconnectLoading = false;
                    this.$store.dispatch('app/displayNotice', 'Stripe account removed.');
                });
            }
        },
    }
</script>
