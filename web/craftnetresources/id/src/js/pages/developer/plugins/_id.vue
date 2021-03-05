<template>
    <div class="mb-3">
        <p>
            <router-link class="nav-link" to="/developer/plugins" exact>←
                Plugins
            </router-link>
        </p>

        <h1 v-if="plugin">{{ plugin.name }}</h1>
        <h1 v-else>Add a plugin</h1>

        <template v-if="!pluginId && !this.pluginDraft.repository">
            <div class="card">
                <div class="card-body">
                    <p>To get started, select a repository for your plugin.</p>

                    <template v-if="appsLoading">
                        <spinner></spinner>
                    </template>
                    <template v-else>
                        <template v-if="connectedAppsCount > 0">
                            <div v-for="(app, appHandle) in apps" class="mb-3"
                                 :key="appHandle">
                                <repositories :appHandle="appHandle"
                                              :loading-repository="loadingRepository"
                                              @selectRepository="onSelectRepository"></repositories>
                            </div>
                        </template>
                        <template v-else>
                            <h2>Connect</h2>
                            <p>Connect to GitHub to retrieve your
                                repositories.</p>
                            <connected-apps></connected-apps>
                        </template>
                    </template>

                    <div class="mt-2">
                        <btn to="/developer/settings">Manage connected apps
                        </btn>
                    </div>
                </div>
            </div>
        </template>

        <template v-else>
            <div v-if="plugin && !plugin.enabled" role="alert"
                 class="alert alert-info">

                <template v-if="plugin.pendingApproval">
                    Your plugin is being reviewed, it will be automatically
                    published once it’s approved.
                </template>
                <template v-else>
                    <template
                        v-if="plugin.lastHistoryNote && plugin.lastHistoryNote.devComments">
                        <h6>Changes requested</h6>
                        <div v-html="plugin.lastHistoryNote.devComments"></div>
                        <btn small @click="submit()">Re-submit for Approval
                        </btn>
                    </template>
                    <template v-else>
                        <btn small @click="submit()">Submit for Approval</btn>
                    </template>

                    <span class="text-secondary">Your plugin will be automatically published once it’s approved.</span>
                </template>
                <spinner v-if="pluginSubmitLoading"></spinner>
            </div>

            <form @submit.prevent="save()">
                <div class="card mb-6">
                    <div class="card-header">GitHub Repository</div>
                    <div class="card-body">
                        <textbox id="repository" label="Repository URL"
                                 v-model="pluginDraft.repository"
                                 :errors="errors.repository" :disabled="true"/>
                    </div>
                </div>

                <div class="card mb-6">
                    <div class="card-header">Plugin Icon</div>
                    <div class="card-body">
                        <div class="flex">
                            <div class="mr-6">
                                <field>
                                    <img :src="pluginDraft.iconUrl"
                                         class="w-16 h-16"/>
                                </field>
                            </div>
                            <div class="flex-1">
                                <field>
                                    <div class="instructions">
                                        <p>The plugin icon must be a square SVG
                                            file that should not exceed
                                            {{ maxUploadSize }}, and should not
                                            contain embedded images or
                                            fonts.</p>
                                    </div>
                                    <input type="file" ref="iconFile"
                                           class="form-control"
                                           @change="changeIcon"
                                           :class="{'is-invalid': errors.iconId }"/>
                                    <div class="invalid-feedback"
                                         v-for="(error, errorKey) in iconErrors"
                                         :key="'plugin-icon-error-' + errorKey">
                                        {{ error }}
                                    </div>
                                </field>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-6">
                    <div class="card-header">Plugin Details</div>
                    <div class="card-body">
                        <div class="flex flex-wrap -mx-4">
                            <div class="w-1/2 px-4">
                                <textbox id="name" label="Name"
                                         v-model="pluginDraft.name"
                                         :errors="errors.name"
                                         @input="onInputName"/>
                            </div>
                            <div class="w-1/2 px-4">
                                <textbox id="packageName" label="Package Name"
                                         v-model="pluginDraft.packageName"
                                         :errors="errors.packageName"
                                         :disabled="true"/>
                            </div>
                            <div class="w-1/2 px-4">
                                <textbox id="handle" label="Plugin Handle"
                                         v-model="pluginDraft.handle"
                                         :errors="errors.handle"
                                         :disabled="true"/>
                            </div>
                            <div class="w-1/2 px-4">
                                <dropdown :fullwidth="true" label="License"
                                          v-model="pluginDraft.license"
                                          :options="[
                                    {label: 'Craft', value: 'craft'},
                                    {label: 'MIT', value: 'mit'},
                                ]"/>
                            </div>
                        </div>

                        <textbox id="shortDescription" label="Short Description"
                                 v-model="pluginDraft.shortDescription"
                                 :errors="errors.shortDescription"/>
                        <textbox type="textarea" id="longDescription"
                                 label="Long Description"
                                 v-model="pluginDraft.longDescription"
                                 :errors="errors.longDescription" rows="16"/>
                        <p class="text-secondary"><small>Styling with Markdown
                            is supported.</small></p>
                        <textbox id="documentationUrl" label="Documentation URL"
                                 v-model="pluginDraft.documentationUrl"
                                 :errors="errors.documentationUrl"/>
                        <textbox id="changelogPath" label="Changelog Path"
                                 instructions="The path to your changelog file, relative to the repository root."
                                 v-model="pluginDraft.changelogPath"
                                 :errors="errors.changelogPath"/>

                        <plugin-categories
                            :plugin-draft="pluginDraft"></plugin-categories>

                        <textbox id="keywords" label="Keywords"
                                 instructions="Comma-separated list of keywords."
                                 v-model="pluginDraft.keywords"
                                 :errors="errors.keywords"/>

                        <lightswitch
                            id="abandoned"
                            label="Abandoned?"
                            :checked.sync="pluginDraft.abandoned"
                        />

                        <template v-if="pluginDraft.abandoned">
                            <textbox id="replacement" label="Replacement Plugin"
                                     instructions="The handle of the replacement plugin. Leave this field empty if you don’t want to specify a replacement plugin."
                                     v-model="pluginDraft.replacementHandle"
                                     />
                        </template>
                    </div>
                </div>

                <div class="card mb-6">
                    <div class="card-header">Screenshots</div>
                    <div class="card-body">
                        <field
                            :instructions="'Plugin screenshots must be JPG or PNG files, and should not exceed '+maxUploadSize+'.'">
                            <input type="file" ref="screenshotFiles"
                                   class="form-control" multiple="">
                        </field>

                        <div ref="screenshots" class="d-inline">

                            <draggable v-model="screenshots">
                                <div v-for="(screenshot, key) in screenshots"
                                     class="screenshot" :key="key">
                                    <img :src="screenshot.url"
                                         class="img-thumbnail mr-3 mb-3"/>
                                    <btn icon="times" kind="danger"
                                         @click="removeScreenshot(key);"></btn>
                                </div>
                            </draggable>

                        </div>
                    </div>
                </div>

                <div class="card mb-6">
                    <div class="card-header">Editions</div>
                    <div class="card-body">
                        <div
                            v-for="(edition, editionKey) in pluginDraft.editions"
                            :key="'edition-' + editionKey">
                            <div class="flex">
                                <div class="w-1/4">
                                    <h2>{{ edition.name }}</h2>
                                    <p class="text-grey">
                                        <code>{{ edition.handle }}</code></p>
                                </div>
                                <div class="w-3/4">
                                    <textbox :id="edition.handle+'-price'"
                                             label="License Price"
                                             v-model="edition.price"
                                             :errors="errors['editions['+editionKey+'].price']"
                                             :disabled="plugin && plugin.enabled && !parseFloat(edition.price)"/>
                                    <textbox
                                        :id="edition.handle+'-renewalPrice'"
                                        label="Renewal Price"
                                        v-model="edition.renewalPrice"
                                        :errors="errors['editions['+editionKey+'].renewalPrice']"
                                        :disabled="plugin && plugin.enabled && !parseFloat(edition.price)"/>

                                    <field
                                        v-if="pluginDraft.editions.length > 1"
                                        id="features" label="Features">
                                        <table
                                            v-if="edition.features.length > 0"
                                            id="features"
                                            class="table border mb-4">
                                            <thead>
                                            <tr>
                                                <th class="w-1/3">Name</th>
                                                <th>Description</th>
                                                <th></th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="(feature, featureKey) in edition.features"
                                                :key="'feature-'+featureKey">
                                                <td>
                                                    <textbox
                                                        :id="edition.handle+'-featureName'"
                                                        v-model="feature.name"/>
                                                </td>
                                                <td>
                                                    <textbox
                                                        :id="edition.handle+'-featureDescription'"
                                                        v-model="feature.description"/>
                                                </td>
                                                <td class="w-10 text-center">
                                                    <a @click.prevent="removeFeature(editionKey, featureKey)">
                                                        <icon icon="times"
                                                              cssClass="text-red"/>
                                                    </a>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>

                                        <div>
                                            <btn icon="plus"
                                                 @click="addFeature(editionKey)">
                                                Add a feature
                                            </btn>
                                        </div>
                                    </field>
                                </div>
                            </div>
                            <hr/>
                        </div>

                        <p class="text-center">To manage your editions, please
                            <a href="mailto:hello@craftcms.com">contact us</a>.
                        </p>
                    </div>
                </div>

                <div>
                    <btn kind="primary" type="submit" :disabled="loading"
                         :loading="loading">Save
                    </btn>
                </div>
            </form>
        </template>
    </div>
