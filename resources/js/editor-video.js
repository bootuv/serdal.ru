// Видео в тексте (новости и статьи блога, x-ui.block-editor с video-method): узел TipTap и ожидание сжатия на сервере (MediaService).
// Пока файл загружается и пока ролик сжимается, на его месте — заглушка в пропорциях видео с процентом; готово — показываем сам ролик.
import { Node, mergeAttributes } from '@tiptap/core';

// Состояние роликов по адресу: { status: uploading|pending|ready|failed, progress }. Узлы перерисовываются по событию editor-video.
// Пока файл загружается, у узла временный адрес upload:… — после загрузки его заменяет настоящий.
const states = new Map();

export function setState(src, state) {
    states.set(src, state);
    window.dispatchEvent(new CustomEvent('editor-video', { detail: { src } }));
}

function playerAttrs(attrs) {
    return attrs.loop
        ? { autoplay: '', loop: '', muted: '', playsinline: '' }
        : { controls: '', preload: 'metadata', playsinline: '' };
}

// Видео отдельным блоком. loop — бывший GIF: без звука, крутится сам; обычное — с кнопками плеера.
// width/height — размер после сжатия: по нему заглушка и плеер держат пропорции.
export const Video = Node.create({
    name: 'video',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes() {
        const number = (name) => ({ default: null, parseHTML: (el) => parseInt(el.getAttribute(name), 10) || null });
        return {
            src: { default: null },
            poster: { default: null },
            width: number('width'),
            height: number('height'),
            loop: { default: false, parseHTML: (el) => el.hasAttribute('loop'), renderHTML: () => ({}) },
        };
    },
    parseHTML() {
        return [{ tag: 'video[src]' }];
    },
    renderHTML({ node, HTMLAttributes }) {
        return ['video', mergeAttributes(HTMLAttributes, playerAttrs(node.attrs))];
    },
    addNodeView() {
        return ({ node }) => {
            const { src, poster, width, height } = node.attrs;
            const ratio = width && height ? `${width} / ${height}` : '16 / 9';

            const dom = document.createElement('div');
            dom.className = 'video-node';
            // Высокий (вертикальный) ролик не выше 70% экрана: ширина — от высоты экрана и пропорций
            if (width && height) dom.style.maxWidth = `calc(70svh * ${width / height})`;

            const video = document.createElement('video');
            const real = ! String(src).startsWith('upload:');
            Object.entries({ ...(real ? { src } : {}), ...(poster ? { poster } : {}), ...playerAttrs(node.attrs) }).forEach(([k, v]) => video.setAttribute(k, v));
            if (node.attrs.loop) video.muted = true;
            video.style.aspectRatio = ratio;

            const wait = document.createElement('div');
            wait.className = 'video-wait';
            wait.style.aspectRatio = ratio;
            wait.innerHTML = '<span class="video-wait-spinner" aria-hidden="true"></span><span class="video-wait-text" role="status"></span><span class="video-wait-bar" aria-hidden="true"><span></span></span>';
            const text = wait.querySelector('.video-wait-text');
            const bar = wait.querySelector('.video-wait-bar > span');

            dom.append(video, wait);

            let wasPending = false;
            const render = () => {
                const state = states.get(src) || { status: 'ready', progress: 0 };
                const uploading = state.status === 'uploading';
                const pending = uploading || state.status === 'pending';
                const failed = state.status === 'failed';
                video.hidden = pending || failed;
                wait.hidden = ! pending && ! failed;
                wait.classList.toggle('is-failed', failed);
                if (uploading) {
                    text.textContent = `Загружаем видео… ${state.progress}%`;
                    bar.style.width = `${Math.max(state.progress, 2)}%`;
                } else if (pending) {
                    text.textContent = state.progress > 0 ? `Обрабатываем видео… ${state.progress}%` : 'Видео в очереди на обработку…';
                    bar.style.width = `${Math.max(state.progress, 2)}%`;
                    wasPending = true;
                } else if (failed) {
                    text.textContent = 'Видео не удалось обработать — удалите его и загрузите другой файл';
                } else if (wasPending) {
                    wasPending = false;
                    video.load();
                }
            };
            const onState = (event) => { if (event.detail.src === src) render(); };
            window.addEventListener('editor-video', onState);
            render();

            return {
                dom,
                // Клики по плееру и заглушке не должны менять документ
                ignoreMutation: () => true,
                stopEvent: (event) => event.target === video && event.type !== 'dragstart',
                destroy: () => window.removeEventListener('editor-video', onState),
            };
        };
    },
});

export const videosIn = (html) => [...(html || '').matchAll(/<video[^>]*\ssrc="([^"]+)"/g)].map((m) => m[1]);

// Только что загруженный ролик: заглушка появляется сразу, еще до первого ответа сервера
export function markPending(src) {
    setState(src, { status: 'pending', progress: 0 });
}

// Ролик сжимается в очереди: спрашиваем метод компонента mediaStatus раз в 2 секунды, пока не будет готов (ready / failed)
export async function waitVideo($wire, src, onDone) {
    const first = await $wire.call('mediaStatus', src);
    setState(src, first);
    if (first.status !== 'pending') return;
    const timer = setInterval(async () => {
        const state = await $wire.call('mediaStatus', src);
        setState(src, state);
        if (state.status === 'pending') return;
        clearInterval(timer);
        onDone(state.status);
    }, 2000);
}
