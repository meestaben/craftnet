<template>
    <modal :show.sync="showModal" modal-type="wide">
        <template slot="body">
            <h2>Upgrade support plan</h2>

            <template v-if="!card">
                <p>Your billing info is missing. Go to <a @click="goToBilling">Account → Billing</a> to add a credit card and update billing infos.</p>
            </template>

            <table class="table border-b mt-6 mb-8">
                <thead class="hidden">
                <tr>
                    <th>Item</th>
                    <th>Price</th>
                </tr>
                </thead>
                <tbody>
                <tr v-if="selectedPlan">
                    <td>New Plan</td>
                    <td class="text-right">{{selectedPlan.name}}</td>
                </tr>
                <template v-if="newSubscriptionInfo">
                    <tr v-if="newSubscriptionInfo.cycleEnd">
                        <td>Cycle End</td>
                        <td class="text-right">{{newSubscriptionInfo.cycleEnd}}</td>
                    </tr>
                    <tr v-if="newSubscriptionInfo.upgradeCost">
                        <td>Upgrade cost</td>
                        <td class="text-right">{{newSubscriptionInfo.upgradeCost|currency}}</td>
                    </tr>
                </template>
                </tbody>
            </table>

            <div>
                <btn ref="cancelBtn" @click="cancel()">Cancel</btn>
                <btn kind="primary" :disabled="!card" @click="switchPlan()">Upgrade Plan</btn>
                <template v-if="loading">
                    <spinner class="ml-2"></spinner>
                </template>
            </div>
        </template>
    </modal>
</template>

<script>
    import {mapState, mapGetters} from 'vuex'
    import Modal from './Modal'

    export default {
        components: {
            Modal,
        },

        data() {
            return {
                loading: false,
            }
        },

        watch: {
            showModal(show) {
                if (show) {
                    this.$nextTick(() => {
                        this.$refs.cancelBtn.$el.focus()
                    })
                }
            }
        },

        computed: {
            ...mapState({
                selectedPlanHandle: state => state.developerSupport.selectedPlanHandle,
                card: state => state.stripe.card,
                showModal: state => state.developerSupport.showModal,
            }),

            ...mapGetters({
                newSubscriptionInfo: 'developerSupport/newSubscriptionInfo',
                selectedPlan: 'developerSupport/selectedPlan',
            }),
        },

        methods: {
            cancel() {
                this.closeModal()
            },

            switchPlan() {
                if (!this.card) {
                    return null
                }

                this.loading = true

                this.$store.dispatch('developerSupport/switchPlan', this.selectedPlanHandle)
                    .then(() => {
                        this.loading = false
                        this.$store.dispatch('app/displayNotice', 'Support plan switched to ' + this.selectedPlanHandle + '.')
                        this.closeModal()
                    })
                    .catch((error) => {
                        this.loading = false
                        const errorMessage = error.response && error.response.data.error ? error.response.data.error : (error ? error : 'Couldn’t switch support plan.')
                        this.$store.dispatch('app/displayError', errorMessage)
                        this.closeModal()
                    })
            },

            goToBilling(ev) {
                ev.preventDefault()
                this.$router.push({path: '/account/billing'})
                this.closeModal()
            },

            closeModal() {
                this.$store.commit('developerSupport/updateShowModal', false)
                this.$store.commit('developerSupport/updateSelectedPlan', null)
            }
        },
    }
</script>
