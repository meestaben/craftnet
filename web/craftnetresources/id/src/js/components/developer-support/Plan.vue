<template>
    <div class="card lg:flex lg:flex-1 mx-4 mb-3">
        <div class="card-body text-center lg:h-full lg:flex-1 lg:flex">
            <div class="support-plan flex flex-col justify-between">
                <div class="details">
                    <div class="plan-icon">
                        <img :src="staticImageUrl(plan.icon + '.svg')" />
                    </div>

                    <h2 class="mb-6">{{plan.name}}</h2>

                    <ul class="feature-list">
                        <li v-for="(feature, featureKey) in plan.features" :key="'features-'+featureKey">
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
                        <template v-if="subscriptionInfoPlan">
                            <template v-if="subscriptionInfoSubscriptionData.status === 'inactive'">
                                <btn kind="primary" @click="$emit('selectPlan', plan)">Select this plan</btn>
                            </template>
                            <template v-else-if="subscriptionInfoSubscriptionData.status === 'active'">
                                <btn kind="primary" :disabled="true">Current plan</btn>
                                <p class="mt-4">
                                    Next billing date: {{subscriptionInfoSubscriptionData.nextBillingDate}}
                                </p>
                                <div class="mt-2">
                                    <a @click.prevent="$emit('cancelSubscription')">Cancel subscription</a>
                                </div>
                            </template>
                            <template v-else-if="subscriptionInfoSubscriptionData.status === 'expiring'">
                                <btn kind="primary" @click="$emit('reactivateSubscription', subscriptionInfoSubscriptionData.uid)">Reactivate</btn>
                                <p class="mt-4">Expires on {{subscriptionInfoSubscriptionData.expiringDate}}.</p>
                            </template>
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

<script>
    import {mapGetters} from 'vuex'

    import helpers from '../../mixins/helpers'

    export default {
        props: ['plan'],

        mixins: [helpers],

        computed: {
            ...mapGetters({
                currentPlanHandle: 'developerSupport/currentPlanHandle',
            }),

            subscriptionInfoPlan() {
                return this.$store.getters['developerSupport/subscriptionInfoPlan'](this.plan.handle)
            },

            subscriptionInfoSubscriptionData() {
                return this.$store.getters['developerSupport/subscriptionInfoSubscriptionData'](this.plan.handle)
            }
        },
    }
</script>