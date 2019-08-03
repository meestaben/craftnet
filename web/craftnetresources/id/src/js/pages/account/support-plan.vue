<template>
    <div>
        <h1>Support Plan</h1>
        <p><a href="https://craftcms.com/contact">Learn more about Craft support options</a></p>

        <div class="flex mt-8 -mx-4">
            <template v-for="(plan, planKey) in plans">
                <div class="card flex-1 mx-4 mb-3">
                    <div class="card-body text-center">
                        <div class="plan-icon">
                            <img :src="staticImageUrl(plan.icon + '.svg')" />
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

                        <div v-if="planKey === 0">
                            <div class="my-6 pt-4">
                                <btn href="https://craftcms.com/discord" class="mr-2">Discord</btn>
                                <btn href="https://craftcms.stackexchange.com/">Stack Exchange</btn>
                            </div>

                            <p class="text-grey"><em>Comes standard with Craft Pro.</em></p>
                        </div>
                    </div>
                </div>
            </template>
        </div>


        <div class="mt-8 text-center text-grey">
            <p>The support plan only covers emails received from <code>{{user.email}}</code>.</p>
            <p>
                Go to your <router-link to="/account/settings">account’s settings</router-link> to change this email address.
            </p>
        </div>
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import helpers from '../../mixins/helpers'

    export default {
        mixins: [helpers],

        data() {
            return {
                currentPlanKey: null,
                plans: [
                    {
                        icon: 'support-plan-standard',
                        name: "Standard",
                        price: 0,
                        features: [
                            'Utilize Discord and Stack Exchange',
                            'While you can contact the team at CMS directly, there is no guaranteed response time',
                        ]
                    },
                    {
                        icon: 'support-plan-premium',
                        name: "Premium",
                        price: 75,
                        features: [
                            'Contact the team at Craft CMS directly via email',
                            'Guaranteed 12 hour or less time to first response (M-F)',
                        ]
                    },
                    {
                        icon: 'support-plan-priority',
                        name: "Priority",
                        price: 750,
                        features: [
                            'Contact the team at Craft CMS directly via email',
                            'Tickets go to top of queue',
                            'Guaranteed 2 hour or less time to first response (M-F), 12 hours on weekends',
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

        img {
            max-height: 50px;
        }

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
