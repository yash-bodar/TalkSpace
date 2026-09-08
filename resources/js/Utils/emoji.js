import appleEmojiData from '@emoji-mart/data/sets/15/apple.json';

// Build instant lookup map for all 6,700+ Apple emoji variants & skin tones - YB - 26-08-2026
const EMOJI_TO_UNIFIED = new Map();

for (const k in appleEmojiData.emojis) {
    const entry = appleEmojiData.emojis[k];
    if (entry && entry.skins) {
        for (const s of entry.skins) {
            if (s.native && s.unified) {
                EMOJI_TO_UNIFIED.set(s.native, s.unified);
                if (s.native.endsWith('\ufe0f')) {
                    EMOJI_TO_UNIFIED.set(s.native.slice(0, -1), s.unified);
                } else {
                    EMOJI_TO_UNIFIED.set(s.native + '\ufe0f', s.unified);
                }
            }
        }
    }
}

// Robust Unicode Emoji Regex capturing all skin tones, hair variants, variation selectors, ZWJ sequences, and flags - YB - 26-08-2026
const EMOJI_REGEX = /(?:\p{Regional_Indicator}{2}|[#*0-9]\uFE0F?\u20E3|\p{Extended_Pictographic}(?:\uFE0F|\uFE0E|[\u{1F3FB}-\u{1F3FF}]|[\u{1F9B0}-\u{1F9B3}]|[\u{E0020}-\u{E007F}])*(?:\u200D\p{Extended_Pictographic}(?:\uFE0F|\uFE0E|[\u{1F3FB}-\u{1F3FF}]|[\u{1F9B0}-\u{1F9B3}]|[\u{E0020}-\u{E007F}])*)*)/gu;

/**
 * Convert any emoji character to hex code matching Apple's datasource
 */
export const getEmojiUnified = (emoji) => {
    if (!emoji) return '';
    if (EMOJI_TO_UNIFIED.has(emoji)) return EMOJI_TO_UNIFIED.get(emoji);

    const clean = emoji.replace(/[\uFE0E\uFE0F]/g, '');
    if (EMOJI_TO_UNIFIED.has(clean)) return EMOJI_TO_UNIFIED.get(clean);

    const codePoints = [];
    for (let i = 0; i < emoji.length; i++) {
        const codePoint = emoji.codePointAt(i);
        if (codePoint !== undefined) {
            // Skip variation selector 16 when building fallback
            if (codePoint !== 0xfe0f && codePoint !== 0xfe0e) {
                codePoints.push(codePoint.toString(16).toLowerCase());
            }
            if (codePoint > 0xffff) {
                i++;
            }
        }
    }
    return codePoints.join('-');
};

/**
 * Return the Apple iOS 3D emoji CDN image URL
 * 
 * // YB - 26-08-2026 code comment
 */
export const getAppleEmojiUrl = (emoji) => {
    const unified = getEmojiUnified(emoji);
    if (!unified) return '';
    return `https://cdn.jsdelivr.net/npm/emoji-datasource-apple@15.0.1/img/apple/64/${unified}.png`;
};

/**
 * Escape HTML to ensure safety before rendering formatted rich text.
 */
function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/**
 * Check if the text contains ONLY 1 to 3 emojis and no alphanumeric text
 */
export const isOnlyEmojis = (text) => {
    if (!text) return false;
    const stripped = text.trim();
    if (!stripped) return false;
    const matches = stripped.match(EMOJI_REGEX);
    if (!matches) return false;
    const nonEmoji = stripped.replace(EMOJI_REGEX, '').replace(/[\uFE0E\uFE0F\u200D\s]/g, '');
    return nonEmoji.length === 0 && matches.length <= 3;
};

/**
 * Parses message text and converts all inline Unicode emojis to Apple iOS 3D images
 * 
 * // YB - 26-08-2026 code comment
 */
export const renderAppleEmojisHtml = (text) => {
    if (!text) return '';
    
    // First safely escape HTML
    const safeText = escapeHtml(text);

    // Replace all full emoji sequences with Apple <img> tags without leaving trailing variation selectors
    return safeText.replace(EMOJI_REGEX, (match) => {
        const url = getAppleEmojiUrl(match);
        if (!url) return match;
        return `<img src="${url}" class="apple-emoji-inline" alt="${match}" draggable="false" loading="lazy" />`;
    });
};
