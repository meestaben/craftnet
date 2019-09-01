<template>
    <modal :show.sync="showModal" modal-type="wide" @background-click="cancel">
        <template v-if="selectedPlan" slot="body">
            <template v-if="subscriptionMode === 'subscribe'">
                <h2>Subscribe to this support plan</h2>
            </template>
            <template v-else-if="selectedPlan.price > currentPlan.price">
                <h2>Upgrade support plan</h2>
            </template>
            <template v-else>
                <h2>Switch support plan</h2>
                <p>Your plan will switch to the {{selectedPlan.name}} tier at the end of the billing cycle</p>
            </template>

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
                <tr>
                    <td>Current Plan</td>
                    <td class="text-right">{{currentPlan.name}}</td>
                </tr>
                <tr>
                    <td>New Plan</td>
                    <td class="text-right">{{selectedPlan.name}}</td>
                </tr>
                <tr>
                    <td>Price</td>
                    <td class="text-right">
                        {{subscriptionInfoPlan.cost.switch|currency}}<br />
                        <small class="text-grey-dark">Then {{subscriptionInfoPlan.cost.recurring|currency}} every month</small>
                    </td>
                </tr>
                </tbody>
            </table>

            <div>
                <btn ref="cancelBtn" @click="cancel">Cancel</btn>

                <template v-if="subscriptionMode === 'subscribe'">
                    <btn  ref="submitBtn" kind="primary" :disabled="!card" @click="subscribePlan()">
                        Subscribe to this plan
                    </btn>
                </template>
                <template v-else>
                    <btn  ref="submitBtn" kind="primary" :disabled="!card" @click="switchPlan()">
                        <template v-if="selectedPlan.price > currentPlan.price">
                            Upgrade plan
                        </template>
                        <template>
                            Switch plan
                        </template>
                    </btn>
                </template>

                <template v-if="loading">
                    <spinner class="ml-2"></spinner>
                </template>
            </div>
        </template>
    </modal>
</template>

<script>
    import {mapState, mapGetters} from 'vuex'
    import Modal from '../Modal'

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
                        this.$refs.submitBtn.$el.focus()
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
                selectedPlan: 'developerSupport/selectedPlan',
                currentPlan: 'developerSupport/currentPlan',
                subscriptionMode: 'developerSupport/subscriptionMode',
            }),

            subscriptionInfoPlan() {
                return this.$store.getters['developerSupport/subscriptionInfoPlan'](this.selectedPlanHandle)
            },
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
                        const errorMessage = error ? error : 'Couldn’t switch support plan.'
                        this.$store.dispatch('app/displayError', errorMessage)
                        this.closeModal()
                    })
            },

            subscribePlan() {
                if (!this.card) {
                    return null
                }

                this.loading = true

                this.$store.dispatch('developerSupport/subscribe', this.selectedPlanHandle)
                    .then(() => {
                        this.loading = false
                        this.$store.dispatch('app/displayNotice', 'Subscribed to ' + this.selectedPlanHandle + ' plan.')
                        this.closeModal()
                    })
                    .catch((error) => {
                        this.loading = false
                        const errorMessage = error ? error : 'Couldn’t subscribe to support plan.'
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
