<template>
    <div v-if="license.expirable && license.expiresOn">
        <h2 class="mb-3">Renew Licenses</h2>

        <renew-cms-license
                v-if="renewLicensesType === 'cms'"
                :license="license"
                @cancel="$emit('cancel')"
                @addToCart="$emit('cancel')"
        />

        <renew-plugin-license
                v-if="renewLicensesType === 'plugin'"
                :license="license"
                @cancel="$emit('cancel')"
                @addToCart="$emit('cancel')"
        />
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import RenewCmsLicense from './types/Cms'
    import RenewPluginLicense from './types/Plugin'

    export default {
        props: ['license'],

        components: {
            RenewCmsLicense,
            RenewPluginLicense,
        },

        computed: {
            ...mapState({
                renewLicensesType: state => state.app.renewLicensesType,
            }),
        },
    }
</script>
