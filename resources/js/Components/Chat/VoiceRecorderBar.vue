<script setup>
import { computed } from 'vue';

/**
 * Bottom audio recording bar replacing input area when user is recording a voice note.
 *
 * // YB - 14-09-2026 code comment
 */
const props = defineProps({
    duration: {
        type: Number,
        default: 0,
    },
    waveformData: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['cancel', 'send']);

const formattedDuration = computed(() => {
    const m = Math.floor(props.duration / 60);
    const s = props.duration % 60;
    return `${m}:${s < 10 ? '0' : ''}${s}`;
});
</script>

<template>
    <div class="flex w-full items-center justify-between gap-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 rounded-2xl px-4 py-2.5 shadow-sm animate-pulse-subtle">
        <!-- Live Recording Indicator & Timer -->
        <div class="flex items-center gap-2.5">
            <span class="flex h-3 w-3 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-600"></span>
            </span>
            <span class="text-xs font-semibold text-rose-600 dark:text-rose-400 font-mono">
                {{ formattedDuration }}
            </span>
        </div>

        <!-- Live Waveform Visualizer -->
        <div class="flex flex-1 items-center justify-center gap-1 h-6 max-w-[200px] overflow-hidden">
            <div
                v-for="(val, idx) in waveformData.slice(-24)"
                :key="idx"
                class="w-1 bg-rose-500 dark:bg-rose-400 rounded-full transition-all duration-75"
                :style="{ height: `${Math.max(15, val)}%` }"
            ></div>
        </div>

        <!-- Actions: Cancel & Send -->
        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="emit('cancel')"
                class="flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:text-rose-600 hover:bg-rose-100 dark:hover:bg-rose-900/50 transition-colors"
                title="Cancel voice recording"
            >
                <svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <button
                type="button"
                @click="emit('send')"
                class="flex h-9 w-9 items-center justify-center rounded-full bg-rose-600 hover:bg-rose-700 text-white shadow-md active:scale-95 transition-transform"
                title="Send voice note"
            >
                <svg class="h-4 w-4 fill-current ml-0.5" viewBox="0 0 24 24">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                </svg>
            </button>
        </div>
    </div>
</template>
