<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import appleEmojiData from '@emoji-mart/data/sets/15/apple.json';
import { Picker } from 'emoji-mart';

// YB - 26-08-2026 - Apple iOS Themed Full Emoji Picker
const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Pick an emoji',
    },
});

const emit = defineEmits(['select', 'close']);

const pickerContainer = ref(null);
let pickerInstance = null;

const initPicker = () => {
    if (!pickerContainer.value || pickerInstance) return;

    pickerInstance = new Picker({
        parent: pickerContainer.value,
        data: appleEmojiData,
        set: 'apple',
        theme: 'light',
        previewPosition: 'none',
        skinTonePosition: 'preview',
        navPosition: 'top',
        maxFrequentRows: 1,
        autoFocus: false,
        onEmojiSelect: (emoji) => {
            emit('select', emoji.native || emoji.shortcodes);
        },
    });
};

watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        setTimeout(() => {
            initPicker();
        }, 50);
    }
});

onMounted(() => {
    if (props.isOpen) {
        initPicker();
    }
});

onUnmounted(() => {
    if (pickerInstance && pickerInstance.remove) {
        try {
            pickerInstance.remove();
        } catch (e) {}
    }
});
</script>

<template>
    <div v-if="isOpen" class="relative z-50 animate-in zoom-in-95 duration-150" @click.stop>
        <div ref="pickerContainer" class="shadow-2xl rounded-3xl overflow-hidden border border-slate-200/90 bg-white"></div>
    </div>
</template>

<style>
em-emoji-picker {
    --border-radius: 1.25rem;
    --font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "SF Pro", "Helvetica Neue", Helvetica, Arial, sans-serif;
    --rgb-accent: 217, 26, 141;
    --color-border: rgba(226, 232, 240, 0.9);
    height: 380px;
    max-height: 380px;
    width: 330px;
    max-width: 90vw;
}
</style>
