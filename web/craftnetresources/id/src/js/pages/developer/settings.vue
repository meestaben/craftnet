<template>
    <div>
        <h1>Developer Settings</h1>

        <h2>Connected Apps</h2>
        <connected-apps title="Connected Apps" :show-stripe="true"></connected-apps>

        <div class="card mt-6">
            <div class="card-body">
                <form @submit.prevent="generateToken()">
                    <h4>API Token</h4>

                    <p v-if="notice">This is your new API token, <strong>keep it someplace safe</strong>.</p>

                    <div class="max-w-sm">
                        <textbox id="apiToken" ref="apiTokenField" class="mono" :spellcheck="false" v-model="apiToken" :readonly="true"/>
                    </div>

                    <btn kind="primary" type="submit" :disabled="loading" :loading="loading">
                        <template v-if="apiToken">Generate new API Token</template>
                        <template v-else>Generate API Token</template>
                    </btn>
                </form>
            </div>
        </div>

        <payout-settings></payout-settings>
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import ConnectedApps from '../../components/developer/connected-apps/ConnectedApps'
    import PayoutSettings from '../../components/developer/PayoutSettings'

    export default {
        data() {
            return {
                apiToken: '',
                loading: false,
                notice: false,
            }
        },

        components: {
            ConnectedApps,
            PayoutSettings,
        },

        computed: {
            ...mapState({
                hasApiToken: state => state.account.hasApiToken,
                user: state => state.account.user,
            }),
        },

        methods: {
            generateToken() {
                this.loading = true

                this.$store.dispatch('account/generateApiToken')
                    .then(response => {
                        this.apiToken = response.data.apiToken

                        const apiTokenInput = this.$refs.apiTokenField.$el.querySelector('input')

                        this.$nextTick(() => {
                            apiTokenInput.select()
                        })

                        this.notice = true
                        this.loading = false
                        this.$store.dispatch('app/displayNotice', 'API token generated.')
                    })
                    .catch(response => {
                        this.loading = false
                        const errorMessage = response.data && response.data.error ? response.data.error : 'Couldn’t generate API token.'
                        this.$store.dispatch('app/displayError', errorMessage)
                    })
            },
        },

        mounted() {
            if (this.hasApiToken) {
                this.apiToken = '****************************************'
            }
        }
    }
</script>
