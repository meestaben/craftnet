<template>
    <div>
        <div class="flex justify-between mb-2">
            <h1>Plugins</h1>

            <div>
                <btn kind="primary" icon="plus" to="/developer/add-plugin">Add a plugin</btn>
            </div>
        </div>

        <template v-if="loading">
            <spinner></spinner>
        </template>

        <template v-else>
            <div v-if="computedPlugins.length > 0" class="card card-table responsive-content">
                <table class="table">
                    <thead>
                    <tr>
                        <th></th>
                        <th>Name</th>
                        <th>Active Installs</th>
                        <th>Price</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(plugin, pluginKey) in computedPlugins" :key="pluginKey">
                        <td class="icon-col">
                            <router-link :to="'/developer/plugins/' + plugin.id">
                                <img v-if="plugin.iconUrl" :src="plugin.iconUrl" height="36" />
                                <img v-else src="~@/images/default-plugin.svg" height="36" />
                            </router-link>
                        </td>
                        <td class="name-col">
                            <router-link :to="'/developer/plugins/' + plugin.id">{{ plugin.name }}</router-link>
                            <small class="ml-2 text-secondary" v-if="plugin.latestVersion">{{ plugin.latestVersion }}</small>
                            <div>{{ plugin.shortDescription }}</div>
                        </td>
                        <td>{{ plugin.activeInstalls }}</td>
                        <td>
                            <div class="text-nowrap">
                                {{ fullPriceLabel(plugin.id) }}
                            </div>
                        </td>
                        <td>
                            <template v-if="plugin.enabled">
                                <span class="text-green">Approved</span>
                            </template>
                            <template v-else>

                                <span v-if="plugin.pendingApproval" class="text-secondary">In Review</span>
                                <template v-else>
                                    <span v-if="plugin.lastHistoryNote && plugin.lastHistoryNote.devComments" class="text-warning">Changes requested</span>
                                    <span v-else class="text-secondary">Prepare for submission</span>
                                </template>
                            </template>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <empty v-else>
                <empty>
                    <icon icon="plug" class="size-4xl mb-4 text-grey" />
                    <div class="font-bold">No plugins</div>
                    <div class="mt-4">
                        <p>You haven’t added any plugins yet.</p>
                    </div>
                </empty>
            </empty>
        </template>
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import Empty from '../../../components/Empty'
    import {currency} from '@/js/filters/currency'

    export default {
        components: {
            Empty,
        },

        data() {
            return {
                loading: false,
            }
        },

        computed: {
            ...mapState({
                plugins: state => state.plugins.plugins,
            }),

            computedPlugins() {
                let plugins = JSON.parse(JSON.stringify(this.plugins))

                plugins.sort((a, b) => {
                    if (a['name'].toLowerCase() < b['name'].toLowerCase()) {
                        return -1
                    }
                    if (a['name'].toLowerCase() > b['name'].toLowerCase()) {
                        return 1
                    }
                    return 0
                })

                return plugins
            },

            priceRanges() {
                let priceRanges = {}

                for (let i = 0; i < this.plugins.length; i++) {
                    const plugin = this.plugins[i]
                    let priceRange = this.getPriceRange(plugin.editions)
                    priceRanges[plugin.id] = priceRange
                }

                return priceRanges
            },
        },

        methods: {
            getPriceRange(editions) {
                let min = null
                let max = null

                for (let i = 0; i < editions.length; i++) {
                    const edition = editions[i]
                    const price = parseInt(edition.price)

                    if (min === null) {
                        min = price
                    }

                    if (max === null) {
                        max = price
                    }

                    if (price < min) {
                        min = price
                    }

                    if (price > max) {
                        max = price
                    }
                }

                return {
                    min,
                    max
                }
            },

            fullPriceLabel(pluginId) {
                const {min, max} = this.priceRanges[pluginId]

                if (min !== max) {
                    return `${this.priceLabel(min)}–${this.priceLabel(max)}`
                }

                return this.priceLabel(min)
            },

            priceLabel(price) {
                return price > 0 ? currency(price) : 'Free'
            }
        },

        mounted() {
            if (this.plugins.length === 0) {
                this.loading = true

                this.$store.dispatch('plugins/getPlugins')
                    .then(() => {
                        this.loading = false
                    })
                    .catch((response) => {
                        this.loading = false
                        const errorMessage = response.data && response.data.error ? response.data.error : 'Couldn’t get plugins.'
                        this.$store.dispatch('app/displayError', errorMessage)
                    })
            }
        }
    }
</script>
