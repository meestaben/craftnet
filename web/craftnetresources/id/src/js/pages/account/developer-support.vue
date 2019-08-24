<template>
    <div>
        <div class="text-center">
            <h1>Choose Your Developer Support Plan</h1>
            <p class="-mt-2"><a href="https://craftcms.com/contact">Learn more about Craft support options</a></p>
        </div>

        <template v-if="loading">
            <div class="mt-6 text-center">
                <spinner></spinner>
            </div>
        </template>

        <template v-if="error">
            <div class="mt-6 text-center">
                <p class="text-red">{{error}}</p>
            </div>
        </template>

        <template v-if="!loading && subscriptionInfo">
            <div class="mx-auto max-w-2xl">
                <div class="lg:flex mt-8 -mx-4">
                    <template v-for="(plan, planKey) in plans">
                        <div class="card lg:flex lg:flex-1 mx-4 mb-3" :key="'plan-'+planKey">
                            <div class="support-plan-wrapper card-body text-center lg:h-full lg:flex-1 lg:flex">
                                <div class="support-plan flex flex-col justify-between">
                                    <div class="details">
                                        <div class="plan-icon">
                                            <img :src="staticImageUrl(plan.icon + '.svg')" />
                                        </div>

                                        <h2 class="mb-6">{{plan.name}}</h2>

                                        <ul class="feature-list">
                                            <li v-for="(feature, featureKey) in plan.features" :key="planKey+'-features-'+featureKey">
                                                <icon icon="check" /> <span>{{feature}}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="actions py-4">
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
                                                <btn kind="primary" @click="selectPlan(plan)">Select this plan</btn>
                                            </template>
                                        </div>

                                        <div v-if="plan.handle === 'basic'">
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
        </template>
    </div>
</template>

<script>
    import {mapState, mapGetters} from 'vuex'
    import helpers from '../../mixins/helpers'

    export default {
        mixins: [helpers],

        data() {
            return {
                error: null,
                loading: false,
            }
        },

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
            selectPlan(plan) {
                this.$store.commit('app/updateGlobalModalComponent', 'support-plan-modal')
                this.$store.commit('app/updateShowGlobalModal', true)
                this.$store.commit('developerSupport/updateSelectedPlan', plan.handle)
            },

            cancelSubscription() {
                if (!window.confirm("Are you sure you want to cancel your subscription?")) {
                    return false
                }

                this.loading = true

                const defaultPlanHandle = 'basic'

                this.$store.dispatch('developerSupport/switchPlan', defaultPlanHandle)
                    .then(() => {
                        this.loading = false
                        this.$emit('close')
                        this.$store.dispatch('app/displayNotice', 'Support plan switched to ' + defaultPlanHandle + '.')
                    })
                    .catch((error) => {
                        this.loading = false
                        this.$emit('close')
                        const errorMessage = error ? error : 'Couldn’t switch support plan.'
                        this.$store.dispatch('app/displayError', errorMessage)
                    })
            }
        },

        mounted() {
            this.loading = true

            this.$store.dispatch('developerSupport/getSubscriptionInfo')
                .then(() => {
                    this.loading = false
                })
                .catch((error) => {
                    const errorMessage = error.response.data && error.response.data.error ? error.response.data.error : 'Couldn’t get subscription info.'
                    this.error = errorMessage
                    this.loading = false
                })
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
