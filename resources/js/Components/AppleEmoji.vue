<script setup>
import { computed, ref } from 'vue';
import { getAppleEmojiUrl } from '@/Utils/emoji';

// YB - 26-08-2026 - Apple iOS Emoji Renderer
const props = defineProps({
    emoji: {
        type: String,
        required: true,
    },
    size: {
        type: String,
        default: '1.25rem',
    },
    className: {
        type: String,
        default: '',
    },
});

const hasError = ref(false);
const emojiUrl = computed(() => getAppleEmojiUrl(props.emoji));
</script>

<template>
    <img 
        v-if="emojiUrl && !hasError" 
        :src="emojiUrl" 
        :alt="emoji"
        :style="{ width: size, height: size, minWidth: size, minHeight: size }"
        class="inline-block object-contain align-middle select-none pointer-events-none transition-transform"
        :class="className"
        loading="lazy"
        draggable="false"
        @error="hasError = true"
    />
    <span v-else class="inline-block align-middle select-none" :style="{ fontSize: size }">
        {{ emoji }}
    </span>
</template>