</template>

<script>
/* global Craft */

import {mapState, mapGetters} from 'vuex'
import pluginsApi from '../../../api/plugins'
import ConnectedApps from '../../../components/developer/connected-apps/ConnectedApps'
import Repositories from '../../../components/developer/Repositories'
import PluginCategories from '../../../components/developer/PluginCategories'
import slug from 'limax'
import draggable from 'vuedraggable'
import qs from 'qs'

export default {
    components: {
        ConnectedApps,
        Repositories,
        PluginCategories,
        draggable,
    },

    data() {
        return {
            loading: false,
            pluginSubmitLoading: false,
            repositoryLoading: false,
            loadingRepository: null,
            pluginDraft: {
                id: null,
                icon: null,
                iconId: null,
                developerId: null,
                editions: [
                    {
                        name: 'Standard',
                        handle: 'standard',
                        features: []
                    }
                ],
                enabled: false,
                handle: '',
                packageName: '',
                name: '',
                shortDescription: '',
                longDescription: '',
                documentationUrl: '',
                changelogPath: '',
                repository: '',
                license: 'craft',
                price: 0,
                renewalPrice: 0,
                iconUrl: null,
                categoryIds: [],
                screenshots: [],
                screenshotIds: [],
                screenshotUrls: [],
                keywords: '',
                abandoned: false,
                replacementHandle: null,
            },
            errors: {},
        }
    },

    computed: {
        ...mapState({
            apps: state => state.apps.apps,
            appsLoading: state => state.apps.appsLoading,
            plugins: state => state.plugins.plugins,
        }),

        ...mapGetters({
            userIsInGroup: 'account/userIsInGroup',
        }),

        pluginId() {
            return this.$route.params.id
        },

        plugin() {
            return this.plugins.find(p => p.id == this.pluginId)
        },

        connectedAppsCount() {
            return Object.keys(this.apps).length
        },

        screenshots: {
            get() {
                let screenshots = []

                this.pluginDraft.screenshotIds.forEach((screenshotId, index) => {
                    let screenshot = {
                        id: screenshotId,
                        url: this.pluginDraft.screenshotUrls[index],
                    }
                    screenshots.push(screenshot)
                })

                return screenshots
            },

            set(screenshots) {
                let screenshotIds = []
                let screenshotUrls = []

                screenshots.forEach(screenshot => {
                    screenshotIds.push(screenshot.id)
                    screenshotUrls.push(screenshot.url)
                })

                this.pluginDraft.screenshotIds = screenshotIds
                this.pluginDraft.screenshotUrls = screenshotUrls
            }
        },

        maxUploadSize() {
            return this.humanFileSize(Craft.maxUploadSize)
        },

        iconErrors() {
            let errors = []

            if (this.errors.iconId) {
                errors = [...errors, ...this.errors.iconId]
            }

            if (this.errors.icon) {
                errors = [...errors, ...this.errors.icon]
            }

            return errors;
        }
    },

    methods: {
        /**
         * On input name.
         *
         * @param name
         */
        onInputName(name) {
            if (!this.pluginId) {
                const handle = slug(name)
                this.pluginDraft.handle = handle
            }
        },

        /**
         * On select repository.
         *
         * @param repository
         */
        onSelectRepository(repository) {
            this.loadDetails(repository.html_url)
        },

        /**
         * Remove screenshot.
         *
         * @param key
         */
        removeScreenshot(key) {
            this.pluginDraft.screenshotUrls.splice(key, 1)
            this.pluginDraft.screenshotIds.splice(key, 1)

            let removeBtns = this.$refs.screenshots.getElementsByClassName('btn')

            for (let i = 0; i < removeBtns.length; i++) {
                removeBtns[i].blur()
            }
        },

        /**
         * Change screenshots.
         */
        changeScreenshots() {
            this.pluginDraft.screenshotUrls = []

            let files = this.$refs.screenshotFiles.files

            for (let i = 0; i < files.length; i++) {
                let reader = new FileReader()

                reader.onload = function(e) {
                    let screenshotUrl = e.target.result
                    this.pluginDraft.screenshotUrls.push(screenshotUrl)
                }.bind(this)

                reader.readAsDataURL(files[i])
            }
        },

        /**
         * Change icon.
         *
         * @param ev
         */
        changeIcon(ev) {
            this.pluginDraft.icon = ev.target.value

            let reader = new FileReader()

            reader.onload = function(e) {
                this.pluginDraft.iconUrl = e.target.result
            }.bind(this)

            reader.readAsDataURL(this.$refs.iconFile.files[0])
        },

        /**
         * Load details.
         *
         * @param repositoryUrl
         */
        loadDetails(repositoryUrl) {
            this.repositoryLoading = true
            this.loadingRepository = repositoryUrl

            let body = {
                repository: encodeURIComponent(url)
            }

            let params = qs.stringify(body)
            let url = repositoryUrl

            pluginsApi.loadDetails(url, params)
                .then((response) => {
                    this.repositoryLoading = false
                    this.loadingRepository = null

                    if (response.data.error) {
                        this.$store.dispatch('app/displayError', response.data.error)
                    } else {
                        this.pluginDraft.repository = repositoryUrl

                        if (response.data.changelogPath) {
                            this.pluginDraft.changelogPath = response.data.changelogPath
                        }

                        if (response.data.documentationUrl) {
                            this.pluginDraft.documentationUrl = response.data.documentationUrl
                        }

                        if (response.data.name) {
                            this.pluginDraft.name = response.data.name
                        }

                        if (response.data.handle) {
                            this.pluginDraft.handle = response.data.handle
                        }

                        if (response.data.shortDescription) {
                            this.pluginDraft.shortDescription = response.data.shortDescription
                        }

                        if (response.data.packageName) {
                            this.pluginDraft.packageName = response.data.packageName
                        }

                        if (response.data.iconId) {
                            this.pluginDraft.iconId = response.data.iconId
                        }

                        if (response.data.iconUrl) {
                            this.pluginDraft.iconUrl = response.data.iconUrl
                        }

                        if (response.data.license) {
                            this.pluginDraft.license = response.data.license
                        }

                        if (response.data.keywords) {
                            this.pluginDraft.keywords = response.data.keywords.join(', ')
                        }
                    }
                })
                .catch((error) => {
                    this.repositoryLoading = false
                    const errorMessage = error.response.data && error.response.data.error ? error.response.data.error : 'Couldn’t load repository.'
                    this.$store.dispatch('app/displayError', errorMessage)
                })
        },

        /**
         * Save the plugin.
         */
        save() {
            this.loading = true

            let plugin = {
                icon: this.$refs.iconFile.files[0],
                handle: this.pluginDraft.handle,
                packageName: this.pluginDraft.packageName,
                name: this.pluginDraft.name,
                shortDescription: this.pluginDraft.shortDescription,
                longDescription: this.pluginDraft.longDescription,
                documentationUrl: this.pluginDraft.documentationUrl,
                changelogPath: this.pluginDraft.changelogPath,
                repository: this.pluginDraft.repository,
                license: this.pluginDraft.license,
                keywords: this.pluginDraft.keywords,
                categoryIds: [],
                screenshotIds: [],
                editions: this.pluginDraft.editions,
                abandoned: this.pluginDraft.abandoned,
                replacementHandle: this.pluginDraft.replacementHandle,
            }

            if (this.pluginDraft.iconId) {
                plugin.iconId = [parseInt(this.pluginDraft.iconId)]
            }

            if (this.pluginDraft.id) {
                plugin.pluginId = this.pluginDraft.id
            }

            if (this.pluginDraft.categoryIds.length > 0) {
                plugin.categoryIds = this.pluginDraft.categoryIds
            }

            if (this.$refs.screenshotFiles.files.length > 0) {
                plugin.screenshots = this.$refs.screenshotFiles.files
            }

            if (this.pluginDraft.screenshotUrls.length > 0) {
                plugin.screenshotUrls = this.pluginDraft.screenshotUrls
            }

            if (this.pluginDraft.screenshotIds.length > 0) {
                plugin.screenshotIds = this.pluginDraft.screenshotIds
            }

            this.$store.dispatch('plugins/savePlugin', {plugin})
                .then(() => {
                    this.loading = false
                    this.$store.dispatch('app/displayNotice', 'Plugin saved.')
                    this.$router.push({path: '/developer/plugins'})
                })
                .catch((response) => {
                    this.loading = false
                    this.errors = response.data && response.data.errors ? response.data.errors : {}

                    const errorMessage = response.data && response.data.error ? response.data.error : 'Couldn’t save plugin.'
                    this.$store.dispatch('app/displayError', errorMessage)
                })
        },

        /**
         * Submit plugin for approval.
         */
        submit() {
            this.pluginSubmitLoading = true
            this.$store.dispatch('plugins/submitPlugin', this.plugin.id)
                .then(() => {
                    this.pluginSubmitLoading = false
                    this.$store.dispatch('app/displayNotice', 'Plugin submitted for approval.')
                })
                .catch((response) => {
                    this.pluginSubmitLoading = false
                    this.errors = response.data && response.data.errors ? response.data.errors : {}

                    const errorMessage = response.data && response.data.error ? response.data.error : 'Couldn’t submit plugin for approval.'
                    this.$store.dispatch('app/displayError', errorMessage)
                })
        },

        /**
         * Human file size.
         *
         * @param bytes
         * @returns {string}
         */
        humanFileSize(bytes) {
            const threshold = 1024

            if (bytes < threshold) {
                return bytes + ' B'
            }

            const units = ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']

            let u = -1

            do {
                bytes = bytes / threshold
                ++u
            }
            while (bytes >= threshold)

            return bytes.toFixed(1) + ' ' + units[u]
        },

        addFeature(editionKey) {
            this.pluginDraft.editions[editionKey].features.push({})
        },

        removeFeature(editionKey, featureKey) {
            this.pluginDraft.editions[editionKey].features.splice(featureKey, 1)
        }
    },

    mounted() {
        this.$store.dispatch('apps/getApps')
            .catch((response) => {
                const errorMessage = response.data && response.data.error ? response.data.error : 'Couldn’t get apps.'
                this.$store.dispatch('app/displayError', errorMessage)
            })

        if (this.plugin) {
            this.pluginDraft = JSON.parse(JSON.stringify(this.plugin))

            if (!this.pluginDraft.price) {
                this.pluginDraft.price = 0
            }

            if (!this.pluginDraft.renewalPrice) {
                this.pluginDraft.renewalPrice = 0
            }
        } else {
            if (this.pluginId) {
                this.$router.push({path: '/developer/plugins'})
            }
        }
    },
}
</script>

<style lang="scss">
.screenshot {
    position: relative;
    display: inline-block;
    width: 230px;
    margin-right: 24px;
    margin-top: 14px;
}

.screenshot {
    .c-btn {
        position: absolute;
        top: -10px;
        right: -10px;
    }
}
</style>
