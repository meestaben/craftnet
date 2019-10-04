<template>
    <div>
        <div class="card mb-3">
            <div class="card-body">
                <h4 class="mb-4">License Details</h4>
                <template v-if="license">
                    <div class="md:flex -mx-4">
                        <div class="md:w-1/2 px-4">
                            <dl>
                                <dt>Edition</dt>
                                <dd>{{ license.edition|capitalize }}</dd>

                                <dt>License Key</dt>
                                <dd>
                                    <code>{{ license.key.slice(0, 10) }}…</code>
                                    <a href="#copy" class="ml-2" @click="copyLicense">Copy</a>
                                    <a :href="downloadLicenseUrl" class="ml-2" target="_blank">Download</a>
                                </dd>

                                <dt>Domain Name</dt>
                                <dd>
                                    <template v-if="!domainEditing">
                                        <p>{{ license.domain }}</p>

                                        <div class="buttons">
                                            <btn small icon="pencil" @click="domainEditing = true">Change Domain</btn>
                                        </div>
                                    </template>

                                    <form v-if="domainEditing" @submit.prevent="saveDomain()">
                                        <textbox id="domain" v-model="licenseDraft.domain" @input="domainChange" />
                                        <btn kind="primary" type="submit" :class="{disabled: !domainValidates}" :disabled="!domainValidates">Save</btn>
                                        <btn @click="cancelEditDomain()">Cancel</btn>
                                        <spinner v-if="domainLoading"></spinner>
                                    </form>
                                </dd>
                            </dl>
                        </div>
                        <div class="md:w-1/2 px-4">
                            <dl>
                                <dt>Email</dt>
                                <dd>{{ license.email }}</dd>

                                <dt>Created</dt>
                                <dd>{{ license.dateCreated.date|moment('YYYY-MM-DD') }}</dd>

                                <dt>Updates Until</dt>
                                <dd>
                                    <template v-if="license.expirable && license.expiresOn">
                                        {{ license.expiresOn.date|moment('YYYY-MM-DD') }}
                                    </template>
                                    <template v-else>
                                        Forever
                                    </template>
                                </dd>

                                <dt>Notes</dt>
                                <dd>
                                    <template v-if="!notesEditing">
                                        <p>{{ license.notes }}</p>

                                        <div class="buttons">
                                            <btn icon="pencil" @click="notesEditing = true">Edit</btn>
                                        </div>
                                    </template>

                                    <form v-if="notesEditing" @submit.prevent="saveNotes()">
                                        <textbox type="textarea" id="notes" v-model="licenseDraft.notes" @input="notesChange" />
                                        <btn kind="primary" type="submit" :disabled="!notesValidates">Save</btn>
                                        <btn @click="cancelEditNotes()">Cancel</btn>
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
                    <p>Renewal Price: <strong>{{license.editionDetails.renewalPrice|currency}}/year</strong>.</p>

                    <template v-if="licenseDraft.autoRenew">
                        <p>Next payment: <strong>{{ license.expiresOn.date|moment('YYYY-MM-DD') }}</strong>.</p>
                    </template>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h4>Updates</h4>
                <license-update-message :license="license" />
                <template v-if="license.expirable">
                    <btn @click="showRenewLicensesModal('extend-updates')">Renew your license…</btn>
                </template>
            </div>
        </div>
    </div>
</template>

