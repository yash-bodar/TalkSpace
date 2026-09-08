// Robust Unicode Emoji Regex capturing all skin tones, hair variants, variation selectors, ZWJ sequences, and flags - YB - 08-09-2026
const EMOJI_REGEX = /(?:\p{Regional_Indicator}{2}|[#*0-9]\uFE0F?\u20E3|\p{Extended_Pictographic}(?:\uFE0F|\uFE0E|[\u{1F3FB}-\u{1F3FF}]|[\u{1F9B0}-\u{1F9B3}]|[\u{E0020}-\u{E007F}])*(?:\u200D\p{Extended_Pictographic}(?:\uFE0F|\uFE0E|[\u{1F3FB}-\u{1F3FF}]|[\u{1F9B0}-\u{1F9B3}]|[\u{E0020}-\u{E007F}])*)*)/gu;

/**
 * Convert any emoji character to hex code matching Apple's datasource
 * 
 * // YB - 08-09-2026 code comment
 */
export const getEmojiUnified = (emoji) => {
    if (!emoji) return '';
    const clean = emoji.replace(/[\uFE0E]/g, '');
    const codePoints = [];
    for (let i = 0; i < clean.length; i++) {
        const codePoint = clean.codePointAt(i);
        if (codePoint !== undefined) {
            const hex = codePoint.toString(16).toLowerCase();
            codePoints.push(hex.length < 4 ? hex.padStart(4, '0') : hex);
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
 * // YB - 08-09-2026 code comment
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
        if (!match) return '';
        const url = getAppleEmojiUrl(match);
        if (!url) return match;
        return `<img src="${url}" class="apple-emoji-inline" data-emoji="${match}" alt="${match}" draggable="false" loading="lazy" />`;
    });
};
