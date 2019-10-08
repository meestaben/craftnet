<template>
    <div>
        <dropdown :value="renew" @input="$emit('update:renew', $event)" :options="renewOptions" />
        <btn @click="$emit('cancel')">Cancel</btn>
        <btn ref="submitBtn" kind="primary" @click="$emit('continue')">Continue</btn>
    </div>
</template>

<script>
    export default {
        props: ['license', 'renew'],

        computed: {
            renewOptions() {
                const renewalOptions = this.license.renewalOptions

                if (!renewalOptions) {
                    return []
                }

                let options = [];

                for (let i = 0; i < renewalOptions.length; i++) {
                    const expiryDateOption = renewalOptions[i]
                    const renewalOption = this.license.renewalOptions[i]

                    const date = expiryDateOption.expiryDate
                    const formattedDate = this.$moment(date).format('YYYY-MM-DD')
                    const label = "Extend updates until " + formattedDate + ' (' + this.$options.filters.currency(renewalOption.amount) +')'

                    options.push({
                        label: label,
                        value: i,
                    })
                }

                return options
            },
        },

        mounted() {
            this.$refs.submitBtn.$el.focus()
        }
    }
</script>