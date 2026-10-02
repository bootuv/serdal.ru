// Видео в тексте (новости, x-ui.block-editor с video-method): узел TipTap и ожидание сжатия на сервере (MediaService).
import { Node, mergeAttributes } from '@tiptap/core';

// Видео отдельным блоком. loop — бывший GIF: без звука, крутится сам; обычное — с кнопками плеера.
export const Video = Node.create({
    name: 'video',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes() {
        return {
            src: { default: null },
            poster: { default: null },
            loop: { default: false, parseHTML: (el) => el.hasAttribute('loop'), renderHTML: () => ({}) },
        };
    },
    parseHTML() {
        return [{ tag: 'video[src]' }];
    },
    renderHTML({ node, HTMLAttributes }) {
        const extra = node.attrs.loop
            ? { autoplay: '', loop: '', muted: '', playsinline: '' }
            : { controls: '', preload: 'metadata', playsinline: '' };
        return ['video', mergeAttributes(HTMLAttributes, extra)];
    },
});

export const videosIn = (html) => [...(html || '').matchAll(/<video[^>]*\ssrc="([^"]+)"/g)].map((m) => m[1]);

// Ролик сжимается в очереди: спрашиваем метод компонента mediaStatus, пока не будет готов (ready / failed)
export async function waitVideo($wire, src, onState) {
    if ((await $wire.call('mediaStatus', src)) === 'ready') return;
    onState('pending');
    const timer = setInterval(async () => {
        const status = await $wire.call('mediaStatus', src);
        if (status === 'pending') return;
        clearInterval(timer);
        onState(status);
    }, 5000);
}
