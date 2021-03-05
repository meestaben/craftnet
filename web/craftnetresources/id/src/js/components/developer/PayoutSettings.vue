<template>
    <form v-if="userDraft" @submit.prevent="save()">
        <div class="card mt-6">
            <div class="card-body">
                <h4>PayPal Payouts</h4>
                <p class="text-secondary">Provide your PayPal email, which will be used for Plugin Store payouts in the event that there was a problem transferring via Stripe.</p>
                <textbox class="mt-4" id="paypalEmail" label="PayPal Email Address" v-model="userDraft.payPalEmail" :errors="errors.payPalEmail" />
                <btn kind="primary" type="submit" :disabled="loading" :loading="loading">Save</btn>
            </div>
        </div>
    </form>
</template>

<script>
import {mapState} from 'vuex'

export default {
    data() {
        return {
            loading: false,
            photoLoading: false,
            userDraft: {},
            password: '',
            newPassword: '',
            errors: {},
        }
    },

    computed: {
        ...mapState({
            user: state => state.account.user,
        }),
    },

    methods: {
        /**
         * Save the settings.
         */
        save() {
            this.errors = {}
            this.loading = true

            this.$store.dispatch('account/saveUser', {
                    id: this.userDraft.id,
                    payPalEmail: this.userDraft.payPalEmail,
                })
                .then(() => {
                    this.loading = false
                    this.$store.dispatch('app/displayNotice', 'Payout settings saved.')

                })
                .catch(response => {
                    this.loading = false
                    this.errors = response.data.errors
                    this.$store.dispatch('app/displayError', 'Couldn’t save payout settings.')
                })
        }
    },

    mounted() {
        this.userDraft = JSON.parse(JSON.stringify(this.user))
    }
}
</script>
