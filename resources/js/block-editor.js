// Блочный редактор в духе Notion для статей блога (<x-ui.block-editor>):
// «/» в пустой строке открывает меню блоков, выделение текста — панель «жирный, курсив, ссылка»,
// Markdown-сокращения («## », «- », «1. », «> », «---»), картинки — из меню, вставкой и перетаскиванием.
// Экземпляр Tiptap держим в замыкании, а не в реактивных данных Alpine (иначе Proxy ломает редактор).
import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extension-placeholder';

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

// Выделенный блок: важная мысль, совет, вывод — <aside class="callout">
const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'inline*',
    defining: true,
    parseHTML() {
        return [{ tag: 'aside.callout' }];
    },
    renderHTML() {
        return ['aside', { class: 'callout' }, 0];
    },
});

// Блоки меню «/»: подпись, подсказка (Markdown-сокращение), слова для поиска, команда
const BLOCKS = [
    { id: 'text', title: 'Текст', hint: 'обычный абзац', words: 'текст абзац параграф', run: (c) => c.setParagraph() },
    { id: 'h2', title: 'Заголовок', hint: '## ', words: 'заголовок раздел h2', run: (c) => c.setHeading({ level: 2 }) },
    { id: 'h3', title: 'Подзаголовок', hint: '### ', words: 'подзаголовок h3', run: (c) => c.setHeading({ level: 3 }) },
    { id: 'ul', title: 'Список', hint: '- ', words: 'список маркированный пункты', run: (c) => c.toggleBulletList() },
    { id: 'ol', title: 'Нумерованный список', hint: '1. ', words: 'нумерованный список шаги', run: (c) => c.toggleOrderedList() },
    { id: 'quote', title: 'Цитата', hint: '> ', words: 'цитата', run: (c) => c.setBlockquote() },
    { id: 'callout', title: 'Выделенный блок', hint: 'совет, вывод', words: 'выделенный блок совет важно вывод', run: (c) => c.setNode('callout') },
    { id: 'hr', title: 'Разделитель', hint: '---', words: 'разделитель линия', run: (c) => c.setHorizontalRule() },
    { id: 'image', title: 'Картинка', hint: 'с компьютера', words: 'картинка изображение фото', run: null },
];

window.blockEditor = (content, options = {}) => {
    let editor = null;

    return {
        content,
        tick: 0,
        uploading: false,
        menu: { open: false, query: '', index: 0, from: 0, x: 0, y: 0 },
        bubble: { open: false, x: 0, y: 0 },

        init() {
            const self = this;
            editor = new Editor({
                element: this.$refs.editor,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3] },
                        code: false, codeBlock: false, underline: false,
                        link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
                    }),
                    Placeholder.configure({
                        includeChildren: false,
                        placeholder: ({ node }) => node.type.name === 'heading' ? 'Заголовок' : (options.placeholder || 'Пишите или нажмите «/» для выбора блока'),
                    }),
                    Image,
                    Callout,
                ],
                content: this.content || '',
                editorProps: {
                    attributes: { class: 'block-content min-h-60 py-2', 'aria-multiline': 'true', role: 'textbox' },
                    handleKeyDown: (view, event) => self.onKey(event),
                    handlePaste: (view, event) => self.dropFiles(event.clipboardData?.files),
                    handleDrop: (view, event) => self.dropFiles(event.dataTransfer?.files),
                },
                onUpdate: ({ editor: e }) => { this.content = e.isEmpty ? '' : e.getHTML(); },
                onTransaction: () => { this.tick++; this.sync(); },
                onBlur: () => { setTimeout(() => { this.bubble.open = false; }, 150); },
            });

            this.$watch('content', (value) => {
                const current = editor.isEmpty ? '' : editor.getHTML();
                if ((value || '') !== current) editor.commands.setContent(value || '', { emitUpdate: false });
            });
        },

        /* ---------- Меню «/» и панель выделения ---------- */

        // После каждого изменения: «/текст» в начале пустого абзаца — меню блоков; выделенный текст — панель
        sync() {
            const { state, view } = editor;
            const { selection } = state;
            const box = this.$root.getBoundingClientRect();

            const $from = selection.$from;
            const before = selection.empty && $from.parent.type.name === 'paragraph'
                ? $from.parent.textBetween(0, $from.parentOffset, '\0', '\0') : '';
            const slash = before.match(/^\/([^\s/]*)$/u);
            if (slash) {
                const at = view.coordsAtPos($from.pos);
                const query = slash[1].toLowerCase();
                if (! this.menu.open || this.menu.query !== query) this.menu.index = 0;
                this.menu = { ...this.menu, open: true, query, from: $from.pos - before.length, x: at.left - box.left, y: at.bottom - box.top + 8 };
            } else {
                this.menu.open = false;
            }

            const textSelected = ! selection.empty && ! selection.node && editor.isFocused;
            if (textSelected) {
                const start = view.coordsAtPos(selection.from);
                this.bubble = { open: true, x: Math.max(0, start.left - box.left), y: start.top - box.top - 52 };
            } else {
                this.bubble.open = false;
            }
        },

        items() {
            this.tick;
            const q = this.menu.query;
            return BLOCKS.filter((b) => q === '' || b.title.toLowerCase().includes(q) || b.words.includes(q));
        },

        onKey(event) {
            if (! this.menu.open) return false;
            const list = this.items();
            if (event.key === 'ArrowDown') { this.menu.index = (this.menu.index + 1) % Math.max(1, list.length); return true; }
            if (event.key === 'ArrowUp') { this.menu.index = (this.menu.index - 1 + list.length) % Math.max(1, list.length); return true; }
            if (event.key === 'Enter') { if (list[this.menu.index]) { this.pick(list[this.menu.index].id); return true; } return false; }
            if (event.key === 'Escape') { this.menu.open = false; return true; }
            return false;
        },

        // Выбор блока: стираем «/запрос» и превращаем строку в блок
        pick(id) {
            const block = BLOCKS.find((b) => b.id === id);
            const to = editor.state.selection.from;
            const chain = editor.chain().focus().deleteRange({ from: this.menu.from, to });
            this.menu.open = false;
            if (id === 'image') {
                chain.run();
                this.$refs.image.click();
                return;
            }
            block.run(chain).run();
        },

        active(name, attrs = {}) { this.tick; return editor?.isActive(name, attrs) ?? false; },
        bold() { editor.chain().focus().toggleBold().run(); },
        italic() { editor.chain().focus().toggleItalic().run(); },
        heading(level) { editor.chain().focus().toggleHeading({ level }).run(); },
        link() {
            const previous = editor.getAttributes('link').href || '';
            const url = window.prompt('Ссылка', previous || 'https://');
            if (url === null) return;
            const chain = editor.chain().focus().extendMarkRange('link');
            (url.trim() === '' || url.trim() === 'https://') ? chain.unsetLink().run() : chain.setLink({ href: url.trim() }).run();
        },

        /* ---------- Картинки ---------- */

        // Вставка и перетаскивание файлов: картинки загружаем, остальное — как обычно
        dropFiles(files) {
            const images = Array.from(files || []).filter((f) => f.type.startsWith('image/'));
            if (! images.length) return false;
            images.forEach((file) => this.uploadImage(file));
            return true;
        },

        // Файл — во временное свойство Livewire (uploadModel), метод компонента (uploadMethod) кладёт его на CDN и возвращает адрес
        uploadImage(file) {
            if (! file) return;
            this.uploading = true;
            const done = () => { this.uploading = false; };
            this.$wire.upload(options.uploadModel, file, async () => {
                try {
                    const url = await this.$wire.call(options.uploadMethod);
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
