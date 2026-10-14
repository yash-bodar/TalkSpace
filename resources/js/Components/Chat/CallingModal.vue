<script setup>
import { computed } from 'vue';

/**
 * WebRTC Calling Modal for outgoing and active audio/video calls.
 *
 * // YB - 14-09-2026 code comment
 */
const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    callType: {
        type: String, // 'audio', 'video'
        default: 'audio',
    },
    callStatus: {
        type: String, // 'calling', 'connected', 'ended'
        default: 'calling',
    },
    partnerName: {
        type: String,
        default: 'Contact',
    },
    partnerAvatar: {
        type: String,
        default: '',
    },
    formattedDuration: {
        type: String,
        default: '00:00',
    },
    isMuted: {
        type: Boolean,
        default: false,
    },
    isVideoOff: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['endCall', 'toggleMute', 'toggleVideo']);

const statusLabel = computed(() => {
    if (props.callStatus === 'calling') return 'Calling...';
    if (props.callStatus === 'connected') return props.formattedDuration;
    if (props.callStatus === 'ended') return 'Call Ended';
    return 'Connecting...';
});
</script>

<template>
    <teleport to="body">
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[120] flex items-center justify-center bg-black/80 backdrop-blur-md p-4 select-none"
            >
                <div class="relative flex flex-col items-center justify-between w-full max-w-md h-[520px] rounded-3xl bg-slate-900 border border-slate-800 text-white p-6 shadow-2xl overflow-hidden">
                    
                    <!-- Call Video Containers (when Video Call) -->
                    <div v-if="callType === 'video'" class="absolute inset-0 z-0 bg-slate-950">
                        <slot name="remoteVideo"></slot>
                        <div class="absolute top-4 right-4 w-28 h-40 rounded-2xl overflow-hidden border-2 border-slate-700 shadow-xl bg-slate-900 z-10">
                            <slot name="localVideo"></slot>
                        </div>
                    </div>

                    <!-- Header: Partner Info -->
                    <div class="relative z-10 flex flex-col items-center mt-6">
                        <div class="relative mb-4">
                            <img
                                :src="partnerAvatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(partnerName)"
                                class="h-24 w-24 rounded-full object-cover border-4 border-slate-800 shadow-2xl"
                                alt="Partner Avatar"
                            />
                            <span
                                v-if="callStatus === 'calling'"
                                class="animate-ping absolute inset-0 rounded-full border-2 border-brand-500 opacity-60"
                            ></span>
                        </div>
                        <h3 class="text-xl font-bold tracking-tight mb-1">{{ partnerName }}</h3>
                        <span
                            class="text-xs font-semibold px-3 py-1 rounded-full"
                            :class="callStatus === 'connected' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-800 text-slate-400'"
                        >
                            {{ statusLabel }}
                        </span>
                    </div>

                    <!-- Body / Middle space -->
                    <div class="relative z-10 flex-1"></div>

                    <!-- Controls Toolbar -->
                    <div class="relative z-10 flex items-center gap-4 mb-4">
                        <!-- Mute / Unmute -->
                        <button
                            type="button"
                            @click="emit('toggleMute')"
                            class="flex h-12 w-12 items-center justify-center rounded-full transition-all active:scale-95"
                            :class="isMuted ? 'bg-rose-500 text-white' : 'bg-slate-800 hover:bg-slate-700 text-white'"
                            title="Toggle microphone"
                        >
                            <svg v-if="!isMuted" class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                            </svg>
                            <svg v-else class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                            </svg>
                        </button>

                        <!-- End Call Button -->
                        <button
                            type="button"
                            @click="emit('endCall')"
                            class="flex h-14 w-14 items-center justify-center rounded-full bg-rose-600 hover:bg-rose-700 text-white shadow-xl active:scale-95 transition-all"
                            title="End call"
                        >
                            <svg class="h-6 w-6 fill-current" viewBox="0 0 24 24">
                                <path d="M12 9c-1.6 0-3.15.25-4.6.72v3.1c0 .39-.23.74-.56.9-.98.49-1.87 1.12-2.66 1.85-.18.18-.43.28-.7.28-.28 0-.53-.11-.71-.29L.29 13.08c-.18-.17-.29-.42-.29-.7 0-.28.11-.53.29-.71C3.34 8.78 7.46 7 12 7s8.66 1.78 11.71 4.67c.18.18.29.43.29.71 0 .28-.11.53-.29.71l-2.48 2.48c-.18.18-.43.29-.71.29-.27 0-.52-.11-.7-.28-.79-.74-1.69-1.36-2.67-1.85-.33-.16-.56-.5-.56-.9v-3.1C15.15 9.25 13.6 9 12 9z"/>
                            </svg>
                        </button>

                        <!-- Camera Toggle (if Video Call) -->
                        <button
                            v-if="callType === 'video'"
                            type="button"
                            @click="emit('toggleVideo')"
                            class="flex h-12 w-12 items-center justify-center rounded-full transition-all active:scale-95"
                            :class="isVideoOff ? 'bg-rose-500 text-white' : 'bg-slate-800 hover:bg-slate-700 text-white'"
                            title="Toggle camera"
                        >
                            <svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>
