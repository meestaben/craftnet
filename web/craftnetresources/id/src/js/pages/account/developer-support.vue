<template>
    <div>
        <div class="text-center">
            <h1>Choose Your Developer Support Plan</h1>
            <p class="-mt-2"><a href="https://craftcms.com/support-services">Learn more about Craft support options</a></p>
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
                        <plan
                                :plan="plan"
                                :key="'plan-'+planKey"
                                @selectPlan="subscribeModalShow"
                                @cancelSubscription="cancelSubscriptionModalShow"
                                @reactivateSubscription="reactivateSubscriptionModalShow"
                        ></plan>
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

        <!-- Modals -->
        <subscribe-modal
                :show="showSubscribeModal"
                :selectedPlanHandle="selectedPlanHandle"
                @cancel="subscribeModalHide"
                @close="subscribeModalHide"
        ></subscribe-modal>

        <cancel-subscription-modal
                :show="showCancelSubscriptionModal"
                :subscriptionUid="subscriptionUid"
                @cancel="cancelSubscriptionModalHide"
                @close="cancelSubscriptionModalHide"
        ></cancel-subscription-modal>

        <reactivate-subscription-modal
                :show="showReactivateSubscriptionModal"
                :subscriptionUid="subscriptionUid"
                @cancel="reactivateSubscriptionModalHide"
                @close="reactivateSubscriptionModalHide"
        ></reactivate-subscription-modal>
    </div>
</template>

<script>
    import {mapState, mapGetters} from 'vuex'
    import Plan from '../../components/developer-support/Plan'
    import SubscribeModal from '../../components/developer-support/modals/SubscribeModal'
    import CancelSubscriptionModal from '../../components/developer-support/modals/CancelSubscriptionModal'
    import ReactivateSubscriptionModal from '../../components/developer-support/modals/ReactivateSubscriptionModal'

    export default {
        components: {
            Plan,
            SubscribeModal,
            CancelSubscriptionModal,
            ReactivateSubscriptionModal,
        },

        data() {
            return {
                error: null,
                loading: false,

                subscriptionUid: null,
                showCancelSubscriptionModal: false,
                showReactivateSubscriptionModal: false,

                selectedPlanHandle: null,
                showSubscribeModal: false,
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
            subscribeModalShow(plan) {
                this.selectedPlanHandle = plan.handle
                this.showSubscribeModal = true
            },

            subscribeModalHide() {
                this.selectedPlanHandle = null
                this.showSubscribeModal = false
            },

            cancelSubscriptionModalShow(subscriptionUid) {
                this.subscriptionUid = subscriptionUid
                this.showCancelSubscriptionModal = true
            },

            cancelSubscriptionModalHide() {
                this.subscriptionUid = null
                this.showCancelSubscriptionModal = false
            },

            reactivateSubscriptionModalShow(subscriptionUid) {
                this.subscriptionUid = subscriptionUid
                this.showReactivateSubscriptionModal = true
            },

            reactivateSubscriptionModalHide() {
                this.subscriptionUid = null
                this.showReactivateSubscriptionModal = false
            },
        },

        mounted() {
            this.loading = true

            this.$store.dispatch('developerSupport/getSubscriptionInfo')
                .then(() => {
                    this.loading = false
                })
                .catch((error) => {
                    const errorMessage = error ? error : 'Couldn’t get subscription info.'
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
