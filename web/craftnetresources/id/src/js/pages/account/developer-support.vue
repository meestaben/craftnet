<template>
    <div>
        <div class="text-center">
            <h1>Choose Your Developer Support Plan</h1>
            <p class="-mt-2"><a href="https://craftcms.com/contact">Learn more about Craft support options</a></p>
        </div>

        <div class="mx-auto max-w-2xl">
            <div class="flex mt-8 -mx-4">
                <template v-for="plan in plans">
                    <div class="card flex flex-1 mx-4 mb-3">
                        <div class="support-plan-wrapper card-body text-center">
                            <div class="support-plan">
                                <div class="details">
                                    <div class="plan-icon">
                                        <img :src="staticImageUrl(plan.icon + '.svg')" />
                                    </div>

                                    <h2 class="mb-6">{{plan.name}}</h2>

                                    <ul class="feature-list">
                                        <li v-for="feature in plan.features">
                                            <icon icon="check" /> <span>{{feature}}</span>
                                        </li>
                                    </ul>
                                </div>

                                <div class="actions">
                                    <div v-if="plan.price > 0" class="my-4">
                                        <h3 class="text-3xl">${{plan.price}}</h3>
                                        <p class="text-grey">per month</p>
                                    </div>

                                    <div v-if="plan.price > 0" class="mt-4">
                                        <template v-if="plan.handle === currentPlan">
                                            <btn kind="primary" :disabled="true">Current plan</btn>
                                            <div class="mt-2">
                                                <a @click.prevent="cancelSubscription">Cancel subscription</a>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <btn kind="primary" @click="changePlan(plan)">Select this plan</btn>
                                        </template>
                                    </div>

                                    <div v-if="plan.handle === 'standard'">
                                        <p class="mb-0 text-grey"><em>Comes standard with Craft Pro.</em></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
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
    import {mapState, mapGetters} from 'vuex'
    import helpers from '../../mixins/helpers'

    export default {
        mixins: [helpers],

        computed: {
            ...mapState({
                user: state => state.account.user,
                subscriptionInfo: state => state.developerSupport.subscriptionInfo,
                plans: state => state.developerSupport.plans,
            }),

            ...mapGetters({
                currentPlan: 'developerSupport/currentPlan',
            }),
        },

        methods: {
            changePlan(plan) {
                this.$store.commit('app/updateGlobalModalComponent', 'support-plan-modal')
                this.$store.commit('app/updateShowGlobalModal', true)
                this.$store.commit('developerSupport/updateCurrentPlan', plan.handle)
            },
            
            cancelSubscription() {
                this.$store.commit('developerSupport/updateCurrentPlan', null)
            }
        }
    }
</script>

<style lang="scss">
    .support-plan-wrapper {
        @apply .h-full .flex-1 .flex;

        .support-plan {
            @apply .flex .flex-col .justify-between;

            .actions {
                @apply .py-4;
            }
        }
    }

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
