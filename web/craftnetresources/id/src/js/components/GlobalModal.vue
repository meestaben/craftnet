<template>
    <modal :show="showGlobalModal" @background-click="close()">
        <template slot="body">
            <component v-if="globalModalComponent" :is="globalModalComponent" @close="close()"></component>
        </template>
    </modal>
</template>

<script>
    import {mapState} from 'vuex'
    import Modal from './Modal'
    import SupportPlanModal from './SupportPlanModal'

    export default {
        components: {
            Modal,
            SupportPlanModal,
        },

        computed: {
            ...mapState({
                globalModalComponent: state => state.app.globalModalComponent,
                showGlobalModal: state => state.app.showGlobalModal,
            }),
        },

        methods: {
            close() {
                this.$store.commit('app/updateShowGlobalModal', false)
            }
        }
    }
</script>