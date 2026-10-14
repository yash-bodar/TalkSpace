<script setup>
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';

/**
 * Forward Message Modal for selecting one or more target chats.
 *
 * // YB - 14-09-2026 code comment
 */
const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    messageToForward: {
        type: Object,
        default: null,
    },
    conversations: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'forwarded']);

const searchQuery = ref('');
const selectedIds = ref([]);
const isSubmitting = ref(false);

const filteredConversations = computed(() => {
    if (!searchQuery.value) return props.conversations;
    const q = searchQuery.value.toLowerCase();
    return props.conversations.filter(c => {
        const title = c.title || c.direct_recipient?.name || '';
        return title.toLowerCase().includes(q);
    });
});

const toggleSelection = (convId) => {
    if (selectedIds.value.includes(convId)) {
        selectedIds.value = selectedIds.value.filter(id => id !== convId);
    } else {
        selectedIds.value.push(convId);
    }
};

const handleForward = async () => {
    if (!props.messageToForward || selectedIds.value.length === 0 || isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await axios.post(route('chat.messages.forward', props.messageToForward.id), {
            conversation_ids: selectedIds.value,
        });

        emit('forwarded', res.data);
        selectedIds.value = [];
        searchQuery.value = '';
        emit('close');
    } catch (err) {
        console.error('Failed to forward message:', err);
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="md">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">
                Forward Message
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                Select one or more chats to forward this message to.
            </p>

            <!-- Search Filter -->
            <div class="relative mb-4">
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Search chats..."
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 px-4 py-2 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                />
            </div>

            <!-- Conversations List -->
            <div class="max-h-60 overflow-y-auto space-y-1.5 pr-1 divide-y divide-slate-100 dark:divide-slate-800">
                <div
                    v-for="conv in filteredConversations"
                    :key="conv.id"
                    @click="toggleSelection(conv.id)"
                    class="flex items-center justify-between p-2.5 rounded-xl cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors"
                    :class="{
                        'bg-brand-50/60 dark:bg-brand-950/30': selectedIds.includes(conv.id)
                    }"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <img
                            :src="conv.avatar_url || conv.direct_recipient?.avatar_url || 'https://ui-avatars.com/api/?name=Chat'"
                            class="h-9 w-9 rounded-full object-cover flex-shrink-0"
                            alt="Avatar"
                        />
                        <div class="min-w-0">
                            <h4 class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">
                                {{ conv.title || conv.direct_recipient?.name || 'Chat' }}
                            </h4>
                            <span class="text-[11px] text-slate-400 capitalize">
                                {{ conv.type === 'direct' ? 'Direct message' : 'Group chat' }}
                            </span>
                        </div>
                    </div>

                    <!-- Checkbox pill -->
                    <div
                        class="h-5 w-5 rounded-md border flex items-center justify-center transition-colors"
                        :class="selectedIds.includes(conv.id)
                            ? 'bg-brand-600 border-brand-600 text-white'
                            : 'border-slate-300 dark:border-slate-600'"
                    >
                        <svg v-if="selectedIds.includes(conv.id)" class="h-3.5 w-3.5 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Modal Footer Actions -->
            <div class="mt-6 flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
                <span class="text-xs text-slate-500">
                    {{ selectedIds.length }} selected
                </span>
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
                        @click="handleForward"
                        :disabled="selectedIds.length === 0 || isSubmitting"
                        class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 disabled:opacity-50 transition-all active:scale-95"
                    >
                        {{ isSubmitting ? 'Forwarding...' : 'Forward' }}
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
