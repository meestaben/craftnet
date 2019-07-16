<template>
    <div>
        <h1>Support Plan</h1>

        <div>
            <p>The support plan only covers emails received from this address:</p>
            <p><code>{{user.email}}</code></p>

            <div class="mt-4">
                <btn @click="changeEmail()">Change email</btn>
            </div>
        </div>

        <hr>

        <div class="flex -mx-4">
            <template v-for="(plan, planKey) in plans">
                <div class="card flex-1 mx-4 mb-3">
                    <div class="card-body text-center">
                        <h2>{{plan.name}}</h2>
                        <h3 class="text-3xl mb-4">${{plan.price}}</h3>

                        <ul class="feature-list">
                            <li v-for="feature in plan.features">
                                {{feature}}
                            </li>
                        </ul>

                        <div class="mt-4">
                            <template v-if="currentPlanKey === null">
                                <btn kind="primary" @click="changePlan(planKey)">Choose plan</btn>
                            </template>
                            <template v-else>
                                <template v-if="planKey === currentPlanKey">
                                    <btn kind="primary" :disabled="true">Current plan</btn>
                                    <div class="mt-2">
                                        <a @click.prevent="currentPlanKey = null">Cancel subscription</a>
                                    </div>
                                </template>
                                <template v-else>
                                    <template v-if="planKey > currentPlanKey">
                                        <btn kind="primary" @click="changePlan(planKey)">Upgrade</btn>
                                    </template>
                                    <template v-else>
                                        <btn kind="primary" @click="changePlan(planKey)">Downgrade</btn>
                                    </template>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script>
    import {mapState} from 'vuex'

    export default {
        data() {
            return {
                currentPlanKey: null,
                plans: [
                    {
                        name: "Free Support",
                        price: 0,
                        features: [
                            'Utilize Discord and Stack Exchange',
                            'While you can contact the team at CMS directly, there is no guaranteed response time',
                        ]
                    },
                    {
                        name: "Developer Support",
                        price: 75,
                        features: [
                            'Contact the team at Craft CMS directly via email',
                            'Guaranteed 12 hour or less time to first response (M-F)',
                        ]
                    },
                    {
                        name: "Priority Developer Support",
                        price: 750,
                        features: [
                            'Contact the team at Craft CMS directly via email',
                            'Tickets go to top of queue',
                            'Guaranteed 2 hour or less time to first response (M-F), 12 hours on Saturday, Sundays, and Holidays',
                        ]
                    },
                ]
            }
        },

        computed: {
            ...mapState({
                user: state => state.account.user,
            }),
        },

        methods: {
            changeEmail() {
                this.$router.push({path: '/account/settings'})
            },

            changePlan(planKey) {
                this.$store.commit('app/updateGlobalModalComponent', 'support-plan-modal')
                this.$store.commit('app/updateShowGlobalModal', true)
                this.currentPlanKey = planKey
            },
        }
    }
</script>

<style lang="scss">
    .feature-list {
        @apply .list-reset .text-left;

        li {
            @apply .border-t .py-2;

            &:last-child {
                @apply .border-b;
            }
        }
    }
</style>
