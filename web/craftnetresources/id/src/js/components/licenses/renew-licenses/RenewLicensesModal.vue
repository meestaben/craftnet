<template>
    <div v-if="user && showRenewLicensesModal && renewLicense" id="renew-licenses-modal">
        <modal :show="true" @background-click="cancel">
            <template slot="body">
                <renew-licenses-form :license="renewLicense" @cancel="cancel" />
            </template>
        </modal>
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import Modal from '../../Modal';
    import RenewLicensesForm from './RenewLicensesForm'

    export default {
        components: {
            Modal,
            RenewLicensesForm,
        },

        computed: {
            ...mapState({
                showRenewLicensesModal: state => state.app.showRenewLicensesModal,
                renewLicense: state => state.app.renewLicense,
                user: state => state.account.user,
            }),
        },

        methods: {
            cancel() {
                this.$store.commit('app/updateShowRenewLicensesModal', false)
            }
        }
    }
</script>

<style lang="scss">
    #renew-licenses-modal {
        .modal {
            .modal-dialog {
                @apply .relative;
                min-width: 800px;
                min-height: 600px;

                .modal-content {
                    @apply .absolute .pin;
                }
            }
        }
    }
</style>
