<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';

/**
 * User Status Modal for setting personal presence status message & emoji.
 *
 * // YB - 14-09-2026 code comment
 */
const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    currentStatusText: {
        type: String,
        default: '',
    },
    currentStatusEmoji: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'updated']);

const statusText = ref(props.currentStatusText || '');
const statusEmoji = ref(props.currentStatusEmoji || '💬');
const expiresInMinutes = ref(0); // 0 = never/until cleared
const isSubmitting = ref(false);

const presets = [
    { emoji: '📅', text: 'In a meeting', duration: 60 },
    { emoji: '🚗', text: 'Commuting', duration: 30 },
    { emoji: '🤒', text: 'Out sick', duration: 1440 },
    { emoji: '🌴', text: 'Vacationing', duration: 10080 },
    { emoji: '🎯', text: 'Focusing', duration: 120 },
    { emoji: '🏠', text: 'Working remotely', duration: 480 },
];

const selectPreset = (preset) => {
    statusEmoji.value = preset.emoji;
    statusText.value = preset.text;
    expiresInMinutes.value = preset.duration;
};

const handleSave = async () => {
    isSubmitting.value = true;
    try {
        const res = await axios.post(route('user.status.update'), {
            status_text: statusText.value,
            status_emoji: statusEmoji.value,
            expires_in_minutes: expiresInMinutes.value,
        });

        emit('updated', res.data.user);
        emit('close');
    } catch (err) {
        console.error('Failed to update status:', err);
    } finally {
        isSubmitting.value = false;
    }
};

const handleClear = async () => {
    statusText.value = '';
    statusEmoji.value = '';
    expiresInMinutes.value = 0;
    await handleSave();
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="md">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">
                Set a status
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                Let your team and contacts know what you are currently up to.
            </p>

            <!-- Custom Input -->
            <div class="flex gap-2 mb-4">
                <input
                    type="text"
                    v-model="statusEmoji"
                    class="w-12 text-center text-lg rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800"
                    placeholder="💬"
                    maxlength="4"
                />
                <input
                    type="text"
                    v-model="statusText"
                    placeholder="What's your status?"
                    class="flex-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-4 py-2 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                />
            </div>

            <!-- Presets -->
            <div class="space-y-1.5 mb-6">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                    Presets
                </span>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="p in presets"
                        :key="p.text"
                        type="button"
                        @click="selectPreset(p)"
                        class="flex items-center gap-2 p-2 rounded-xl text-left text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-100 dark:border-slate-800 transition-colors"
                    >
                        <span>{{ p.emoji }}</span>
                        <span class="truncate">{{ p.text }}</span>
                    </button>
                </div>
            </div>

            <!-- Expiration selector -->
            <div class="mb-6">
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                    Clear status after
                </label>
                <select
                    v-model="expiresInMinutes"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm text-slate-800 dark:text-slate-200"
                >
                    <option :value="0">Don't clear</option>
                    <option :value="30">30 minutes</option>
                    <option :value="60">1 hour</option>
                    <option :value="240">4 hours</option>
                    <option :value="1440">Today</option>
                    <option :value="10080">This week</option>
                </select>
            </div>

            <!-- Footer Actions -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
                <button
                    v-if="statusText"
                    type="button"
                    @click="handleClear"
                    class="text-xs font-medium text-rose-500 hover:text-rose-600"
                >
                    Clear Status
                </button>
                <div v-else></div>

                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-xl px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="handleSave"
                        :disabled="isSubmitting"
                        class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 disabled:opacity-50 transition-all active:scale-95"
                    >
                        {{ isSubmitting ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
