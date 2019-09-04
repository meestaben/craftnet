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
                                @selectPlan="showSubscribeModal"
                                @cancelSubscription="showCancelSubscriptionModal"
                                @reactivateSubscription="showReactivateSubscriptionModal"
                        ></plan>
                    </template>
                </div>
            </div>

            <div class="mt-8 text-center text-grey">
                <p>Support plans cover emails to <a href="mailto:support@craftcms.com">support@craftcms.com</a> from <a :href="'mailto:'+user.email">{{user.email}}</a>.</p>
                <p>Go to your <router-link to="/account/settings">account settings</router-link> to change your email address.</p>
            </div>
        </template>

        <!-- Modals -->
        <subscribe-modal
                :show="showingModal === 'subscribe'"
                :selectedPlanHandle="selectedPlanHandle"
                @cancel="hideSubscribeModal"
                @close="hideSubscribeModal"
        ></subscribe-modal>

        <cancel-subscription-modal
                :show="showingModal === 'cancelSubscription'"
                :subscriptionUid="subscriptionUid"
                @cancel="hideCancelSubscriptionModal"
                @close="hideCancelSubscriptionModal"
        ></cancel-subscription-modal>

        <reactivate-subscription-modal
                :show="showingModal === 'reactivateSubscription'"
                :subscriptionUid="subscriptionUid"
                @cancel="hideReactivateSubscriptionModal"
                @close="hideReactivateSubscriptionModal"
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
                showingModal: null,
                subscriptionUid: null,
                selectedPlanHandle: null,
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
            showSubscribeModal(plan) {
                this.selectedPlanHandle = plan.handle
                this.showModal('subscribe')
            },

            hideSubscribeModal() {
                this.selectedPlanHandle = null
                this.hideModal()
            },

            showCancelSubscriptionModal(subscriptionUid) {
                this.subscriptionUid = subscriptionUid
                this.showModal('cancelSubscription')
            },

            hideCancelSubscriptionModal() {
                this.subscriptionUid = null
                this.hideModal()
            },

            showReactivateSubscriptionModal(subscriptionUid) {
                this.subscriptionUid = subscriptionUid
                this.showModal('reactivateSubscription')
            },

            hideReactivateSubscriptionModal() {
                this.subscriptionUid = null
                this.hideModal()
            },

            showModal(modalHandle) {
                this.showingModal = modalHandle
            },

            hideModal() {
                this.showingModal = null
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
