<template>
    <tr>
        <td>
            <input
                    type="checkbox"
                    :value="1"
                    :disabled="(itemKey === 0 || !renewableLicense.key || alreadyInCart) ? true : false"
                    :checked="renewableLicense.key && !alreadyInCart ? isChecked : false"
                    @input="$emit('checkLicense', $event)" />
        </td>
        <td :class="{'text-grey': !renewableLicense.key}">
            {{ renewableLicense.description }}
            <div v-if="alreadyInCart" class="text-grey-dark">Already in cart.</div>
        </td>
        <td :class="{'text-grey': !renewableLicense.key}">{{ renewableLicense.expiresOn.date|moment('YYYY-MM-DD') }}</td>
        <td :class="{'text-grey': !renewableLicense.key}">
            {{ renewableLicense.expiryDate|moment('YYYY-MM-DD') }}
        </td>
        <td></td>
    </tr>
</template>

<script>
    export default {
        props: ['itemKey', 'renewableLicense', 'isChecked'],

        data() {
            return {
                alreadyInCart: false,
            }
        },

        mounted() {
            // Make a copy of `alreadyInCart` value to prevent “Already in cart” text to show up when item gets added to the cart.
            this.alreadyInCart = this.renewableLicense.alreadyInCart
        }
    }
</script>