<template>
    <modal :show.sync="show" @background-click="$emit('cancel')">
        <template slot="body">
            <h2>Cancel subscription</h2>
            <p>Are you sure you want to cancel your subscription?</p>

            <div>
                <btn ref="cancelBtn" :disabled="loading" @click="$emit('cancel')">Cancel</btn>
                <btn ref="submitBtn" kind="primary" :disabled="loading" @click="cancelSubscription(subscriptionUid)">Yes, cancel my subscription</btn>

                <template v-if="loading">
                    <spinner class="ml-2"></spinner>
                </template>
            </div>
        </template>
    </modal>
</template>

<script>
    import Modal from '../../Modal'

    export default {
        props: ['show', 'subscriptionUid'],

        data() {
            return {
                loading: false,
            }
        },

        watch: {
            show(show) {
                if (show) {
                    this.$nextTick(() => {
                        if (this.$refs.submitBtn) {
                            this.$refs.submitBtn.$el.focus()
                        }
                    })
                }
            }
        },

        components: {
            Modal,
        },

        methods: {
            cancelSubscription(subscriptionUid) {
                this.loading = true

                this.$store.dispatch('developerSupport/cancelSubscription', subscriptionUid)
                    .then(() => {
                        this.loading = false
                        this.$emit('close')
                        this.$store.dispatch('app/displayNotice', 'Subscription canceled.')
                    })
                    .catch((error) => {
                        this.loading = false
                        this.$emit('close')
                        const errorMessage = error ? error : 'Couldn’t switch support plan.'
                        this.$store.dispatch('app/displayError', errorMessage)
                    })
            },
        }
    }
</script>