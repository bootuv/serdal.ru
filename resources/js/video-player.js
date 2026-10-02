/**
 * Плеер записей занятий (resources/views/components/ui/video-player.blade.php): своё управление поверх <video>,
 * панель показывается при наведении и касании, прячется через 2,5 с без движения во время просмотра.
 * Перемотка: пока бегунок тянут, меняется только показанное время; видео перематывается один раз — при отпускании
 * (иначе каждое движение — новый запрос к хранилищу, а обновления времени дёргают бегунок назад).
 * Воспроизведение, перемотка с буфером, время, громкость, скорость (запоминается), полный экран.
 * Клавиши, когда плеер в фокусе: пробел/K — пауза, ←/→ — 10 секунд, ↑/↓ — громкость, M — звук, F — полный экран.
 */
const SPEEDS = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
const SPEED_KEY = 'serdal.recordingSpeed';

function storedSpeed() {
    try {
        const v = parseFloat(localStorage.getItem(SPEED_KEY));
        return SPEEDS.includes(v) ? v : 1;
    } catch {
        return 1;
    }
}

function clock(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) seconds = 0;
    const s = Math.floor(seconds % 60);
    const m = Math.floor(seconds / 60) % 60;
    const h = Math.floor(seconds / 3600);
    const mm = h ? String(m).padStart(2, '0') : String(m);

    return (h ? h + ':' : '') + mm + ':' + String(s).padStart(2, '0');
}

