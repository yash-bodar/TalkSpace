<script setup>
/**
 * Mentions Autocomplete Popover for @mentions in chat input.
 *
 * // YB - 14-09-2026 code comment
 */
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    users: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['select']);
</script>

<template>
    <transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 translate-y-2 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 translate-y-2 scale-95"
    >
        <div
            v-if="show && users.length > 0"
            class="absolute bottom-full mb-2 left-4 w-64 max-h-48 overflow-y-auto rounded-2xl bg-white dark:bg-slate-900 shadow-xl border border-slate-200 dark:border-slate-800 p-1.5 z-40"
        >
            <div class="px-2 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                Mention Member
            </div>
            <div
                v-for="user in users"
                :key="user.id"
                @mousedown.prevent="emit('select', user)"
                class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer transition-colors"
            >
                <img
                    :src="user.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.name)"
                    class="h-6 w-6 rounded-full object-cover"
                    alt="Avatar"
                />
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">
                        {{ user.name }}
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>
