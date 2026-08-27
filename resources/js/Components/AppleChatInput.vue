<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';
import { renderAppleEmojisHtml, getAppleEmojiUrl } from '@/Utils/emoji';

// YB - 26-08-2026 - Apple iOS Rich Chat Input Component
const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Type your message... (Press Enter to send)',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    maxHeight: {
        type: String,
        default: '9rem',
    },
});

const emit = defineEmits(['update:modelValue', 'send', 'typing']);

const inputRef = ref(null);
let isInternalUpdate = false;

// Extract raw text with Unicode emojis from contenteditable DOM
const extractRawText = () => {
    if (!inputRef.value) return '';
    
    let text = '';
    const walk = (node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            text += node.textContent;
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            if (node.tagName === 'IMG' && node.dataset.emoji) {
                text += node.dataset.emoji;
            } else if (node.tagName === 'BR') {
                text += '\n';
            } else if (node.tagName === 'DIV' || node.tagName === 'P') {
                if (text.length > 0 && !text.endsWith('\n')) {
                    text += '\n';
                }
                for (const child of node.childNodes) {
                    walk(child);
                }
            } else {
                for (const child of node.childNodes) {
                    walk(child);
                }
            }
        }
    };

    for (const child of inputRef.value.childNodes) {
        walk(child);
    }

    return text;
};

// Handle user typing or pasting
const handleInput = () => {
    isInternalUpdate = true;
    const raw = extractRawText();
    emit('update:modelValue', raw);
    emit('typing');
    nextTick(() => {
        isInternalUpdate = false;
    });
};

const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        emit('send');
    }
};

const handlePaste = (e) => {
    e.preventDefault();
    const pastedText = e.clipboardData.getData('text/plain');
    if (!pastedText) return;

    // Convert pasted emojis to Apple 3D emoji HTML
    const htmlWithAppleEmojis = renderAppleEmojisHtml(pastedText);
    document.execCommand('insertHTML', false, htmlWithAppleEmojis);
    handleInput();
};

// Insert an Apple emoji at current cursor position
const insertEmoji = (emojiNative) => {
    if (!inputRef.value) return;
    inputRef.value.focus();

    const url = getAppleEmojiUrl(emojiNative);
    const imgHtml = url 
        ? `<img src="${url}" class="apple-emoji-inline" data-emoji="${emojiNative}" alt="${emojiNative}" draggable="false" />`
        : emojiNative;

    document.execCommand('insertHTML', false, imgHtml);
    handleInput();
};

const clear = () => {
    if (inputRef.value) {
        inputRef.value.innerHTML = '';
        emit('update:modelValue', '');
    }
};

const focus = () => {
    inputRef.value?.focus();
};

// Sync when modelValue is changed externally (e.g. cleared on send)
watch(() => props.modelValue, (newVal) => {
    if (isInternalUpdate) return;
    if (!inputRef.value) return;

    if (!newVal) {
        inputRef.value.innerHTML = '';
    } else {
        const currentRaw = extractRawText();
        if (currentRaw !== newVal) {
            inputRef.value.innerHTML = renderAppleEmojisHtml(newVal);
        }
    }
});

defineExpose({
    insertEmoji,
    clear,
    focus,
});
</script>

<template>
    <div
        ref="inputRef"
        contenteditable="true"
        role="textbox"
        spellcheck="true"
        :aria-disabled="disabled"
        @input="handleInput"
        @keydown="handleKeyDown"
        @paste="handlePaste"
        :style="{ maxHeight: maxHeight }"
        class="w-full glass-input text-slate-800 text-sm rounded-2xl px-4 py-3 border border-slate-200 focus:outline-none resize-none transition overflow-y-auto custom-scrollbar shadow-xs select-text focus:border-brand-300 focus:ring-2 focus:ring-brand-500/20"
        :data-placeholder="placeholder"
    ></div>
</template>

<style scoped>
[contenteditable="true"]:empty:before {
    content: attr(data-placeholder);
    color: #94a3b8;
    pointer-events: none;
    display: block;
}
</style>
