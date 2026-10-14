<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

/**
 * Interactive Seekable Voice Note Player with Waveform Visualization and Playback Speed Control.
 *
 * // YB - 14-09-2026 code comment
 */
const props = defineProps({
    audioUrl: {
        type: String,
        required: true,
    },
    duration: {
        type: Number,
        default: 0,
    },
    waveform: {
        type: Array,
        default: () => [],
    },
    isSender: {
        type: Boolean,
        default: false,
    },
});

const isPlaying = ref(false);
const currentTime = ref(0);
const playbackRate = ref(1);
const speeds = [1, 1.5, 2];
const speedIndex = ref(0);

let audioElement = null;

// Normalize waveform bars (ensure 24-36 visual bars)
const displayBars = computed(() => {
    if (props.waveform && props.waveform.length >= 10) {
        // Sample down or keep up to 32 bars
        if (props.waveform.length <= 32) return props.waveform;
        const step = props.waveform.length / 32;
        const sampled = [];
        for (let i = 0; i < 32; i++) {
            sampled.push(props.waveform[Math.floor(i * step)] || 20);
        }
        return sampled;
    }
    // Fallback pseudo waveform if none recorded
    const bars = [24, 45, 78, 90, 65, 43, 89, 95, 70, 50, 80, 92, 60, 40, 75, 85, 90, 55, 30, 65, 80, 45, 25, 60];
    return bars;
});

const progressPercentage = computed(() => {
    const total = props.duration || audioElement?.duration || 1;
    if (total <= 0) return 0;
    return Math.min(100, (currentTime.value / total) * 100);
});

const formattedTime = computed(() => {
    const sec = isPlaying.value ? Math.floor(currentTime.value) : (props.duration || 0);
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return `${m}:${s < 10 ? '0' : ''}${s}`;
});

const togglePlay = () => {
    if (!audioElement) return;

    if (isPlaying.value) {
        audioElement.pause();
    } else {
        audioElement.play().catch(e => console.warn('Audio play error:', e));
    }
};

const seek = (index) => {
    if (!audioElement) return;
    const total = props.duration || audioElement.duration || 1;
    const ratio = index / displayBars.value.length;
    audioElement.currentTime = ratio * total;
    currentTime.value = audioElement.currentTime;
};

const cycleSpeed = () => {
    speedIndex.value = (speedIndex.value + 1) % speeds.length;
    playbackRate.value = speeds[speedIndex.value];
    if (audioElement) {
        audioElement.playbackRate = playbackRate.value;
    }
};

onMounted(() => {
    if (typeof Audio !== 'undefined') {
        audioElement = new Audio(props.audioUrl);
        audioElement.playbackRate = playbackRate.value;

        audioElement.ontimeupdate = () => {
            currentTime.value = audioElement.currentTime;
        };

        audioElement.onplay = () => {
            isPlaying.value = true;
        };

        audioElement.onpause = () => {
            isPlaying.value = false;
        };

        audioElement.onended = () => {
            isPlaying.value = false;
            currentTime.value = 0;
        };
    }
});

watch(() => props.audioUrl, (newUrl) => {
    if (audioElement) {
        audioElement.pause();
        audioElement.src = newUrl;
        currentTime.value = 0;
        isPlaying.value = false;
    }
});

onUnmounted(() => {
    if (audioElement) {
        audioElement.pause();
        audioElement = null;
    }
});
</script>

<template>
    <div
        class="flex items-center gap-3 py-1 px-1 min-w-[220px] max-w-[290px] select-none"
        :class="isSender ? 'text-white' : 'text-slate-800 dark:text-slate-100'"
    >
        <!-- Play / Pause Button -->
        <button
            type="button"
            @click.stop="togglePlay"
            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full transition-all duration-200 active:scale-95 shadow-sm"
            :class="isSender
                ? 'bg-white text-brand-600 hover:bg-slate-100'
                : 'bg-brand-600 text-white hover:bg-brand-700'"
            :aria-label="isPlaying ? 'Pause voice message' : 'Play voice message'"
        >
            <svg v-if="!isPlaying" class="h-5 w-5 fill-current ml-0.5" viewBox="0 0 24 24">
                <path d="M8 5v14l11-7z" />
            </svg>
            <svg v-else class="h-5 w-5 fill-current" viewBox="0 0 24 24">
                <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
            </svg>
        </button>

        <!-- Waveform visualizer & Time -->
        <div class="flex flex-1 flex-col gap-1 min-w-0">
            <!-- Clickable waveform bars -->
            <div class="flex items-center gap-[2px] h-7 cursor-pointer" @click.stop>
                <div
                    v-for="(height, idx) in displayBars"
                    :key="idx"
                    @click="seek(idx)"
                    class="flex-1 rounded-full transition-all duration-75 hover:opacity-100"
                    :style="{
                        height: `${Math.max(12, Math.min(100, height))}%`,
                    }"
                    :class="[
                        (idx / displayBars.length) * 100 <= progressPercentage
                            ? (isSender ? 'bg-white' : 'bg-brand-600 dark:bg-brand-400')
                            : (isSender ? 'bg-white/40' : 'bg-slate-300 dark:bg-slate-600')
                    ]"
                ></div>
            </div>

            <!-- Footer: Timestamp & Speed -->
            <div class="flex items-center justify-between text-[11px] font-medium opacity-85">
                <span>{{ formattedTime }}</span>
                <button
                    type="button"
                    @click.stop="cycleSpeed"
                    class="rounded px-1.5 py-0.5 text-[10px] font-bold tracking-tight transition-colors"
                    :class="isSender
                        ? 'bg-white/20 hover:bg-white/30 text-white'
                        : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300'"
                >
                    {{ playbackRate }}x
                </button>
            </div>
        </div>
    </div>
</template>
