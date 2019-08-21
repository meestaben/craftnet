<template>
    <div>
        <h2>Upgrade support plan</h2>
        <p>Your billing info is missing. Go to <router-link to="/account/billing">Account → Billing</router-link> to add a credit card and update billing infos.</p>

        <table class="table border-b mt-6 mb-8">
            <thead class="hidden">
            <tr>
                <th>Item</th>
                <th>Price</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>New Plan</td>
                <td class="text-right">{{selectedPlan}}</td>
            </tr>
            <template v-if="newSubscriptionInfo">
                <tr>
                    <td>Cycle End</td>
                    <td class="text-right">{{newSubscriptionInfo.cycleEnd}}</td>
                </tr>
                <tr>
                    <td>Upgrade cost</td>
                    <td class="text-right">{{newSubscriptionInfo.upgradeCost}}</td>
                </tr>
            </template>
            </tbody>
        </table>

        <div>
            <btn @click="cancel()">Cancel</btn>
            <btn kind="primary" @click="changePlan()">Upgrade Plan</btn>
        </div>
    </div>
</template>

<script>
    import {mapState, mapGetters} from 'vuex'

    export default {

        computed: {
            ...mapState({
                selectedPlan: state => state.developerSupport.selectedPlan,
            }),

            ...mapGetters({
                newSubscriptionInfo: 'developerSupport/newSubscriptionInfo',
            }),
        },

        methods: {
            cancel() {
                this.$emit('close')
                this.$store.commit('developerSupport/updateSelectedPlan', null)
            },

            changePlan() {
                this.$store.commit('developerSupport/updateCurrentPlan', this.selectedPlan)
                this.$emit('close')
            }
        }
    }
</script>