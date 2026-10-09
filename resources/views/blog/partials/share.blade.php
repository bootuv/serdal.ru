{{-- «Поделиться» статьей: окно с картинкой статьи — переключатель «Сторис» (BlogShareImage::STORY, 1080×1920)
     и «Пост» (та же картинка, что og:image, 1200×630), «Скачать картинку» (на телефоне — системное окно «Поделиться»
     с картинкой), ссылка, Telegram и ВКонтакте. Картинка грузится, когда ее впервые показывают, — пока ее собирают,
     заглушка «Готовим картинку…». --}}
@php
    $shareImage = app(\App\Services\BlogShareImage::class);
    $shareUrl = \App\Support\Seo::url(route('blog.show', $post->slug, false));
    $formats = [
        'story' => ['label' => 'Сторис', 'title' => 'Картинка для сторис', 'hint' => '1080 × 1920 · Telegram, ВКонтакте, WhatsApp',
            'url' => $shareImage->url($post, \App\Services\BlogShareImage::STORY), 'file' => 'serdal-' . $post->slug . '-storis.jpg'],
        'post' => ['label' => 'Пост', 'title' => 'Картинка для поста', 'hint' => '1200 × 630 · посты в соцсетях и каналах',
            'url' => $shareImage->url($post), 'file' => 'serdal-' . $post->slug . '.jpg'],
    ];
@endphp
<button type="button" class="blog-like blog-share-open" data-blog-share-open aria-haspopup="dialog">
    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 15V3m0 0L7.5 7.5M12 3l4.5 4.5M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
    <span>Поделиться</span>
</button>

<dialog class="blog-share" data-blog-share data-format="story" data-url="{{ $shareUrl }}" data-formats="{{ json_encode($formats) }}"
        aria-labelledby="blog-share-title">
    <div class="blog-share-head">
        <h2 id="blog-share-title" class="blog-share-title">Поделиться статьей</h2>
        <button type="button" class="blog-share-close" data-blog-share-close aria-label="Закрыть">
            <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>
    <div class="blog-share-switch" role="tablist" aria-label="Вид картинки">
        @foreach($formats as $key => $format)
            <button type="button" role="tab" data-blog-share-format="{{ $key }}" aria-selected="{{ $key === 'story' ? 'true' : 'false' }}">{{ $format['label'] }}</button>
        @endforeach
    </div>
    <div class="blog-share-body">
        <div class="blog-share-preview">
            <img alt="Картинка статьи" data-blog-share-img>
            <div class="blog-share-loading" data-blog-share-loading><span></span>Готовим картинку…</div>
        </div>
        <div class="blog-share-side">
            <div class="blog-share-block">
                <div class="blog-share-label" data-blog-share-title>{{ $formats['story']['title'] }}</div>
                <div class="blog-meta" data-blog-share-hint>{{ $formats['story']['hint'] }}</div>
                <a href="{{ $formats['story']['url'] }}" download="{{ $formats['story']['file'] }}" class="blog-cta-button blog-share-download" data-blog-share-download>Скачать картинку</a>
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

    const formats = JSON.parse(dialog.dataset.formats);
    const img = dialog.querySelector('[data-blog-share-img]');
    const loading = dialog.querySelector('[data-blog-share-loading]');
    const download = dialog.querySelector('[data-blog-share-download]');
    const title = dialog.querySelector('[data-blog-share-title]');
    const hint = dialog.querySelector('[data-blog-share-hint]');
    const copy = dialog.querySelector('[data-blog-share-copy]');
    const tabs = dialog.querySelectorAll('[data-blog-share-format]');
    const mobile = matchMedia('(pointer: coarse)').matches;
    const blobs = {};
    const fetching = {};
    let current = 'story';

    // Каждую картинку скачиваем один раз: ее же показываем и отдаем в «Поделиться» без ожидания
    const load = (key) => fetching[key] ??= fetch(formats[key].url)
        .then((r) => r.ok ? r.blob() : null)
        .then((b) => blobs[key] = b)
        .catch(() => null);

    const show = (key) => {
        current = key;
        dialog.dataset.format = key;
        tabs.forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.blogShareFormat === key)));
        title.textContent = formats[key].title;
        hint.textContent = formats[key].hint;
        download.href = formats[key].url;
        download.download = formats[key].file;
        loading.hidden = false;
        img.removeAttribute('src');
        load(key).then((b) => { if (current === key) img.src = b ? URL.createObjectURL(b) : formats[key].url; });
    };

    img.addEventListener('load', () => loading.hidden = true);
    img.addEventListener('error', () => loading.hidden = true);
    tabs.forEach((tab) => tab.addEventListener('click', () => show(tab.dataset.blogShareFormat)));

    open.addEventListener('click', () => { dialog.showModal(); if (!img.getAttribute('src')) show(current); });
    dialog.querySelector('[data-blog-share-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });

    // На телефоне — системное окно «Поделиться» с картинкой (сразу в сторис или мессенджер), иначе — обычное скачивание
    if (mobile && navigator.canShare) download.textContent = 'Поделиться картинкой';
    download.addEventListener('click', (e) => {
        const blob = blobs[current];
        if (!mobile || !blob || !navigator.canShare) return;
        const file = new File([blob], formats[current].file, { type: 'image/jpeg' });
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
