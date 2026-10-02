// Редактор форматированного текста для кабинетов (<x-ui.editor>): жирный, курсив, списки, ссылки.
// Третий параметр options: { images: true } — ещё и картинки (<x-ui.editor-media>, статьи базы знаний).
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

window.richEditor = (content, placeholder = '', options = {}) => {
    let editor = null;

    return {
        content,
        tick: 0,
        uploading: false,

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
                ],
                content: this.content || '',
                editorProps: { attributes: { class: 'rich min-h-40 px-4 py-3', 'aria-multiline': 'true', role: 'textbox' } },
                onUpdate: ({ editor: e }) => { this.content = e.isEmpty ? '' : e.getHTML(); },
                onTransaction: () => { this.tick++; },
            });

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
        uploadImage(file, prop, method) {
            if (! file || ! options.images) return;
            this.uploading = true;
            const done = () => { this.uploading = false; };
            this.$wire.upload(prop, file, async () => {
                try {
                    const url = await this.$wire.call(method);
                    if (url) editor.chain().focus().insertContent({ type: 'image', attrs: { src: url, alt: '' } }).run();
                } finally { done(); }
            }, () => {
                done();
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Картинку не удалось загрузить — попробуйте файл поменьше', tone: 'danger' } }));
            });
        },
        destroy() { editor?.destroy(); },
    };
};
