// Редактор форматированного текста для кабинетов (<x-ui.editor>): жирный, курсив, списки, ссылки.
// Экземпляр Tiptap держим в замыкании, а не в реактивных данных Alpine (иначе Proxy ломает редактор).
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extension-placeholder';

window.richEditor = (content, placeholder = '') => {
    let editor = null;

    return {
        content,
        tick: 0,

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
        destroy() { editor?.destroy(); },
    };
};
