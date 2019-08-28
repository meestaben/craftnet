<template>
    <form @submit.prevent="save()">
        <div ref="cardElement" class="card-element form-control mb-3"></div>
        <p id="card-errors" class="text-red" role="alert"></p>

        <btn kind="primary" type="submit">Save</btn>
        <btn @click="cancel()">Cancel</btn>

        <spinner v-if="loading"></spinner>
    </form>
</template>


<script>
    /* global Stripe */

    export default {
        props: ['loading'],

        methods: {
            /**
             * Save the credit card.
             */
            save() {
                this.$emit('beforeSave');

                let vm = this;
                this.stripe.createPaymentMethod('card', this.card).then(function(result) {
                    if (result.error) {
                        let errorElement = document.getElementById('card-errors');
                        errorElement.textContent = result.error.message;
                        vm.$emit('error', result.error);
                    } else {
                        vm.$emit('save', vm.card, result.paymentMethod);
                    }
                });
            },

            /**
             * Cancel.
             */
            cancel() {
                this.card.clear();

                let errorElement = document.getElementById('card-errors');
                errorElement.textContent = '';

                this.$emit('cancel');
            }
        },

        mounted() {
            this.stripe = Stripe(window.stripePublicKey);
            this.elements = this.stripe.elements({locale: 'en'});
            this.card = this.elements.create('card', {hidePostalCode: true});

            // Vue likes to stay in control of $el but Stripe needs a real element
            const el = document.createElement('div')
            this.card.mount(el)

            // this.$children cannot be used because it expects a VNode :(
            this.$refs.cardElement.appendChild(el)
        },
    }
</script>

<style lang="scss" scoped>
    .card-element {
        @apply .border .border-grey-light .px-3 .py-2 .rounded;
        max-width: 410px;
    }
</style>
