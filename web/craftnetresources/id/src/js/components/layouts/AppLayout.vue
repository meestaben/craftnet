<template>
    <div class="app">
        <renew-licenses-modal />
        <developer-support-modal />

        <global-header :showingSidebar="showingSidebar" @toggleSidebar="toggleSidebar()"></global-header>

        <div class="flex-container">
            <template v-if="typeof $route.meta.sidebar === 'undefined' || $route.meta.sidebar === true">
                <app-sidebar :showingSidebar="showingSidebar" @closeSidebar="closeSidebar()"></app-sidebar>
            </template>

            <div id="main" class="main" :class="{'main-full': $route.meta.mainFull}">
                <div class="page-alerts">
                    <template v-if="$route.meta.stripeAccountAlert">
                        <stripe-account-alert></stripe-account-alert>
                    </template>

                    <template v-if="$route.meta.cmsLicensesRenewAlert">
                        <license-renew-alert type="CMS" :expiring-licenses-total="expiringCmsLicensesTotal"></license-renew-alert>
                    </template>

                    <template v-if="$route.meta.pluginLicensesRenewAlert">
                        <license-renew-alert type="plugin" :expiring-licenses-total="expiringPluginLicensesTotal"></license-renew-alert>
                    </template>
                </div>

                <div class="main-content">
                    <router-view :key="$route.path"></router-view>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import GlobalHeader from '../GlobalHeader'
    import AppSidebar from '../AppSidebar'
    import StripeAccountAlert from '../StripeAccountAlert'
    import LicenseRenewAlert from '../LicenseRenewAlert'
    import DeveloperSupportModal from '../developer-support/DeveloperSupportModal'
    import RenewLicensesModal from '../licenses/renew-licenses/RenewLicensesModal'

    export default {
        components: {
            GlobalHeader,
            AppSidebar,
            StripeAccountAlert,
            LicenseRenewAlert,
            DeveloperSupportModal,
            RenewLicensesModal,
        },

        data() {
            return {
                showingSidebar: false,
            }
        },

        computed: {
            ...mapState({
                expiringCmsLicensesTotal: state => state.cmsLicenses.expiringCmsLicensesTotal,
                expiringPluginLicensesTotal: state => state.pluginLicenses.expiringPluginLicensesTotal,
            }),
        },

        methods: {
            /**
             * Toggles the sidebar.
             */
            toggleSidebar() {
                this.showingSidebar = !this.showingSidebar
            },

            /**
             * Closes the sidebar.
             */
            closeSidebar() {
                this.showingSidebar = false
            },
        }
    }
</script>

<style lang="scss">
    .app {
        @apply .fixed .pin .flex .flex-col;

        .header {
            &-left {
                #sidebar-toggle {
                    @apply .mr-4 .text-grey-darker .text-center;
                    width: 14px;

                    &:hover {
                        @apply .text-black;
                    }
                }
            }
        }
    }

    @media (max-width: 767px) {
        .app {
            .sidebar {
                &.showing-sidebar {
                    @apply .block .bg-white .absolute .pin .z-10;
                    top: 61px;
                }
            }
        }
    }

    @media (min-width: 768px) {
        .app {
            .header {
                &-left {
                    #sidebar-toggle {
                        @apply .hidden;
                    }
                }
            }

            .sidebar {
                @apply .w-64 .block .border-r;
            }
        }
    }

    /* Main */

    .main {
        @apply .flex-1 .overflow-auto .bg-white;

        &:not(.main-full) {
            @apply .px-8 .py-6;

            .main-content {
                @apply .mx-auto;
                max-width: 1440px;
            }
        }

        &.main-full {
            @apply .flex;

            .main-content {
                @apply .flex .flex-1;
            }
        }

        .main-content {
            .top-alert {
                @apply .-mx-8 .-mt-6 .rounded-none .px-8 .mb-6;
                border: 0;
            }
        }
    }

</style>