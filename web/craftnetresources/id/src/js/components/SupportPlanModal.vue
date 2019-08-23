<template>
    <div class="support-plan-modal">
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
                <tr>
                    <td>Cycle End</td>
                    <td class="text-right">{{newSubscriptionInfo.cycleEnd}}</td>
                </tr>
                <tr>
                    <td>Upgrade cost</td>
                    <td class="text-right">{{newSubscriptionInfo.upgradeCost|currency}}</td>
                </tr>
            </template>
            </tbody>
        </table>

        <div>
            <btn @click="cancel()">Cancel</btn>
            <btn kind="primary" :disabled="!card" @click="switchPlan()">Upgrade Plan</btn>
            <template v-if="loading">
                <spinner class="ml-2"></spinner>
            </template>
        </div>
    </div>
</template>

<script>
    import {mapState, mapGetters} from 'vuex'

    export default {
        data() {
            return {
                loading: false,
            }
        },
        computed: {
            ...mapState({
                selectedPlanHandle: state => state.developerSupport.selectedPlanHandle,
                card: state => state.stripe.card,
            }),

            ...mapGetters({
                newSubscriptionInfo: 'developerSupport/newSubscriptionInfo',
                selectedPlan: 'developerSupport/selectedPlan',
            }),
        },

        methods: {
            cancel() {
                this.$emit('close')
                this.$store.commit('developerSupport/updateSelectedPlan', null)
            },

            switchPlan() {
                if (!this.card) {
                    return null
                }

                this.loading = true

                this.$store.dispatch('developerSupport/switchPlan', this.selectedPlanHandle)
                    .then(() => {
                        this.loading = false
                        this.$emit('close')
                        this.$store.dispatch('app/displayNotice', 'Support plan switched to ' + this.selectedPlanHandle + '.')
                    })
                    .catch((error) => {
                        this.loading = false
                        this.$emit('close')
                        const errorMessage = error ? error : 'Couldn’t switch support plan.'
                        this.$store.dispatch('app/displayError', errorMessage)
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

<style lang="scss">
    .support-plan-modal {
        width: 450px;
    }
</style>