function registerVideoPlayer(Alpine) {
    Alpine.data('videoPlayer', () => ({
        speeds: SPEEDS,
        playing: false,
        started: false,
        waiting: false,
        current: 0,
        duration: 0,
        buffered: 0,
        volume: 1,
        muted: false,
        speed: storedSpeed(),
        speedOpen: false,
        full: false,
        scrubbing: false,
        scrubTime: 0,
        hover: false,
        hoverTimer: null,
        // Видео в тексте (data-fit): рамка по пропорциям ролика, «ширина / высота»
        fit: false,
        ratio: '16 / 9',

        init() {
            const v = this.$refs.video;
            if (this.$root.dataset.fit !== undefined) {
                this.fit = true;
                if (this.$root.dataset.fit) this.ratio = this.$root.dataset.fit;
                v.addEventListener('loadedmetadata', () => { if (v.videoWidth && v.videoHeight) this.ratio = `${v.videoWidth} / ${v.videoHeight}`; });
            }
            v.playbackRate = this.speed;
            v.addEventListener('loadedmetadata', () => { this.duration = v.duration || 0; v.playbackRate = this.speed; });
            v.addEventListener('durationchange', () => { this.duration = v.duration || 0; });
            v.addEventListener('timeupdate', () => { this.current = v.currentTime; this.updateBuffered(); });
            v.addEventListener('progress', () => this.updateBuffered());
            v.addEventListener('play', () => { this.playing = true; this.started = true; });
            v.addEventListener('pause', () => { this.playing = false; });
            v.addEventListener('ended', () => { this.playing = false; });
            v.addEventListener('seeked', () => { this.current = v.currentTime; this.waiting = false; });
            v.addEventListener('waiting', () => { this.waiting = true; });
            v.addEventListener('playing', () => { this.waiting = false; });
            v.addEventListener('canplay', () => { this.waiting = false; });
            v.addEventListener('volumechange', () => { this.volume = v.volume; this.muted = v.muted || v.volume === 0; });
            v.addEventListener('ratechange', () => { this.speed = v.playbackRate; });
            document.addEventListener('fullscreenchange', () => { this.full = document.fullscreenElement === this.$root; });
            // Отпустили бегунок за его пределами — всё равно перематываем
            window.addEventListener('pointerup', () => { if (this.scrubbing) this.scrubEnd(this.scrubTime); });
        },

        // Высокий (вертикальный) ролик не выше 80% экрана: ширина рамки — от высоты экрана и пропорций
        fitStyle() {
            if (! this.fit || this.full) return '';
            const [w, h] = this.ratio.split('/').map((n) => parseFloat(n));
            return w && h ? `max-width: calc(80svh * ${w / h} + 8px)` : '';
        },

        // Панель видна, пока двигают мышью (касаются); через 2,5 с без движения во время просмотра прячется
        wake() {
            this.hover = true;
            clearTimeout(this.hoverTimer);
            this.hoverTimer = setTimeout(() => {
                if (this.playing && !this.speedOpen && !this.scrubbing) this.hover = false;
            }, 2500);
        },

        rest() {
            clearTimeout(this.hoverTimer);
            if (!this.speedOpen && !this.scrubbing) this.hover = false;
        },

        shown() {
            return this.scrubbing ? this.scrubTime : this.current;
        },

        scrubStart() {
            this.scrubbing = true;
            this.scrubTime = this.current;
        },

        scrub(value) {
            if (!this.scrubbing) this.scrubStart();
            this.scrubTime = Number(value);
        },

        scrubEnd(value) {
            this.scrubbing = false;
            this.seek(value);
        },

        updateBuffered() {
            const v = this.$refs.video;
            if (v.buffered.length && v.duration) {
                this.buffered = v.buffered.end(v.buffered.length - 1);
            }
        },

        toggle() {
            const v = this.$refs.video;
            v.paused || v.ended ? v.play().catch(() => {}) : v.pause();
        },

        seek(value) {
            this.$refs.video.currentTime = Math.min(Math.max(0, Number(value)), this.duration || 0);
            this.current = this.$refs.video.currentTime;
        },

        skip(seconds) {
            this.seek(this.$refs.video.currentTime + seconds);
        },

        setVolume(value) {
            const v = this.$refs.video;
            v.volume = Math.min(Math.max(0, Number(value)), 1);
            v.muted = v.volume === 0;
        },

        toggleMute() {
            const v = this.$refs.video;
            if (v.muted || v.volume === 0) {
                v.muted = false;
                if (v.volume === 0) v.volume = 0.6;
            } else {
                v.muted = true;
            }
        },

        setSpeed(rate) {
            this.$refs.video.playbackRate = rate;
            this.speed = rate;
            this.speedOpen = false;
            try { localStorage.setItem(SPEED_KEY, String(rate)); } catch {}
        },

        speedLabel(rate) {
            return String(rate).replace('.', ',') + '×';
        },

        toggleFull() {
            const root = this.$root;
            const v = this.$refs.video;
            if (document.fullscreenElement) {
                document.exitFullscreen().catch(() => {});
            } else if (root.requestFullscreen) {
                root.requestFullscreen().catch(() => {});
            } else if (v.webkitEnterFullscreen) {
                v.webkitEnterFullscreen(); // iPhone: полный экран есть только у самого видео
            }
        },

        key(e) {
            if (e.target.closest('input[type="range"]') && ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) return;
            const actions = {
                ' ': () => this.toggle(), k: () => this.toggle(), 'л': () => this.toggle(),
                ArrowLeft: () => this.skip(-10), ArrowRight: () => this.skip(10),
                ArrowUp: () => this.setVolume(this.$refs.video.volume + 0.1), ArrowDown: () => this.setVolume(this.$refs.video.volume - 0.1),
                m: () => this.toggleMute(), 'ь': () => this.toggleMute(),
                f: () => this.toggleFull(), 'а': () => this.toggleFull(),
            };
            const action = actions[e.key] || actions[e.key.toLowerCase()];
            if (action && !e.metaKey && !e.ctrlKey && !e.altKey) {
                e.preventDefault();
                action();
                this.wake();
            }
        },

        time(seconds) {
            return clock(seconds);
        },
    }));
}

if (window.Alpine) {
    registerVideoPlayer(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerVideoPlayer(window.Alpine));
}
