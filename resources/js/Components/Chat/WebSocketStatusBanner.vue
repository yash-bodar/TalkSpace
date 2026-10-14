<script setup>
/**
 * WebSocket status banner for network drops, reconnecting states, and recovery badges.
 *
 * // YB - 14-09-2026 code comment
 */
defineProps({
    status: {
        type: String, // 'connected', 'connecting', 'disconnected', 'unavailable'
        default: 'connected',
    },
});
</script>

<template>
    <transition
        enter-active-class="transition ease-out duration-300 transform"
        enter-from-class="-translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200 transform"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-full opacity-0"
    >
        <div
            v-if="status !== 'connected'"
            class="sticky top-0 z-50 flex items-center justify-center gap-2 px-4 py-1.5 text-xs font-medium text-white shadow-md transition-colors"
            :class="{
                'bg-amber-600': status === 'connecting',
                'bg-rose-600': status === 'disconnected' || status === 'unavailable',
            }"
        >
            <span
                class="inline-block h-2 w-2 rounded-full"
                :class="{
                    'bg-white animate-ping': status === 'connecting',
                    'bg-white': status !== 'connecting',
                }"
            ></span>
            <span v-if="status === 'connecting'">Reconnecting to TalkSpace real-time server...</span>
            <span v-else-if="status === 'unavailable' || status === 'disconnected'">
                Network offline. Reconnecting automatically...
            </span>
        </div>
    </transition>
</template>
