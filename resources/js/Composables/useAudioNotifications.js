import { ref } from 'vue';

/**
 * Composable for audio chime notifications and Web Push permissions.
 *
 * // YB - 14-09-2026 code comment
 */
export function useAudioNotifications() {
    const isSoundEnabled = ref(localStorage.getItem('talkspace_sound_enabled') !== 'false');
    const isPushSupported = ref(typeof window !== 'undefined' && 'Notification' in window && 'serviceWorker' in navigator);
    const pushPermission = ref(typeof window !== 'undefined' && 'Notification' in window ? Notification.permission : 'default');

    // Create synthesized audio chimes with Web Audio API (Zero external assets required)
    const playMessageChime = (type = 'incoming') => {
        if (!isSoundEnabled.value) return;

        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) return;

            const ctx = new AudioContextClass();
            const now = ctx.currentTime;

            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.connect(gain);
            gain.connect(ctx.destination);

            if (type === 'incoming') {
                // High-pitched pleasant bell chime (E6 -> A6)
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1318.51, now);
                osc.frequency.exponentialRampToValueAtTime(1760.00, now + 0.08);

                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);

                osc.start(now);
                osc.stop(now + 0.35);
            } else if (type === 'outgoing') {
                // Subtle pop
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(880, now);
                osc.frequency.exponentialRampToValueAtTime(440, now + 0.06);

                gain.gain.setValueAtTime(0.12, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.1);

                osc.start(now);
                osc.stop(now + 0.1);
            }
        } catch (e) {
            // AudioContext autoplay restrictions handled silently
        }
    };

    const toggleSound = () => {
        isSoundEnabled.value = !isSoundEnabled.value;
        localStorage.setItem('talkspace_sound_enabled', isSoundEnabled.value ? 'true' : 'false');
    };

    const requestPushPermission = async () => {
        if (!isPushSupported.value) return 'unsupported';

        try {
            const perm = await Notification.requestPermission();
            pushPermission.value = perm;
            return perm;
        } catch (err) {
            console.error('Failed to request push permission:', err);
            return 'denied';
        }
    };

    const triggerNotification = (title, options = {}) => {
        if (!isPushSupported.value || pushPermission.value !== 'granted') {
            return;
        }

        try {
            const notif = new Notification(title, {
                icon: '/favicon.ico',
                badge: '/favicon.ico',
                ...options,
            });

            notif.onclick = () => {
                window.focus();
                notif.close();
            };
        } catch (e) {
            console.warn('Notification trigger error:', e);
        }
    };

    return {
        isSoundEnabled,
        isPushSupported,
        pushPermission,
        playMessageChime,
        toggleSound,
        requestPushPermission,
        triggerNotification,
    };
}
