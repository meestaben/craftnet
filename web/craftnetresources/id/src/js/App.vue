<template>
    <div id="app" :class="{'has-sidebar': (!$route.meta.layout || $route.meta.layout !== 'no-sidebar')}">
        <auth-manager ref="authManager"></auth-manager>

        <template v-if="user">
            <renew-licenses-modal v-if="showRenewLicensesModal" :license="renewLicense" @cancel="$store.commit('app/updateShowRenewLicensesModal', false)" />
        </template>

        <global-modal></global-modal>

        <developer-support-modal></developer-support-modal>

        <template v-if="notification">
            <div id="notifications-wrapper" :class="{'hide': !notification }">
                <div id="notifications">
                    <div class="notification" :class="notification.type">{{ notification.message }}</div>
                </div>
            </div>
        </template>

        <template v-if="loading">
            <div class="text-center">
                <spinner class="lg mt-8"></spinner>
            </div>
        </template>

        <template v-else>
            <component :is="layoutComponent"></component>
        </template>
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import router from './router'
    import helpers from './mixins/helpers'
    import AuthManager from './components/AuthManager'
    import GlobalModal from './components/GlobalModal'
    import RenewLicensesModal from './components/licenses/renew-licenses/RenewLicensesModal'
    import AppLayout from './components/layouts/AppLayout'
    import SiteLayout from './components/layouts/SiteLayout'
    import DeveloperSupportModal from './components/DeveloperSupportModal'

    export default {
        router,

        mixins: [helpers],

        components: {
            AuthManager,
            GlobalModal,
            RenewLicensesModal,
            AppLayout,
            SiteLayout,
            DeveloperSupportModal,
        },

        computed: {
            ...mapState({
                notification: state => state.app.notification,
                showRenewLicensesModal: state => state.app.showRenewLicensesModal,
                loading: state => state.app.loading,
                renewLicense: state => state.app.renewLicense,
                user: state => state.account.user,
            }),

            layoutComponent() {
                switch (this.$route.meta.layout) {
                    case 'site':
                        return 'site-layout'

                    default:
                        return 'app-layout'
                }
            }
        },

        created() {
            this.$store.dispatch('account/loadAccount')
                .then(() => {
                    this.$store.dispatch('cart/getCart')
                        .then(() => {
                            this.$store.commit('app/updateLoading', false)
                        })
                })

            if(window.sessionNotice) {
                this.$store.dispatch('app/displayNotice', window.sessionNotice)
            }

            if(window.sessionError) {
                this.$store.dispatch('app/displayError', window.sessionError)
            }
        }
    }
</script>

<style lang="scss">
    @import './../sass/app.scss';

    #app:not(.has-sidebar) {
        .header {
            #sidebar-toggle {
                @apply .hidden;
            }
        }
    }
</style>