<script>
    /* global Craft */

    import {mapActions} from 'vuex'
    import cmsLicensesApi from '../../api/cms-licenses'
    import LicenseUpdateMessage from './LicenseUpdateMessage'

    export default {
        props: ['license'],

        components: {
            LicenseUpdateMessage,
        },

        data() {
            return {
                licenseDraft: {},
                domainEditing: false,
                domainLoading: false,
                domainValidates: false,
                notesEditing: false,
                notesLoading: false,
                notesValidates: false,
                renewLicenses: false,
            }
        },

        computed: {
            canSave() {
                if (this.license.domain != this.licenseDraft.domain) {
                    return true
                }

                if (this.license.notes != this.licenseDraft.notes) {
                    return true
                }

                return false
            },

            formattedLicense() {
                let value = this.license.key
                let formattedValue = this.$options.filters.formatCmsLicense(value)
                return formattedValue
            },

            downloadLicenseUrl() {
                return Craft.actionUrl + '/craftnet/id/cms-licenses/download&id=' + this.license.id
            },
        },

        methods: {
            ...mapActions({
                showRenewLicensesModal: 'app/showRenewLicensesModal',
            }),

            /**
             * Save auto renew.
             */
            saveAutoRenew() {
                cmsLicensesApi.saveCmsLicense({
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

            /**
             * Save domain.
             */
            saveDomain() {
                this.domainLoading = true
                const oldDomain = this.licenseDraft.domain

                this.saveCmsLicense(
                    // success
                    (response) => {
                        const newDomain = response.data.license.domain

                        if (oldDomain && oldDomain !== newDomain) {
                            this.licenseDraft.domain = newDomain

                            if (!newDomain) {
                                this.$store.dispatch('app/displayNotice', oldDomain + ' is not a public domain.')
                            } else {
                                this.$store.dispatch('app/displayNotice', 'Domain changed to ' + newDomain + '.')
                            }
                        } else {
                            this.$store.dispatch('app/displayNotice', 'Domain saved.')
                        }

                        this.domainLoading = false
                        this.domainEditing = false
                    },

                    // error
                    (response) => {
                        const errorMessage = response.data && response.data.error ? response.data.error : 'Couldn’t save domain.'
                        this.$store.dispatch('app/displayError', errorMessage)
                        this.domainLoading = false
                    })
            },

            /**
             * Save notes.
             */
            saveNotes() {
                this.notesLoading = true

                this.saveCmsLicense(
                    // success
                    () => {
                        this.notesLoading = false
                        this.notesEditing = false
                        this.$store.dispatch('app/displayNotice', 'Notes saved.')
                    },

                    // error
                    (response) => {
                        const errorMessage = response.data && response.data.error ? response.data.error : 'Couldn’t save notes.'
                        this.$store.dispatch('app/displayError', errorMessage)
                        this.notesLoading = false
                    })
            },

            /**
             * Save CMS license.
             *
             * @param cb
             * @param cbError
             */
            saveCmsLicense(cb, cbError) {
                cmsLicensesApi.saveCmsLicense({
                        key: this.license.key,
                        domain: this.licenseDraft.domain,
                        notes: this.licenseDraft.notes,
                    })
                    .then(response => {
                        if (response.data && !response.data.error) {
                            // refresh license data
                            cmsLicensesApi.getCmsLicense(this.license.id)
                                .then((getCmsLicenseResponse) => {
                                    this.$emit('update:license', getCmsLicenseResponse.data)
                                    this.$store.commit('app/updateRenewLicense', getCmsLicenseResponse.data)
                                    cb(response)
                                })
                                .catch((getCmsLicenseError) => {
                                    cbError(getCmsLicenseError.response)
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
             * Cancel edit domain.
             */
            cancelEditDomain() {
                this.licenseDraft.domain = this.license.domain
                this.domainEditing = false
                this.domainValidates = false
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
                if (this.licenseDraft.notes !== this.license.notes) {
                    this.notesValidates = true
                }
            },

            /**
             * Domain changes.
             */
            domainChange() {
                this.domainValidates = false
                if (this.licenseDraft.domain !== this.license.domain) {
                    this.domainValidates = true
                }
            },

            /**
             * Copy license
             */
            copyLicense() {
                let $temp = document.createElement('input')
                document.body.appendChild($temp)
                $temp.value = this.license.key
                $temp.select()
                document.execCommand("copy")
                $temp.remove()

                this.$store.dispatch('app/displayNotice', 'License key copied.')
            },
        },

        mounted() {
            this.licenseDraft = {
                autoRenew: (this.license.autoRenew == 1 ? true : false),
                domain: this.license.domain,
                notes: this.license.notes,
            }
        }
    }
</script>
