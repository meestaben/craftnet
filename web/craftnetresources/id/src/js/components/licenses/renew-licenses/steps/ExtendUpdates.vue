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
                if (!this.license.expiryDateOptions) {
                    return []
                }

                let options = [];

                for (let i = 0; i < this.license.expiryDateOptions.length; i++) {
                    const expiryDateOption = this.license.expiryDateOptions[i]
                    const date = expiryDateOption[1]
                    const formattedDate = this.$moment(date).format('YYYY-MM-DD')
                    const label = "Extend updates until " + formattedDate

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