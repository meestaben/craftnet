<template>
    <div>
        <template v-if="loading">
            <spinner></spinner>
        </template>

        <template v-else>
            <div class="card card-table" :class="{'opacity-25': loading}">
                <vuetable
                        ref="vuetable"
                        pagination-path=""
                        :api-mode="false"
                        :data="subscriptionInvoices"
                        :fields="fields"
                        :append-params="moreParams"
                >
                    <template slot="date" slot-scope="props">
                        {{ props.rowData.date }}
                    </template>
                    <template slot="amount" slot-scope="props">
                        {{ props.rowData.amount|currency }}
                    </template>
                    <template slot="url" slot-scope="props">
                        <a :href="props.rowData.url" title="Download receipt">Download Receipt</a>
                    </template>
                </vuetable>
            </div>
        </template>
    </div>
</template>

<script>
    import {mapState, mapActions} from 'vuex'
    import Vuetable from 'vuetable-2/src/components/Vuetable'

    export default {
        components: {
            Vuetable,
        },
        data() {
            return {
                fields: [
                    {
                        name: '__slot:date',
                        title: 'Date',
                    },
                    {
                        name: '__slot:amount',
                        title: 'Amount',
                    },
                    {
                        name: '__slot:url',
                        title: 'Download',
                    },
                ],
                loading: false,
                moreParams: {},
            }
        },

        computed: {
            ...mapState({
                subscriptionInvoices: state => state.invoices.subscriptionInvoices,
            }),
        },

        methods: {
            ...mapActions({
                getSubscriptionInvoices: 'invoices/getSubscriptionInvoices',
            }),
        },

        mounted() {
            this.loading = true

            this.getSubscriptionInvoices()
                .then(() => {
                    this.loading = false
                })
                .catch(() => {
                    this.loading = false
                })
        }
    }
</script>