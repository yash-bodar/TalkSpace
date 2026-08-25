<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-slate-700 shadow-xs transition duration-150 ease-in-out hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-25 active:scale-95"
    >
        <slot />
    </button>
</template>
