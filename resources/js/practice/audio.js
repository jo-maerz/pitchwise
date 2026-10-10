import { PitchDetector } from 'pitchy';

/** Frequencies outside this range are ignored (low C on a cello is 65 Hz; violin tops out near 3.5 kHz). */
export const MIN_HZ = 55;
export const MAX_HZ = 4200;

/**
 * Opens the microphone with browser "voice" processing switched off (it bends pitch),
 * and returns a reader that yields one pitch frame per call.
 */
export async function openMicrophone({ noiseGateDb = -30 } = {}) {
    if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('This browser cannot use the microphone here. Use a current Chrome, Firefox or Safari over https or localhost.');
    }
    const stream = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: false, noiseSuppression: false, autoGainControl: false },
    });
    const ctx = new AudioContext({ latencyHint: 'interactive' });
    if (ctx.state === 'suspended') await ctx.resume();

    const source = ctx.createMediaStreamSource(stream);
    const analyser = ctx.createAnalyser();
    analyser.fftSize = 2048; // ~43 ms at 48 kHz: 8 periods of the violin's open G
    source.connect(analyser);

    const detector = PitchDetector.forFloat32Array(analyser.fftSize);
    detector.minVolumeDecibels = noiseGateDb;
    const buffer = new Float32Array(detector.inputLength);

    return {
        ctx,
        /** Current time on the audio clock, in ms. Frames and the metronome share this clock. */
        nowMs: () => ctx.currentTime * 1000,
        read() {
            analyser.getFloatTimeDomainData(buffer);
            let [hz, clarity] = detector.findPitch(buffer, ctx.sampleRate);
            if (!(hz >= MIN_HZ && hz <= MAX_HZ)) {
                hz = 0;
                clarity = 0;
            }
            // Loudness of this window in dB (same scale as the noise gate); used to hear a note being played again.
            let sum = 0;
            for (let i = 0; i < buffer.length; i++) sum += buffer[i] * buffer[i];
            const level = 10 * Math.log10(sum / buffer.length + 1e-12);
            return { t: ctx.currentTime * 1000, hz, clarity, level };
        },
        setNoiseGate(db) {
            detector.minVolumeDecibels = db;
        },
        click(atMs, accent = false) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.frequency.value = accent ? 2000 : 1500;
            const t = atMs / 1000;
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(accent ? 0.5 : 0.3, t + 0.002);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.04);
            osc.connect(gain).connect(ctx.destination);
            osc.start(t);
            osc.stop(t + 0.05);
        },
        async close() {
            stream.getTracks().forEach((track) => track.stop());
            await ctx.close();
        },
    };
}
