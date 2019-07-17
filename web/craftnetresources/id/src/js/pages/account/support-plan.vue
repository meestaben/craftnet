<template>
    <div>
        <h1>Support Plan</h1>

        <p>The support plan only covers emails received from this address:</p>
        <p><code>{{user.email}}</code></p>
        <p>
            <router-link to="/account/settings">Change email</router-link>
        </p>

        <hr>

        <div class="flex -mx-4">
            <template v-for="(plan, planKey) in plans">
                <div class="card flex-1 mx-4 mb-3">
                    <div class="card-body text-center">
                        <div class="plan-icon">
                            <icon :icon="plan.icon" />
                        </div>

                        <h2 class="mb-6">{{plan.name}}</h2>

                        <ul class="feature-list">
                            <li v-for="feature in plan.features">
                                <icon icon="check" /> <span>{{feature}}</span>
                            </li>
                        </ul>

                        <div v-if="plan.price > 0" class="my-4">
                            <h3 class="text-3xl">${{plan.price}}</h3>
                            <p class="text-grey">/month per seat</p>
                        </div>

                        <div v-if="plan.price > 0" class="mt-4">
                            <template v-if="planKey === currentPlanKey">
                                <btn kind="primary" :disabled="true">Current plan</btn>
                                <div class="mt-2">
                                    <a @click.prevent="currentPlanKey = null">Cancel subscription</a>
                                </div>
                            </template>
                            <template v-else>
                                <btn kind="primary" @click="changePlan(planKey)">Select this plan</btn>
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
                        icon: 'book',
                        name: "Community Support",
                        price: 0,
                        features: [
                            'Utilize Discord and Stack Exchange',
                            'While you can contact the team at CMS directly, there is no guaranteed response time',
                        ]
                    },
                    {
                        icon: 'user',
                        name: "Developer Support",
                        price: 75,
                        features: [
                            'Contact the team at Craft CMS directly via email',
                            'Guaranteed 12 hour or less time to first response (M-F)',
                        ]
                    },
                    {
                        icon: 'exclamation-triangle',
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
            changePlan(planKey) {
                this.$store.commit('app/updateGlobalModalComponent', 'support-plan-modal')
                this.$store.commit('app/updateShowGlobalModal', true)
                this.currentPlanKey = planKey
            },
        }
    }
</script>

<style lang="scss">
    .plan-icon {
        @apply .py-6;

        svg.c-icon {
            @apply .text-grey;

            width: 50px;
            height: 50px;
        }
    }

    .feature-list {
        @apply .list-reset .text-left;

        li {
            @apply .border-t .py-2 .flex;

            .c-icon {
                @apply .w-6 .mr-1;
                top: 4px;
            }

            span {
                @apply .flex-1;
            }

            &:last-child {
                @apply .border-b;
            }
        }
    }
</style>
