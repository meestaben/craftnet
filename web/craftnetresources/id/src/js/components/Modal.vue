<template>
    <transition :name="transition" @enter="$emit('enter')" @after-enter="$emit('after-enter')" @leave="$emit('leave')">
        <div v-if="show" class="modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true" @click="onBackgroundClick">
            <div class="modal-dialog" :class="modalTypeClass" role="document">
                <div class="modal-content">
                    <div v-if="$slots.header" class="modal-header">
                        <h5 class="modal-title" id="modalLabel"><slot name="header"></slot></h5>
                    </div>
                    <div class="modal-body">
                        <slot name="body"></slot>
                    </div>

                    <div v-if="$slots.footer" class="modal-footer">
                        <slot name="footer"></slot>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<script>
    export default {
        props: {
            show: Boolean,
            transition: String,
            modalType: {default: 'dialog'}
        },

        computed: {
            modalTypeClass() {
                // 'dialog' (default) or 'wide' - see `.modal` styles
                return 'modal-' + this.modalType
            }
        },

        methods: {
            onBackgroundClick($ev) {
                if ($ev.target.classList.contains('modal')) {
                    this.$emit('background-click')
                }
            }
        }
    }
</script>

<style lang="scss">
    .modal {
        @apply .fixed .pin .block .z-10 .flex .items-center .content-center .justify-center;
        background: rgba(0,0,0,0.7);

        .modal-wide {
            @apply .w-full .max-w-lg;
        }

        .modal-content {
            @apply .flex .flex-col .bg-white .rounded .p-8;
            border: 1px solid rgba(0,0,0,.2);
        }
    }
</style>
