// Редактор форматированного текста для кабинетов (<x-ui.editor>): жирный, курсив, списки, ссылки.
// Третий параметр options: { images: true } — ещё и картинки (<x-ui.editor-media>, статьи базы знаний); { videos: true } — и видео (новости).
// Экземпляр Tiptap держим в замыкании, а не в реактивных данных Alpine (иначе Proxy ломает редактор).
import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extension-placeholder';

// Картинка отдельным блоком: <img src alt>. Разбирает и картинки из старого редактора (img внутри figure).
const Image = Node.create({
    name: 'image',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes() {
        return { src: { default: null }, alt: { default: null } };
    },
    parseHTML() {
        return [{ tag: 'img[src]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['img', mergeAttributes(HTMLAttributes)];
    },
});

// Видео отдельным блоком. loop — бывший GIF: без звука, крутится сам; обычное — с кнопками плеера.
const Video = Node.create({
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

window.richEditor = (content, placeholder = '', options = {}) => {
    let editor = null;

    return {
        content,
        tick: 0,
        uploading: false,
        uploadingText: 'Загружаем картинку…',
        processing: 0,

        init() {
            editor = new Editor({
                element: this.$refs.editor,
                extensions: [
                    StarterKit.configure({
                        heading: false, blockquote: false, code: false, codeBlock: false,
                        horizontalRule: false, strike: false, underline: false,
                        link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
                    }),
                    Placeholder.configure({ placeholder }),
                    ...(options.images ? [Image] : []),
                    ...(options.videos ? [Video] : []),
                ],
                content: this.content || '',
                editorProps: { attributes: { class: 'rich min-h-40 px-4 py-3', 'aria-multiline': 'true', role: 'textbox' } },
                onUpdate: ({ editor: e }) => { this.content = e.isEmpty ? '' : e.getHTML(); },
                onTransaction: () => { this.tick++; },
            });

            // Видео, которые еще сжимаются (черновик открыли до конца обработки), — ждем и их
            if (options.videos) this.videosIn(this.content).forEach((src) => this.waitVideo(src));

            this.$watch('content', (value) => {
                const current = editor.isEmpty ? '' : editor.getHTML();
                if ((value || '') !== current) editor.commands.setContent(value || '', { emitUpdate: false });
            });
        },

        active(name) { this.tick; return editor?.isActive(name) ?? false; },
        bold() { editor.chain().focus().toggleBold().run(); },
        italic() { editor.chain().focus().toggleItalic().run(); },
        bullets() { editor.chain().focus().toggleBulletList().run(); },
        numbers() { editor.chain().focus().toggleOrderedList().run(); },
        link() {
            const previous = editor.getAttributes('link').href || '';
            const url = window.prompt('Ссылка', previous || 'https://');
            if (url === null) return;
            const chain = editor.chain().focus().extendMarkRange('link');
            (url.trim() === '' || url.trim() === 'https://') ? chain.unsetLink().run() : chain.setLink({ href: url.trim() }).run();
        },
        // Картинка: файл загружается во временное свойство Livewire (prop), метод компонента (method) кладёт его на CDN и возвращает адрес
        // Метод может вернуть и видео (GIF в новостях становится зацикленным видео) — тогда вставляем его
        uploadImage(file, prop, method) {
            if (! file || ! options.images) return;
            this.uploading = true;
            this.uploadingText = 'Загружаем картинку…';
            const done = () => { this.uploading = false; };
            this.$wire.upload(prop, file, async () => {
                try {
                    const result = await this.$wire.call(method);
                    if (result && typeof result === 'object') this.insertVideo(result);
                    else if (result) editor.chain().focus().insertContent({ type: 'image', attrs: { src: result, alt: '' } }).run();
                } finally { done(); }
            }, () => {
                done();
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Картинку не удалось загрузить — попробуйте файл поменьше', tone: 'danger' } }));
            });
        },
        uploadVideo(file, prop, method) {
            if (! file || ! options.videos) return;
            this.uploading = true;
            this.uploadingText = 'Загружаем видео… 0%';
            const done = () => { this.uploading = false; };
            this.$wire.upload(prop, file, async () => {
                try {
                    const result = await this.$wire.call(method);
                    if (result) this.insertVideo(result);
                } finally { done(); }
            }, () => {
                done();
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Видео не удалось загрузить — проверьте размер (до 200 МБ)', tone: 'danger' } }));
            }, (event) => { this.uploadingText = `Загружаем видео… ${event.detail.progress}%`; });
        },
        insertVideo({ src, poster, loop }) {
            editor.chain().focus().insertContent({ type: 'video', attrs: { src, poster, loop: !! loop } }).run();
            this.waitVideo(src);
        },
        videosIn(html) {
            return [...(html || '').matchAll(/<video[^>]*\ssrc="([^"]+)"/g)].map((m) => m[1]);
        },
        // Ролик сжимается в очереди: спрашиваем сервер, пока не будет готов, затем перезагружаем плеер в редакторе
        async waitVideo(src) {
            if ((await this.$wire.call('mediaStatus', src)) === 'ready') return;
            this.processing++;
            const timer = setInterval(async () => {
                const status = await this.$wire.call('mediaStatus', src);
                if (status === 'pending') return;
                clearInterval(timer);
                this.processing--;
                if (status === 'failed') {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Видео не удалось обработать — удалите его и загрузите другой файл', tone: 'danger' } }));
                    return;
                }
                this.$refs.editor.querySelectorAll('video').forEach((el) => { if (el.getAttribute('src') === src) el.load(); });
            }, 5000);
        },
        destroy() { editor?.destroy(); },
    };
};
