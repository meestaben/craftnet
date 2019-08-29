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
                        <plan :plan="plan" :key="'plan-'+planKey" @selectPlan="onSelectPlan" @cancelSubscription="onCancelSubscription"></plan>
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
    import Plan from '../../components/developer-support/Plan'

    export default {
        components: {
            Plan,
        },

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
                currentPlanHandle: 'developerSupport/currentPlanHandle',
                subscriptionInfoSubscriptionData: 'developerSupport/subscriptionInfoSubscriptionData',
            }),
        },

        methods: {
            onSelectPlan(plan) {
                this.$store.commit('developerSupport/updateSelectedPlan', plan.handle)
                this.$store.commit('developerSupport/updateShowModal', true)
            },

            onCancelSubscription() {
                if (!window.confirm("Are you sure you want to cancel your subscription?")) {
                    return false
                }

                const currentSubscription = this.subscriptionInfoSubscriptionData(this.currentPlanHandle)

                this.loading = true

                const defaultPlanHandle = 'basic'

                this.$store.dispatch('developerSupport/cancelSubscription', currentSubscription.uid)
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
