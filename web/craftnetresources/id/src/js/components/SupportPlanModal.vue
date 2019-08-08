<template>
    <div>
        <h2>Upgrade support plan</h2>

        <p>Your billing info is missing. Go to <router-link to="/account/billing">Account → Billing</router-link> to add a credit card and update billing infos.</p>

        <table class="table border-b mt-6 mb-8">
            <thead class="hidden">
            <tr>
                <th>Item</th>
                <th>Price</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>Refund old plan</td>
                <td class="text-right">- $50</td>
            </tr>
            <tr>
                <td>New plan name</td>
                <td class="text-right">$750</td>
            </tr>
            <tr>
                <td class="font-bold">Total</td>
                <td class="font-bold text-right">$700</td>
            </tr>
            </tbody>
        </table>

        <div>
            <btn @click="$emit('close')">Cancel</btn>
            <btn kind="primary">Upgrade Plan</btn>
        </div>
    </div>
</template>