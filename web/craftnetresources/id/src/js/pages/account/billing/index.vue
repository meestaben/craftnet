<template>
    <div>
        <h1>Billing</h1>

        <div class="card mb-6">
            <div class="card-body">
                <billing-payment></billing-payment>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-body">
                <billing-address-form></billing-address-form>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-body">
                <billing-invoice-details></billing-invoice-details>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-body">
                <h4>Invoices</h4>

                <invoices-table></invoices-table>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-body">
                <h4>Subscription Invoices</h4>

                <subscription-invoices-table></subscription-invoices-table>
            </div>
        </div>
    </div>
</template>

<script>
    import BillingPayment from '../../../components/billing/BillingPayment'
    import BillingInvoiceDetails from '../../../components/billing/BillingInvoiceDetails'
    import BillingAddressForm from '../../../components/billing/BillingAddressForm'
    import InvoicesTable from '../../../components/billing/InvoicesTable'
    import SubscriptionInvoicesTable from '../../../components/billing/SubscriptionInvoicesTable'

    export default {
        components: {
            BillingPayment,
            BillingInvoiceDetails,
            BillingAddressForm,
            InvoicesTable,
            SubscriptionInvoicesTable,
        },
    }
</script>
