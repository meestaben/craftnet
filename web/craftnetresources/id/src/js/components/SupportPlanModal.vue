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
            <btn kind="primary" :disabled="!card" @click="changePlan()">Upgrade Plan</btn>
        </div>
    </div>
</template>

<script>
    import {mapState, mapGetters} from 'vuex'

    export default {

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

            changePlan() {
                if (!this.card) {
                    return null
                }

                this.$store.commit('developerSupport/updateCurrentPlan', this.selectedPlanHandle)
                this.$emit('close')
            },

            goToBilling(ev) {
                ev.preventDefault()
                this.$router.push({path: '/account/billing'})
                this.$emit('close')
            },
        }
    }
</script>

<style lang="scss">
    .support-plan-modal {
        width: 450px;
    }
</style>