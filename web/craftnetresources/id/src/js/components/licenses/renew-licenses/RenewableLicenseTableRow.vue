<template>
    <tr>
        <td>
            <input
                    type="checkbox"
                    :value="1"
                    :disabled="(itemKey === 0 || !renewableLicense.key) ? true : false"
                    :checked="renewableLicense.key ? isChecked : false"
                    @input="$emit('checkLicense', $event)" />
        </td>
        <td :class="{'text-grey': !renewableLicense.key}">
            {{ renewableLicense.description }}
        </td>
        <td :class="{'text-grey': !renewableLicense.key}">{{ renewableLicense.expiresOn.date|moment('YYYY-MM-DD') }}</td>
        <td :class="{'text-grey': !renewableLicense.key}">
            {{ renewableLicense.expiryDate|moment('YYYY-MM-DD') }}
        </td>
        <td>{{renewableLicense.amount|currency}}</td>
    </tr>
</template>

<script>
    export default {
        props: ['itemKey', 'renewableLicense', 'isChecked', 'isDisabled'],
    }
</script>