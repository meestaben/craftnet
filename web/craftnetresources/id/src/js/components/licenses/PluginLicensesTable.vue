<template>
    <div class="responsive-content">
        <table class="table">
            <thead>
            <tr>
                <th>License Key</th>
                <th>Plugin</th>
                <th v-if="!excludeNotesColumn">Notes</th>
                <th v-if="!excludeCmsLicenseColumn">CMS License</th>
                <th>Updates Until</th>
                <th>Auto Renew</th>
            </tr>
            </thead>
            <tbody>
            <template>
                <tr v-for="(license, licenseIndex) in licenses" :key="licenseIndex">
                    <td>
                        <code>
                            <router-link v-if="license.key" :to="'/licenses/plugins/'+license.id">{{ license.key.substr(0, 4) }}</router-link>
                            <template v-else>{{ license.shortKey }}</template>
                        </code>
                    </td>
                    <td>
                        <template v-if="license.plugin">
                            {{ license.plugin.name }}
                        </template>
                    </td>
                    <td v-if="!excludeNotesColumn">{{ license.notes }}</td>
                    <td v-if="!excludeCmsLicenseColumn">
                        <template v-if="license.cmsLicense">
                            <code>
                                <router-link v-if="license.cmsLicense.key" :to="'/licenses/cms/'+license.cmsLicenseId">{{ license.cmsLicense.key.substr(0, 10) }}</router-link>
                                <template v-else>{{ license.cmsLicense.shortKey }}</template>
                            </code>
                        </template>

                        <template v-else>
                            —
                        </template>
                    </td>
                    <td>
                        <template v-if="license.expiresOn">
                            <template v-if="expiresSoon(license)">
                                <span class="text-orange">
                                    {{ license.expiresOn.date|moment('YYYY-MM-DD') }}
                                </span>
                            </template>
                            <template v-else>
                                {{ license.expiresOn.date|moment('YYYY-MM-DD') }}
                            </template>
                        </template>
                    </td>
                    <td>
                        <template v-if="autoRenewSwitch">

                            <template v-if="!!license.key">
                                <lightswitch
                                        :id="'auto-renew-'+license.id"
                                        @change="savePluginLicenseAutoRenew(license, $event)"
                                        :checked.sync="pluginLicensesAutoRenew[license.id]"
                                        :disabled="!license.key"
                                />
                            </template>
                            <template v-else>
                                <lightswitch
                                        :id="'auto-renew-'+license.id"
                                        :checked="license.autoRenew"
                                        :disabled="true"
                                />
                            </template>

                        </template>
                        <template v-else>
                            <badge v-if="license.autoRenew == 1" type="success">Enabled</badge>
                            <badge v-else>Disabled</badge>
                        </template>
                    </td>
                </tr>
            </template>
            </tbody>
        </table>
    </div>
</template>

<script>
    import Badge from '../Badge'
    import pluginLicensesApi from '../../api/plugin-licenses'
    import helpers from '../../mixins/helpers'

    export default {
        mixins: [helpers],

        props: ['licenses', 'excludeCmsLicenseColumn', 'excludeNotesColumn', 'autoRenewSwitch'],

        components: {
            Badge,
        },

        data() {
            return {
                pluginLicensesAutoRenew: {},
            }
        },

        methods: {
            savePluginLicenseAutoRenew(license, checked) {
                if (!license.key) {
                    return false;
                }

                const autoRenew = checked
                const data = {
                    pluginHandle: license.plugin.handle,
                    key: license.key,
                    autoRenew: autoRenew ? 1 : 0,
                }

                pluginLicensesApi.savePluginLicense(data)
                    .then((response) => {
                        if (response.data && !response.data.error) {
                            if (autoRenew) {
                                this.$store.dispatch('app/displayNotice', 'Auto renew enabled.')
                            } else {
                                this.$store.dispatch('app/displayNotice', 'Auto renew disabled.');
                            }
                        } else {
                            this.$store.dispatch('app/displayError', 'Couldn’t save license.');
                        }
                    })
                    .catch((response) => {
                        this.$store.dispatch('app/displayError', 'Couldn’t save license.');
                        this.errors = response.errors;
                    });
            }
        },

        mounted() {
            this.pluginLicensesAutoRenew = {};

            this.licenses.forEach(function(license) {
                this.pluginLicensesAutoRenew[license.id] = license.autoRenew
            }.bind(this))
        }
    }
</script>
