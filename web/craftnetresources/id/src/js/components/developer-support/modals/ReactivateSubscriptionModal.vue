<template>
    <modal :show.sync="show" @close="$emit('cancel')">
        <template slot="body">
            <h2>Reactivate your subscription</h2>
            <p>Are you sure you want to reactivate your subscription?</p>

            <div>
                <btn ref="cancelBtn" :disabled="loading" @click="$emit('cancel')">Cancel</btn>
                <btn ref="submitBtn" kind="primary" :disabled="loading" @click="reactivateSubscription(subscriptionUid)">Reactivate</btn>

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
            reactivateSubscription(subscriptionUid) {
                this.loading = true

                this.$store.dispatch('developerSupport/reactivateSubscription', subscriptionUid)
                    .then(() => {
                        this.loading = false
                        this.$emit('close')
                        this.$store.dispatch('app/displayNotice', 'Subscription reactivated.')
                    })
                    .catch((error) => {
                        this.loading = false
                        this.$emit('close')
                        const errorMessage = error ? error : 'Couldn’t reactivate subscription.'
                        this.$store.dispatch('app/displayError', errorMessage)
                    })
            }
        }
    }
</script>