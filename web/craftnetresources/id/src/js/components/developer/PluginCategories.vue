<template>
    <div>
        <field label="Categories" :instructions="'Pick up to '+maxCategories+' categories. ('+ pluginDraft.categoryIds.length +'/'+maxCategories+' selected)'">
            <template v-if="loading">
                <spinner></spinner>
            </template>

            <template v-else>
                <draggable v-model="pluginDraft.categoryIds">
                    <div class="alert float-left clearfix mb-3 mr-2 px-3 py-2" v-for="(category, key) in selectedCategories" :key="'selected-categories-' + key">
                        <div class="flex">
                            <div>{{category.title}}</div>
                            <div class="ml-3 mt-1">
                                <a @click.prevent="unselectCategory(category.id)">
                                    <icon icon="times" class="text-red" />
                                </a>
                            </div>
                        </div>
                    </div>
                </draggable>

                <div class="clearfix"></div>

                <div>
                    <div class="inline-block" v-for="(category, key) in availableCategories" :key="'available-category-' + key">
                        <btn class="mb-2 mr-2" icon="plus" :disabled="pluginDraft.categoryIds.length >= maxCategories" outline @click="selectCategory(category.id)">{{category.title}}</btn>
                    </div>
                </div>
            </template>
        </field>
    </div>
</template>

<script>
    import {mapState, mapActions} from 'vuex'
    import draggable from 'vuedraggable'

    export default {
        components: {
            draggable
        },

        props: ['pluginDraft'],

        data() {
            return {
                loading: false,
                maxCategories: 3,
            }
        },

        computed: {
            ...mapState({
                categories: state => state.pluginStore.categories,
            }),

            selectedCategories() {
                let categories = []

                this.pluginDraft.categoryIds.forEach(categoryId => {
                    const category = this.categories.find(c => c.id == categoryId)
                    categories.push(category)
                })

                return categories
            },

            availableCategories() {
                return this.categories.filter(category => {
                    return !this.pluginDraft.categoryIds.find(categoryId => categoryId == category.id)
                })
            },

            categoryOptions() {
                let options = []

                this.categories.forEach(category => {
                    let checked = this.pluginDraft.categoryIds.find(categoryId => categoryId == category.id)

                    let option = {
                        label: category.title,
                        value: category.id,
                        checked: checked,
                    }

                    options.push(option)
                })

                return options
            }
        },

        methods: {
            ...mapActions({
                getCoreData: 'pluginStore/getCoreData',
            }),

            /**
             * Select category.
             *
             * @param categoryId
             */
            selectCategory(categoryId) {
                if (this.pluginDraft.categoryIds.length < this.maxCategories) {
                    const exists = this.pluginDraft.categoryIds.find(catId => catId == categoryId)

                    if (!exists) {
                        this.pluginDraft.categoryIds.push(categoryId)
                    }
                }
            },

            /**
             * Unselect category.
             *
             * @param categoryId
             */
            unselectCategory(categoryId) {
                const i = this.pluginDraft.categoryIds.indexOf(categoryId)

                if (i !== -1) {
                    this.pluginDraft.categoryIds.splice(i, 1)
                }
            },
        },

        mounted() {
            this.loading = true

            this.getCoreData()
                .then(() => {
                    this.loading = false
                })
        }
    }
</script>
