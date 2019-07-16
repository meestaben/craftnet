<template>
    <div>
        <h2>Upgrade support plan</h2>

        <ul>
            <li>Total cost to be billed</li>
            <li>New plan billing date</li>
            <li>Reimbursement credit (if upgrading from standard to priority)</li>
            <li>Optional promo code box</li>
            <li>Confirm/Cancel buttons</li>
            <li>If they haven’t entered their billing info yet, disable the Confirm button and link to the Billing page</li>
        </ul>

        <div>
            <btn @click="$emit('close')">Cancel</btn>
            <btn kind="primary">Upgrade Plan</btn>
        </div>
    </div>
</template>