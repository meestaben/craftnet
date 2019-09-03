<template>
    <modal :show="show" modal-type="wide" @close="$emit('close')">
        <template v-if="selectedPlan" slot="body">
            <template v-if="subscriptionMode === 'subscribe'">
                <h2>Subscribe to this support plan</h2>
            </template>
            <template v-else-if="selectedPlan.price > currentPlan.price">
                <h2>Upgrade support plan</h2>
            </template>
            <template v-else>
                <h2>Switch support plan</h2>
                <p>Your plan will switch to the {{selectedPlan.name}} tier at the end of the billing cycle.</p>
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
                <btn ref="cancelBtn" :disabled="loading" @click="$emit('close')">Cancel</btn>

                <template v-if="subscriptionMode === 'subscribe'">
                    <btn  ref="submitBtn" kind="primary" :disabled="!card || loading" @click="subscribePlan()">
                        Subscribe to this plan
                    </btn>
                </template>
                <template v-else>
                    <btn  ref="submitBtn" kind="primary" :disabled="!card || loading" @click="switchPlan()">
                        <template v-if="selectedPlan.price > currentPlan.price">
                            Upgrade plan
                        </template>
                        <template v-else>
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
    import Modal from '../../Modal'

    export default {
        props: ['show', 'selectedPlanHandle'],

        components: {
            Modal,
        },

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

        computed: {
            ...mapState({
                card: state => state.stripe.card,
                plans: state => state.developerSupport.plans,
            }),

            ...mapGetters({
                currentPlan: 'developerSupport/currentPlan',
            }),

            subscriptionInfoPlan() {
                return this.$store.getters['developerSupport/subscriptionInfoPlan'](this.selectedPlanHandle)
            },

            selectedPlan() {
                if (!this.selectedPlanHandle) {
                    return null
                }

                return this.plans.find(plan => plan.handle === this.selectedPlanHandle)
            },

            subscriptionMode() {
                const proSubscription = this.$store.getters['developerSupport/subscriptionInfoSubscriptionData']('pro')
                const premiumSubscription = this.$store.getters['developerSupport/subscriptionInfoSubscriptionData']('premium')

                switch (this.selectedPlanHandle) {
                    case 'pro':
                        if ((proSubscription.status === 'inactive' && premiumSubscription.status === 'inactive') || premiumSubscription.status === 'expiring') {
                            return 'subscribe'
                        }
                        break
                    case 'premium':
                        if ((proSubscription.status === 'inactive' && premiumSubscription.status === 'inactive')) {
                            return 'subscribe'
                        }
                        break
                }

                return 'switch'
            }
        },

        methods: {
            switchPlan() {
                if (!this.card) {
                    return null
                }

                this.loading = true

                this.$store.dispatch('developerSupport/switchPlan', this.selectedPlanHandle)
                    .then(() => {
                        this.loading = false
                        this.$store.dispatch('app/displayNotice', 'Support plan switched to ' + this.selectedPlanHandle + '.')
                        this.$emit('close')
                    })
                    .catch((error) => {
                        this.loading = false
                        const errorMessage = error ? error : 'Couldn’t switch support plan.'
                        this.$store.dispatch('app/displayError', errorMessage)
                        this.$emit('close')
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
                        this.$emit('close')
                    })
                    .catch((error) => {
                        this.loading = false
                        const errorMessage = error ? error : 'Couldn’t subscribe to support plan.'
                        this.$store.dispatch('app/displayError', errorMessage)
                        this.$emit('close')
                    })
            },

            goToBilling(ev) {
                ev.preventDefault()
                this.$router.push({path: '/account/billing'})
                this.$emit('close')
            },
        },
    }
</script>
