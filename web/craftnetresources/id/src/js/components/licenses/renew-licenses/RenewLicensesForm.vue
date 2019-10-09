<template>
    <div v-if="license.expirable && license.expiresOn">
        <h2 class="mb-3">Renew Licenses</h2>

        <renew-cms-license
                v-if="renewLicensesStep === 'renew-cms-license'"
                :license="license"
                @cancel="$emit('cancel')"
                @addToCart="$emit('cancel')"
        />

        <renew-plugin-license
                v-if="renewLicensesStep === 'renew-plugin-license'"
                :license="license"
                @cancel="$emit('cancel')"
                @addToCart="$emit('cancel')" />
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import RenewCmsLicense from './steps/RenewCmsLicense'
    import RenewPluginLicense from './steps/RenewPluginLicense'

    export default {
        props: ['license'],

        components: {
            RenewCmsLicense,
            RenewPluginLicense,
        },

        computed: {
            ...mapState({
                renewLicensesStep: state => state.app.renewLicensesStep,
            }),
        },
    }
</script>
