<template>
    <div>
        <div class="card mb-3">
            <div class="card-body">
                <h4 class="mb-4">License Details</h4>
                <template v-if="license">
                    <div class="md:flex -mx-4">
                        <div class="md:w-1/2 px-4">
                            <dl>
                                <template v-if="license.plugin">
                                    <dt>Plugin</dt>
                                    <dd>
                                        {{ license.plugin.name }}

                                        <template v-if="license.plugin.hasMultipleEditions">
                                            <edition-badge class="ml-2 inline-block">{{ license.edition.name }}</edition-badge>
                                        </template>
                                    </dd>
                                </template>

                                <dt>License Key</dt>
                                <dd><code>{{ license.key|formatPluginLicense }}</code></dd>

                                <dt>Craft License</dt>
                                <dd>
                                    <template v-if="license.cmsLicense">
                                        <p>
                                            <code>
                                                <router-link v-if="license.cmsLicense.key" :to="'/licenses/cms/'+license.cmsLicenseId">{{ license.cmsLicense.key.substr(0, 10) }}</router-link>
                                                <template v-else>{{ license.cmsLicense.shortKey }}</template>
                                            </code>
                                            <span v-if="license.cmsLicense.edition" class="text-secondary">(Craft {{ license.cmsLicense.edition }})</span>
                                        </p>
                                        <div class="buttons">
                                            <btn small @click="detachCmsLicense()">Detach from this Craft license</btn>
                                            <spinner v-if="detaching"></spinner>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="text-secondary">Not attached to a CMS license.</span>
                                        <a v-if="originalCmsLicenseId" @click.prevent="reattachCmsLicense()" href="#">Undo</a>
                                    </template>
                                </dd>
                            </dl>
                        </div>
                        <div class="md:w-1/2 px-4">
                            <dl>
                                <dt>Email</dt>
                                <dd>{{ license.email }}</dd>

                                <dt>Created</dt>
                                <dd>{{ license.dateCreated.date|moment('YYYY-MM-DD') }}</dd>

                                <dt>Notes</dt>
                                <dd>
                                    <template v-if="!notesEditing">
                                        <p>{{ license.notes }}</p>

                                        <div class="buttons">
                                            <btn small icon="pencil" @click="notesEditing = true">Edit</btn>
                                        </div>
                                    </template>

                                    <form v-if="notesEditing" @submit.prevent="saveNotes()">
                                        <textbox type="textarea" id="notes" v-model="licenseDraft.notes" @input="notesChange" />
                                        <btn kind="primary" type="submit" :disabled="!notesValidates">Save</btn>
                                        <btn @click="cancelEditNotes()" >Cancel</btn>
                                        <spinner v-if="notesLoading"></spinner>
                                    </form>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        <div v-if="license.expirable && license.expiresOn" class="card mb-3">
            <div class="card-body">
                <h4>Auto-Renew</h4>

                <template v-if="licenseDraft.autoRenew">
                    <p>Auto-renew is <strong>enabled</strong> for this license.</p>
                </template>

                <template v-else>
                    <p>Auto-renew is <strong>disabled</strong> for this license.</p>
                </template>

                <lightswitch
                        id="auto-renew"
                        @change="saveAutoRenew"
                        :checked.sync="licenseDraft.autoRenew"
                />

                <div class="text-grey-dark">
                    <p>Renewal Price: <strong>{{license.edition.renewalPrice|currency}}/year</strong>.</p>

                    <template v-if="licenseDraft.autoRenew">
                        <p>Next payment: <strong>{{ license.expiresOn.date|moment('YYYY-MM-DD') }}</strong>.</p>
                    </template>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h4>Updates</h4>
                <license-update-message :license="license"></license-update-message>
                <template v-if="license.expirable && license.expiresOn">
                    <btn @click="showRenewLicensesModal('plugin')">Renew your license…</btn>
                </template>
            </div>
        </div>
    </div>
</template>

