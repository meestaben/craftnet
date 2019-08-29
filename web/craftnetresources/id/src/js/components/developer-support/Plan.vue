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
                            <template v-if="!subscriptionInfoPlan.canceled">
                                <template v-if="plan.handle === currentPlanHandle && !subscriptionInfoPlan.canceled">
                                    <btn kind="primary" :disabled="true">Current plan</btn>
                                    <div class="mt-2">
                                        <a @click.prevent="$emit('cancelSubscription')">Cancel subscription</a>
                                    </div>
                                </template>
                                <template v-else>
                                    <btn kind="primary" @click="$emit('selectPlan', plan)">Select this plan</btn>
                                </template>
                            </template>
                            <template v-else>
                                <btn kind="primary" @click="$emit('selectPlan', plan)">Reactivate this plan</btn>

                                <div class="mt-6 text-grey-dark">
                                    <template v-if="subscriptionInfoPlan.cycleEnd">
                                        <p>Your subscription to this plan has been canceled, its cycle ends on {{subscriptionInfoPlan.cycleEnd}}.</p>
                                    </template>
                                    <template v-else>
                                        <p>Your subscription to this plan has been canceled.</p>
                                    </template>
                                </div>
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
            }
        },
    }
</script>