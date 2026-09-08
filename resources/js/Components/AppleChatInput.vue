<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';
import { renderAppleEmojisHtml, getAppleEmojiUrl } from '@/Utils/emoji';

// YB - 08-09-2026 - Apple iOS Rich Chat Input Component with safe selection preservation
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
let savedRange = null;

// Save current selection range inside the editor
const saveSelection = () => {
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0 && inputRef.value && inputRef.value.contains(sel.anchorNode)) {
        savedRange = sel.getRangeAt(0).cloneRange();
    }
};

// Restore selection range or focus to the end
const restoreSelection = () => {
    if (!inputRef.value) return;
    inputRef.value.focus();
    const sel = window.getSelection();
    if (!sel) return;

    if (savedRange && inputRef.value.contains(savedRange.commonAncestorContainer)) {
        sel.removeAllRanges();
        sel.addRange(savedRange);
    } else {
        const range = document.createRange();
        range.selectNodeContents(inputRef.value);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
        savedRange = range.cloneRange();
    }
};

// Extract raw text with Unicode emojis from contenteditable DOM
const extractRawText = () => {
    if (!inputRef.value) return '';
    
    let text = '';
    const walk = (node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            text += node.textContent;
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            if (node.tagName === 'IMG') {
                text += node.dataset.emoji || node.getAttribute('alt') || '';
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
    saveSelection();
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

// Insert an Apple emoji at current cursor position without replacing existing emojis
const insertEmoji = (emojiNative) => {
    if (!inputRef.value || !emojiNative) return;
    restoreSelection();

    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;

    const range = sel.getRangeAt(0);
    range.deleteContents();

    const url = getAppleEmojiUrl(emojiNative);
    let nodeToInsert;
    if (url) {
        const img = document.createElement('img');
        img.src = url;
        img.className = 'apple-emoji-inline';
        img.dataset.emoji = emojiNative;
        img.alt = emojiNative;
        img.draggable = false;
        img.loading = 'lazy';
        nodeToInsert = img;
    } else {
        nodeToInsert = document.createTextNode(emojiNative);
    }

    range.insertNode(nodeToInsert);

    // Position caret immediately after the newly inserted emoji
    const newRange = document.createRange();
    newRange.setStartAfter(nodeToInsert);
    newRange.setEndAfter(nodeToInsert);
    sel.removeAllRanges();
    sel.addRange(newRange);
    savedRange = newRange.cloneRange();

    handleInput();
};

const clear = () => {
    if (inputRef.value) {
        inputRef.value.innerHTML = '';
        savedRange = null;
        emit('update:modelValue', '');
    }
};

const focus = () => {
    if (!inputRef.value) return;
    restoreSelection();
};

// Sync when modelValue is changed externally (e.g. cleared on send or edited)
watch(() => props.modelValue, (newVal) => {
    if (isInternalUpdate) return;
    if (!inputRef.value) return;

    if (!newVal) {
        inputRef.value.innerHTML = '';
        savedRange = null;
    } else {
        const currentRaw = extractRawText();
        if (currentRaw !== newVal) {
            inputRef.value.innerHTML = renderAppleEmojisHtml(newVal);
            saveSelection();
        }
    }
});

// YB - 08-09-2026 - Initialize input content on mount (especially for message edit mode)
onMounted(() => {
    if (props.modelValue && inputRef.value) {
        inputRef.value.innerHTML = renderAppleEmojisHtml(props.modelValue);
        saveSelection();
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
        @keyup="saveSelection"
        @mouseup="saveSelection"
        @touchend="saveSelection"
        @focus="saveSelection"
        @blur="saveSelection"
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