<script>
    import {mapActions} from 'vuex'
    import pluginLicensesApi from '../../api/plugin-licenses'
    import LicenseUpdateMessage from './LicenseUpdateMessage'
    import EditionBadge from '../EditionBadge';

    export default {
        props: ['license', 'type'],

        data() {
            return {
                errors: {},
                licenseDraft: {},
                originalCmsLicenseId: this.license.cmsLicenseId,
                originalCmsLicense: this.license.cmsLicense,
                detaching: false,
                reattaching: false,
                notesEditing: false,
                notesLoading: false,
                notesValidates: false,
            }
        },

        components: {
            EditionBadge,
            LicenseUpdateMessage,
        },

        methods: {
            ...mapActions({
                showRenewLicensesModal: 'app/showRenewLicensesModal',
            }),

            /**
             * Detach the Craft license.
             */
            detachCmsLicense() {
                this.detaching = true
                this.licenseDraft.cmsLicenseId = null
                this.licenseDraft.cmsLicense = null

                this.savePluginLicense(
                    // success
                    () => {
                        this.detaching = false
                        this.$store.dispatch('app/displayNotice', 'Plugin license detached from CMS license.')
                    },
                    // error
                    (response) => {
                        this.detaching = false
                        const errorMessage = response.data && response.data.error ? response.data.error : 'Couldn’t detach plugin license from CMS license.'
                        this.$store.dispatch('app/displayError', errorMessage)
                    })
            },

            /**
             * Reattach the Craft license.
             */
            reattachCmsLicense() {
                this.reattaching = true
                this.licenseDraft.cmsLicenseId = this.originalCmsLicenseId
                this.licenseDraft.cmsLicense = this.originalCmsLicense

                this.savePluginLicense(
                    // Success
                    () => {
                        this.reattaching = false
                        this.$store.dispatch('app/displayNotice', 'Plugin license reattached to CMS license.')
                    },
                    // error
                    (response) => {
                        this.reattaching = false
                        const errorMessage = response.data && response.data.error ? response.data.error : 'Couldn’t detach plugin license from CMS license.'
                        this.$store.dispatch('app/displayError', errorMessage)
                    })
            },

            /**
             * Can save
             */
            canSave() {
                if (this.license.notes !== this.licenseDraft.notes) {
                    return true
                }

                return false
            },

            /**
             * Save notes.
             */
            saveNotes() {
                this.notesLoading = true

                this.savePluginLicense(
                    // success
                    () => {
                        this.notesLoading = false
                        this.notesEditing = false
                        this.$store.dispatch('app/displayNotice', 'Notes saved.')
                    },
                    // error
                    (response) => {
                        this.notesLoading = false
                        const errorMessage = response.data && response.data.error ? response.data.error : 'Couldn’t save notes.'
                        this.$store.dispatch('app/displayError', errorMessage)
                    })
            },

            /**
             * Cancel edit notes.
             */
            cancelEditNotes() {
                this.licenseDraft.notes = this.license.notes
                this.notesEditing = false
                this.notesValidates = false
            },

            /**
             * Notes change.
             */
            notesChange() {
                this.notesValidates = false

                if(this.licenseDraft.notes !== this.license.notes) {
                    this.notesValidates = true
                }
            },

            /**
             * Save plugin license.
             *
             * @param cb
             * @param cbError
             */
            savePluginLicense(cb, cbError) {
                pluginLicensesApi.savePluginLicense({
                    pluginHandle: this.license.plugin.handle,
                    key: this.license.key,
                    cmsLicenseId: this.licenseDraft.cmsLicenseId,
                    cmsLicense: this.licenseDraft.cmsLicense,
                    notes: this.licenseDraft.notes,
                })
                    .then((response) => {
                        if (response.data && !response.data.error) {
                            // refresh license data
                            pluginLicensesApi.getPluginLicense(this.license.id)
                                .then((getPluginLicenseResponse) => {
                                    this.$emit('update:license', getPluginLicenseResponse.data)
                                    this.$store.commit('app/updateRenewLicense', getPluginLicenseResponse.data)
                                    cb(response)
                                })
                                .catch((getPluginLicenseError) => {
                                    cbError(getPluginLicenseError.response)
                                })
                        } else {
                            cbError(response)
                        }
                    })
                    .catch((error) => {
                        cbError(error.response)
                    })
            },

            /**
             * Save auto renew
             */
            saveAutoRenew() {
                pluginLicensesApi.savePluginLicense({
                        pluginHandle: this.license.plugin.handle,
                        key: this.license.key,
                        autoRenew: (this.licenseDraft.autoRenew ? 1 : 0),
                    })
                    .then((response) => {
                        if (response.data && !response.data.error) {
                            if (this.licenseDraft.autoRenew) {
                                this.$store.dispatch('app/displayNotice', 'Auto renew enabled.')
                            } else {
                                this.$store.dispatch('app/displayNotice', 'Auto renew disabled.')
                            }
                        } else {
                            this.licenseDraft.autoRenew = !this.licenseDraft.autoRenew
                            this.$store.dispatch('app/displayError', 'Couldn’t save license.')
                        }
                    })
                    .catch((error) => {
                        this.licenseDraft.autoRenew = !this.licenseDraft.autoRenew
                        const errorMessage = error.response.data && error.response.data.error ? error.response.data.error : 'Couldn’t save license.'
                        this.$store.dispatch('app/displayError', errorMessage)
                    })
            },
        },

        mounted() {
            this.licenseDraft = {
                cmsLicenseId: this.license.cmsLicenseId,
                cmsLicense: this.license.cmsLicense,
                autoRenew: (this.license.autoRenew == 1 ? true : false),
                notes: this.license.notes,
            }
        }
    }
</script>
