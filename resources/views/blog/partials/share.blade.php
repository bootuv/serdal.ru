{{-- «Поделиться» статьей: окно с вертикальной картинкой для сторис (BlogShareImage::STORY, как у отзывов в кабинете),
     «Скачать картинку» (на телефоне — системное окно «Поделиться» с картинкой), ссылка, Telegram и ВКонтакте.
     Картинка грузится при первом открытии окна — пока ее собирают, заглушка «Готовим картинку…». --}}
@php
    $shareUrl = \App\Support\Seo::url(route('blog.show', $post->slug, false));
    $storyUrl = app(\App\Services\BlogShareImage::class)->url($post, \App\Services\BlogShareImage::STORY);
@endphp
<button type="button" class="blog-like blog-share-open" data-blog-share-open aria-haspopup="dialog">
    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 15V3m0 0L7.5 7.5M12 3l4.5 4.5M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
    <span>Поделиться</span>
</button>

<dialog class="blog-share" data-blog-share data-story="{{ $storyUrl }}" data-url="{{ $shareUrl }}" data-title="{{ $post->title }}"
        data-file="serdal-{{ $post->slug }}.jpg" aria-labelledby="blog-share-title">
    <div class="blog-share-body">
        <div class="blog-share-preview">
            <img alt="Картинка статьи для сторис" data-blog-share-img>
            <div class="blog-share-loading" data-blog-share-loading><span></span>Готовим картинку…</div>
        </div>
        <div class="blog-share-side">
            <div class="blog-share-head">
                <h2 id="blog-share-title" class="blog-share-title">Поделиться статьей</h2>
                <button type="button" class="blog-share-close" data-blog-share-close aria-label="Закрыть">
                    <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
            <div class="blog-share-block">
                <div class="blog-share-label">Картинка для сторис</div>
                <div class="blog-meta">1080 × 1920 · Telegram, ВКонтакте, WhatsApp</div>
                <a href="{{ $storyUrl }}" download="serdal-{{ $post->slug }}.jpg" class="blog-cta-button blog-share-download" data-blog-share-download>Скачать картинку</a>
            </div>
            <div class="blog-share-block">
                <div class="blog-share-label">Ссылка на статью</div>
                <div class="blog-share-links">
                    <button type="button" class="blog-share-link" data-blog-share-copy>Скопировать ссылку</button>
                    <a class="blog-share-link" href="https://t.me/share/url?url={{ urlencode($shareUrl) }}&text={{ urlencode($post->title) }}" target="_blank" rel="noopener">Telegram</a>
                    <a class="blog-share-link" href="https://vk.com/share.php?url={{ urlencode($shareUrl) }}" target="_blank" rel="noopener">ВКонтакте</a>
                </div>
            </div>
        </div>
    </div>
</dialog>

<script>
(() => {
    const dialog = document.querySelector('[data-blog-share]');
    const open = document.querySelector('[data-blog-share-open]');
    if (!dialog || !open) return;

    const img = dialog.querySelector('[data-blog-share-img]');
    const loading = dialog.querySelector('[data-blog-share-loading]');
    const download = dialog.querySelector('[data-blog-share-download]');
    const copy = dialog.querySelector('[data-blog-share-copy]');
    const mobile = matchMedia('(pointer: coarse)').matches;
    let blob = null;
    let fetching = null;

    // Картинку скачиваем один раз: ее же показываем и отдаем в «Поделиться» без ожидания
    const load = () => fetching ??= fetch(dialog.dataset.story)
        .then((r) => r.ok ? r.blob() : null)
        .then((b) => { blob = b; img.src = b ? URL.createObjectURL(b) : dialog.dataset.story; return b; })
        .catch(() => { img.src = dialog.dataset.story; return null; });

    img.addEventListener('load', () => loading.hidden = true);
    img.addEventListener('error', () => loading.hidden = true);

    open.addEventListener('click', () => { dialog.showModal(); load(); });
    dialog.querySelector('[data-blog-share-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });

    // На телефоне — системное окно «Поделиться» с картинкой (сразу в сторис или мессенджер), иначе — обычное скачивание
    if (mobile && navigator.canShare) download.textContent = 'Поделиться картинкой';
    download.addEventListener('click', (e) => {
        if (!mobile || !blob || !navigator.canShare) return;
        const file = new File([blob], dialog.dataset.file, { type: 'image/jpeg' });
        if (!navigator.canShare({ files: [file] })) return;
        e.preventDefault();
        navigator.share({ files: [file] }).catch(() => {});
    });

    copy.addEventListener('click', () => {
        navigator.clipboard.writeText(dialog.dataset.url).then(() => {
            copy.textContent = 'Ссылка скопирована';
            setTimeout(() => copy.textContent = 'Скопировать ссылку', 2000);
        });
    });
})();
</script>
