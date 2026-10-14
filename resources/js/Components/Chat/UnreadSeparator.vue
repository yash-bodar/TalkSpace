<script setup>
/**
 * Unread Messages Separator line inside the chat timeline.
 *
 * // YB - 14-09-2026 code comment
 */
defineProps({
    count: {
        type: Number,
        default: 1,
    },
});
</script>

<template>
    <div class="flex items-center my-4 select-none">
        <div class="flex-grow border-t border-brand-300/60 dark:border-brand-800/60"></div>
        <span class="mx-3 rounded-full bg-brand-50 dark:bg-brand-950/70 border border-brand-200 dark:border-brand-800 px-3 py-0.5 text-[10px] font-bold uppercase tracking-wider text-brand-600 dark:text-brand-400 shadow-sm">
            {{ count }} Unread {{ count === 1 ? 'Message' : 'Messages' }}
        </span>
        <div class="flex-grow border-t border-brand-300/60 dark:border-brand-800/60"></div>
    </div>
</template>
