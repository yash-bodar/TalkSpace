<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

/**
 * Global & In-Chat Historical Message Search Drawer.
 * Fixed slide-over drawer anchored to the right edge with backdrop overlay.
 *
 * // YB - 14-09-2026 code comment
 */
const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    activeConversationId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['close', 'selectMessage']);

const searchInputRef = ref(null);
const searchQuery = ref('');
const searchScope = ref('active'); // 'active' or 'all'
const searchResults = ref([]);
const isSearching = ref(false);
let debounceTimeout = null;

const performSearch = async () => {
    if (!searchQuery.value || searchQuery.value.trim().length < 1) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const params = { query: searchQuery.value.trim() };
        if (searchScope.value === 'active' && props.activeConversationId) {
            params.conversation_id = props.activeConversationId;
        }

        const res = await axios.get(route('chat.messages.search'), { params });
        searchResults.value = res.data.messages || [];
    } catch (err) {
        console.error('Failed to search messages:', err);
    } finally {
        isSearching.value = false;
    }
};

watch(searchQuery, () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(performSearch, 300);
});

watch(searchScope, () => {
    performSearch();
});

watch(() => props.isOpen, (open) => {
    if (open) {
        if (!props.activeConversationId) {
            searchScope.value = 'all';
        }
        nextTick(() => {
            searchInputRef.value?.focus();
        });
        if (searchQuery.value.trim()) {
            performSearch();
        }
    } else {
        searchQuery.value = '';
        searchResults.value = [];
    }
});

const handleKeydown = (e) => {
    if (e.key === 'Escape' && props.isOpen) {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="isOpen" class="fixed inset-0 z-50 overflow-hidden">
            <!-- Backdrop -->
            <transition
                enter-active-class="transition-opacity duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
                appear
            >
                <div 
                    class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity" 
                    @click="emit('close')"
                ></div>
            </transition>

            <!-- Slide-over panel -->
            <div class="fixed inset-y-0 right-0 max-w-full flex pl-10 pointer-events-none">
                <transition
                    enter-active-class="transform transition ease-in-out duration-300 sm:duration-400"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transform transition ease-in-out duration-300 sm:duration-400"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                    appear
                >
                    <div 
                        class="w-screen max-w-md pointer-events-auto bg-white border-l border-slate-200 shadow-2xl flex flex-col h-full z-10 animate-in slide-in-from-right duration-300"
                    >
                        <!-- Header -->
                        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50/70">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center border border-brand-200/60 shadow-xs">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8" />
                                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 text-sm">
                                        Search Messages
                                    </h3>
                                    <p class="text-[11px] text-slate-400">Search messages across your history</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="emit('close')"
                                class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Search Inputs & Scope Filter -->
                        <div class="p-4 border-b border-slate-100 space-y-3 bg-white">
                            <div class="relative">
                                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <input
                                    ref="searchInputRef"
                                    type="text"
                                    v-model="searchQuery"
                                    placeholder="Type keyword or phrase..."
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:bg-white transition"
                                />
                                <button
                                    v-if="searchQuery"
                                    @click="searchQuery = ''; performSearch()"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- Scope pills -->
                            <div class="flex rounded-xl bg-slate-100 p-1 text-xs">
                                <button
                                    type="button"
                                    @click="searchScope = 'active'"
                                    :disabled="!activeConversationId"
                                    class="flex-1 rounded-lg py-1.5 font-bold transition-all text-xs"
                                    :class="searchScope === 'active'
                                        ? 'bg-white text-slate-900 shadow-xs'
                                        : 'text-slate-500 hover:text-slate-700 disabled:opacity-40'"
                                >
                                    This Chat
                                </button>
                                <button
                                    type="button"
                                    @click="searchScope = 'all'"
                                    class="flex-1 rounded-lg py-1.5 font-bold transition-all text-xs"
                                    :class="searchScope === 'all'
                                        ? 'bg-white text-slate-900 shadow-xs'
                                        : 'text-slate-500 hover:text-slate-700'"
                                >
                                    All Chats
                                </button>
                            </div>
                        </div>

                        <!-- Results List -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-2.5 bg-slate-50/50">
                            <div v-if="isSearching" class="py-12 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
                                <div class="w-6 h-6 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></div>
                                <span>Searching historical messages...</span>
                            </div>

                            <div v-else-if="searchQuery && searchResults.length === 0" class="py-16 text-center text-xs text-slate-400">
                                <p class="font-semibold text-slate-600 mb-1">No results found</p>
                                <p>Try searching for a different keyword or switch to "All Chats".</p>
                            </div>

                            <div v-else-if="!searchQuery" class="py-16 text-center text-xs text-slate-400">
                                <p class="font-semibold text-slate-600 mb-1">Quick Search</p>
                                <p>Search messages from any time in your conversations.</p>
                            </div>

                            <div
                                v-for="msg in searchResults"
                                :key="msg.id"
                                @click="emit('selectMessage', msg)"
                                class="p-3.5 rounded-2xl border border-slate-200/80 bg-white hover:border-brand-300 hover:shadow-md hover:bg-brand-50/30 cursor-pointer transition-all duration-150 group"
                            >
                                <div class="flex items-center justify-between text-[11px] text-slate-400 mb-1.5">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="font-bold text-slate-700 group-hover:text-brand-600 transition truncate">
                                            {{ msg.sender?.name || 'User' }}
                                        </span>
                                        <span v-if="msg.conversation?.title" class="text-slate-400 font-medium truncate text-[10px] bg-slate-100 px-1.5 py-0.5 rounded-md">
                                            {{ msg.conversation.title }}
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-slate-400 flex-shrink-0">{{ msg.created_at_human }}</span>
                                </div>
                                <p class="text-xs text-slate-700 line-clamp-2 leading-relaxed">
                                    {{ msg.body }}
                                </p>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </Teleport>
</template>
