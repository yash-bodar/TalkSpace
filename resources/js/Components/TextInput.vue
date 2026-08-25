<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-xl border-slate-300 shadow-xs focus:border-brand-500 focus:ring-brand-500 text-slate-800 bg-white placeholder-slate-400"
        v-model="model"
        ref="input"
    />
</template>
