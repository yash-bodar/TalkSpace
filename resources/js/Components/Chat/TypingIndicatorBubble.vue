<script setup>
/**
 * Animated 3-dot typing indicator bubble matching timeline chat aesthetics.
 *
 * // YB - 14-09-2026 code comment
 */
defineProps({
    userName: {
        type: String,
        default: 'Someone',
    },
});
</script>

<template>
    <div class="flex items-center gap-2 py-1.5 px-3 max-w-[140px] rounded-2xl rounded-tl-sm bg-slate-100 dark:bg-slate-800 text-slate-500 shadow-sm border border-slate-200/60 dark:border-slate-700/60">
        <span class="text-[11px] font-medium truncate max-w-[70px]">{{ userName }}</span>
        <div class="flex items-center gap-1">
            <span class="h-1.5 w-1.5 rounded-full bg-slate-400 dark:bg-slate-500 animate-bounce [animation-delay:-0.3s]"></span>
            <span class="h-1.5 w-1.5 rounded-full bg-slate-400 dark:bg-slate-500 animate-bounce [animation-delay:-0.15s]"></span>
            <span class="h-1.5 w-1.5 rounded-full bg-slate-400 dark:bg-slate-500 animate-bounce"></span>
        </div>
    </div>
</template>
