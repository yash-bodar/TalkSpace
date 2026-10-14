<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

/**
 * Fullscreen Media Lightbox for high-resolution photo & video viewing.
 *
 * // YB - 14-09-2026 code comment
 */
const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    mediaUrl: {
        type: String,
        default: '',
    },
    mediaName: {
        type: String,
        default: '',
    },
    mediaType: {
        type: String, // 'image', 'video'
        default: 'image',
    },
});

const emit = defineEmits(['close']);

const zoomScale = ref(1);

const zoomIn = () => {
    zoomScale.value = Math.min(3, zoomScale.value + 0.25);
};

const zoomOut = () => {
    zoomScale.value = Math.max(0.5, zoomScale.value - 0.25);
};

const resetZoom = () => {
    zoomScale.value = 1;
};

const handleKeyDown = (e) => {
    if (props.isOpen && e.key === 'Escape') {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <teleport to="body">
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[100] flex flex-col items-center justify-between bg-black/90 backdrop-blur-md p-4 select-none"
                @click="emit('close')"
            >
                <!-- Top Header Bar -->
                <div
                    class="flex w-full items-center justify-between text-white/90 px-4 py-2"
                    @click.stop
                >
                    <span class="text-sm font-medium truncate max-w-[400px]">
                        {{ mediaName || 'Media Attachment' }}
                    </span>

                    <div class="flex items-center gap-3">
                        <button
                            v-if="mediaType === 'image'"
                            type="button"
                            @click="zoomIn"
                            class="p-2 rounded-full hover:bg-white/10 text-white/80 hover:text-white transition-colors"
                            title="Zoom in"
                        >
                            <svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
                            </svg>
                        </button>
                        <button
                            v-if="mediaType === 'image'"
                            type="button"
                            @click="zoomOut"
                            class="p-2 rounded-full hover:bg-white/10 text-white/80 hover:text-white transition-colors"
                            title="Zoom out"
                        >
                            <svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7" />
                            </svg>
                        </button>
                        <a
                            :href="mediaUrl"
                            download
                            class="p-2 rounded-full hover:bg-white/10 text-white/80 hover:text-white transition-colors"
                            title="Download media"
                            @click.stop
                        >
                            <svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </a>
                        <button
                            type="button"
                            @click="emit('close')"
                            class="p-2 rounded-full hover:bg-white/10 text-white/80 hover:text-white transition-colors"
                            title="Close (Esc)"
                        >
                            <svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Media Display Area -->
                <div
                    class="flex flex-1 items-center justify-center overflow-hidden w-full max-h-[85vh]"
                    @click.stop
                >
                    <img
                        v-if="mediaType === 'image'"
                        :src="mediaUrl"
                        :alt="mediaName"
                        class="max-h-full max-w-full object-contain rounded-lg transition-transform duration-150 cursor-grab"
                        :style="{ transform: `scale(${zoomScale})` }"
                        @dblclick="resetZoom"
                    />
                    <video
                        v-else-if="mediaType === 'video'"
                        :src="mediaUrl"
                        controls
                        autoplay
                        class="max-h-full max-w-full rounded-lg shadow-2xl"
                    ></video>
                </div>

                <!-- Footer (Empty / padding) -->
                <div class="h-6"></div>
            </div>
        </transition>
    </teleport>
</template>
