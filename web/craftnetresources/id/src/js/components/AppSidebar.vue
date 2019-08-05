<template>
    <div class="sidebar" :class="{ 'showing-sidebar': showingSidebar }">
        <h5>
            <router-link @click.native="$emit('closeSidebar')" to="/licenses">
                <icon icon="key" />
                Licenses
            </router-link>
        </h5>
        <ul>
            <li><router-link @click.native="$emit('closeSidebar')" to="/licenses/cms">Craft CMS</router-link></li>
            <li><router-link @click.native="$emit('closeSidebar')" to="/licenses/plugins">Plugins</router-link></li>
            <li><router-link @click.native="$emit('closeSidebar')" to="/licenses/claim">Claim License</router-link></li>
        </ul>

        <template v-if="user">
            <template v-if="userIsInGroup('developers')">
                <h5>
                    <router-link @click.native="$emit('closeSidebar')" to="/developer">
                        <icon icon="plug" />
                        Developer
                    </router-link>
                </h5>
                <ul>
                    <li><router-link @click.native="$emit('closeSidebar')" to="/developer/plugins">Plugins</router-link></li>
                    <li><router-link @click.native="$emit('closeSidebar')" to="/developer/sales">Sales</router-link></li>
                    <li><router-link @click.native="$emit('closeSidebar')" to="/developer/profile">Profile</router-link></li>
                    <li><router-link @click.native="$emit('closeSidebar')" to="/developer/settings">Settings</router-link></li>
                </ul>
            </template>

            <template v-if="userIsInGroup('staff') && user.enableShowcaseFeatures">
                <h5>
                    <a class="disabled" href="#">
                        <icon icon="image" />
                        Showcase
                    </a>
                </h5>
                <ul>
                    <li><a class="disabled" href="#">Activity</a></li>
                    <li><a class="disabled" href="#">Projects</a></li>
                    <li><a class="disabled" href="#">Agency Profile</a></li>
                </ul>
            </template>

            <template v-if="user.enablePartnerFeatures">
                <h5>
                    <router-link @click.native="$emit('closeSidebar')" to="/partner">
                        <icon icon="handshake" />
                        Partner
                    </router-link>
                </h5>
                <ul>
                    <li><router-link @click.native="$emit('closeSidebar')" to="/partner/overview">Overview</router-link></li>
                    <li><router-link @click.native="$emit('closeSidebar')" to="/partner/profile">Profile</router-link></li>
                </ul>
            </template>
        </template>

        <h5>
            <router-link @click.native="$emit('closeSidebar')" to="/account">
                <icon icon="user" />
                Account
            </router-link>
        </h5>
        <ul>
            <li><router-link @click.native="$emit('closeSidebar')" to="/account/developer-support">Developer Support</router-link></li>
            <li><router-link @click.native="$emit('closeSidebar')" to="/account/billing">Billing</router-link></li>
            <li><router-link @click.native="$emit('closeSidebar')" to="/account/settings">Settings</router-link></li>
        </ul>
    </div>
</template>

<script>
    import {mapState, mapGetters} from 'vuex'

    export default {
        props: ['showingSidebar'],

        computed: {
            ...mapState({
                user: state => state.account.user,
            }),

            ...mapGetters({
                userIsInGroup: 'account/userIsInGroup',
            }),
        },
    }
</script>

<style lang="scss">
    .sidebar {
        @apply .hidden .py-6 .overflow-auto;

        h5 {
            @apply .relative .text-base .mb-2 .text-grey-darker .px-6;

            a {
                @apply .text-grey-darker .block;

                &:hover {
                    @apply .no-underline;
                }

                &.disabled {
                    @apply .text-grey;
                }
            }

            svg {
                width: 13px;
                margin-right: 5px;
            }

            &:not(:first-child) {
                @apply .mt-4;
            }
        }

        ul {
            @apply .list-reset;

            li {
                a {
                    @apply .block .text-grey-darker .px-6 .py-2 .no-underline;
                    padding-left: 47px;

                    &:hover {
                        @apply .text-black;
                    }

                    &.active {
                        @apply .bg-grey-light;
                    }

                    &.disabled {
                        @apply .text-grey;
                    }
                }
            }
        }
    }
</style>