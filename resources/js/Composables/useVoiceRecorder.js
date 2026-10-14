import { ref, onMounted, onUnmounted } from 'vue';

/**
 * Composable for audio recording and real-time waveform calculation.
 *
 * // YB - 14-09-2026 code comment
 */
export function useVoiceRecorder() {
    const isRecording = ref(false);
    const recordingDuration = ref(0);
    const audioBlob = ref(null);
    const audioUrl = ref(null);
    const waveformData = ref([]);
    const isSupported = ref(typeof window !== 'undefined' && !!navigator.mediaDevices?.getUserMedia);

    let mediaRecorder = null;
    let audioContext = null;
    let analyser = null;
    let sourceNode = null;
    let animationFrameId = null;
    let durationTimer = null;
    let audioChunks = [];
    let stream = null;

    const startRecording = async () => {
        if (isRecording.value) return;

        try {
            audioChunks = [];
            waveformData.value = [];
            recordingDuration.value = 0;
            audioBlob.value = null;
            if (audioUrl.value) {
                URL.revokeObjectURL(audioUrl.value);
                audioUrl.value = null;
            }

            stream = await navigator.mediaDevices.getUserMedia({ audio: true });

            // Initialize Web Audio API for live waveform analysis
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioContext = new AudioContextClass();
                analyser = audioContext.createAnalyser();
                analyser.fftSize = 64;
                sourceNode = audioContext.createMediaStreamSource(stream);
                sourceNode.connect(analyser);

                const dataArray = new Uint8Array(analyser.frequencyBinCount);
                const sampleWaveform = () => {
                    if (!isRecording.value) return;

                    analyser.getByteFrequencyData(dataArray);
                    // Compute average volume for this sample normalized 5-100
                    let sum = 0;
                    for (let i = 0; i < dataArray.length; i++) {
                        sum += dataArray[i];
                    }
                    const avg = sum / dataArray.length;
                    const normalized = Math.max(8, Math.min(100, Math.round((avg / 255) * 100)));

                    // Keep up to 40 samples for visual density
                    waveformData.value.push(normalized);
                    if (waveformData.value.length > 50) {
                        waveformData.value.shift();
                    }

                    setTimeout(() => {
                        if (isRecording.value) {
                            sampleWaveform();
                        }
                    }, 80);
                };
                sampleWaveform();
            }

            // Pick supported mimeType
            let mimeType = 'audio/webm;codecs=opus';
            if (!MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                if (MediaRecorder.isTypeSupported('audio/mp4')) {
                    mimeType = 'audio/mp4';
                } else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
                    mimeType = 'audio/ogg;codecs=opus';
                } else {
                    mimeType = '';
                }
            }

            const options = mimeType ? { mimeType } : {};
            mediaRecorder = new MediaRecorder(stream, options);

            mediaRecorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) {
                    audioChunks.push(e.data);
                }
            };

            mediaRecorder.onstop = () => {
                const recordedMime = mediaRecorder.mimeType || 'audio/webm';
                audioBlob.value = new Blob(audioChunks, { type: recordedMime });
                audioUrl.value = URL.createObjectURL(audioBlob.value);
            };

            mediaRecorder.start(100);
            isRecording.value = true;

            durationTimer = setInterval(() => {
                recordingDuration.value++;
            }, 1000);

        } catch (err) {
            console.error('Failed to access microphone for voice note:', err);
            isRecording.value = false;
            throw err;
        }
    };

    const stopRecording = () => {
        return new Promise((resolve) => {
            if (!mediaRecorder || !isRecording.value) {
                resolve(null);
                return;
            }

            clearInterval(durationTimer);
            isRecording.value = false;

            if (audioContext && audioContext.state !== 'closed') {
                audioContext.close().catch(() => {});
            }

            mediaRecorder.onstop = () => {
                const recordedMime = mediaRecorder.mimeType || 'audio/webm';
                audioBlob.value = new Blob(audioChunks, { type: recordedMime });
                audioUrl.value = URL.createObjectURL(audioBlob.value);

                // Stop media tracks
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }

                // Ensure at least 30 waveform points
                let finalWaveform = [...waveformData.value];
                if (finalWaveform.length < 20) {
                    while (finalWaveform.length < 25) {
                        finalWaveform.push(Math.floor(Math.random() * 30) + 15);
                    }
                }

                resolve({
                    blob: audioBlob.value,
                    url: audioUrl.value,
                    duration: recordingDuration.value,
                    waveform: finalWaveform,
                    mimeType: recordedMime,
                });
            };

            mediaRecorder.stop();
        });
    };

    const cancelRecording = () => {
        clearInterval(durationTimer);
        isRecording.value = false;
        recordingDuration.value = 0;
        waveformData.value = [];

        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }

        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }

        if (audioContext && audioContext.state !== 'closed') {
            audioContext.close().catch(() => {});
        }

        if (audioUrl.value) {
            URL.revokeObjectURL(audioUrl.value);
            audioUrl.value = null;
        }
        audioBlob.value = null;
    };

    onUnmounted(() => {
        cancelRecording();
    });

    return {
        isRecording,
        recordingDuration,
        audioBlob,
        audioUrl,
        waveformData,
        isSupported,
        startRecording,
        stopRecording,
        cancelRecording,
    };
}
