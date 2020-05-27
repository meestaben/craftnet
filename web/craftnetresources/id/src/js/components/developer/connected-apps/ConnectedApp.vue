<template>
    <list-group-item>
        <spinner v-if="loading"></spinner>

        <template v-else>
            <div class="flex items-start">
                <img class="flex mr-3" :src="icon" height="48" width="48" />
                <div class="flex-1">
                    <template v-if="connected">
                        <h5>{{ accountName }}</h5>
                        <p v-if="connectedDescription" class="mb-0">{{ connectedDescription }}</p>
                        <p class="mb-0">
                            <span class="text-secondary">{{ name }}</span>
                        </p>
                    </template>

                    <template v-else>
                        <h5>{{ name }}</h5>
                        <p class="mb-0">{{ description }}</p>
                    </template>
                </div>
                <div class="ml-4">
                    <template v-if="connected">
                        <btn kind="danger" small @click="$emit('disconnect')">Disconnect</btn>
                    </template>
                    <template v-else>
                        <btn kind="primary" @click="$emit('connect')">Connect</btn>
                    </template>

                    <div v-if="buttonLoading" class="mt-2 text-right">
                        <spinner></spinner>
                    </div>
                </div>
            </div>
        </template>
    </list-group-item>
</template>

<script>
    import ListGroupItem from '../../ListGroupItem'

    export default {
        props: ['name', 'description', 'icon', 'accountName', 'connected', 'connectedDescription', 'buttonLoading', 'loading'],

        data() {
            return {
                // name: "Stripe",
                // description: "Connect your Stripe account so you can receive payouts for plugin purchases.",
                // icon: '/craftnetresources/id/dist/images/stripe.svg',
                // accountName: "My account name",
                // connected: false,
                // buttonLoading: true,
                // loading: false,
            }
        },

        components: {
            ListGroupItem,
        }
    }
</script>
