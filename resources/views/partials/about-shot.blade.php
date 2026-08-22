{{--
    Место под скриншот платформы.

    Пока реальной картинки нет, рисуется аккуратная заглушка-скелет.
    Чтобы поставить настоящий скриншот — положите файл в public/images/about/
    и передайте его в параметре src:

        @include('partials.about-shot', [
            'label' => 'Расписание занятий',
            'src'   => '/images/about/schedule.png',
        ])

    Параметры:
        label — подпись под картинкой (обязателен, используется и как alt)
        src   — путь к картинке; если не передан, рисуется заглушка
        ratio — пропорции: '16-9' (по умолчанию) или '4-3'
--}}
@php
    $src = $src ?? null;
    $ratio = $ratio ?? '16-9';
@endphp

<figure class="about-shot about-shot--{{ $ratio }}">
    @if ($src)
        <img src="{{ $src }}" alt="{{ $label }}" loading="lazy" class="about-shot__img">
    @else
        <div class="about-shot__frame" role="img" aria-label="{{ $label }}">
            <div class="about-shot__bar">
                <i></i><i></i><i></i>
            </div>
            <div class="about-shot__body">
                <div class="about-shot__side">
                    <span></span><span></span><span></span><span></span>
                    <span></span><span></span><span></span><span></span>
                </div>
                <div class="about-shot__main">
                    <div class="about-shot__title"></div>
                    <div class="about-shot__tiles">
                        <div></div><div></div><div></div>
                    </div>
                    {{-- Строк намеренно с запасом: лишние обрезаются рамкой,
                         и заглушка выглядит как настоящий экран, не влезший целиком. --}}
                    <div class="about-shot__rows">
                        <div class="about-shot__line"></div>
                        <div class="about-shot__line about-shot__line--mid"></div>
                        <div class="about-shot__line about-shot__line--short"></div>
                        <div class="about-shot__line"></div>
                        <div class="about-shot__line about-shot__line--short"></div>
                        <div class="about-shot__line about-shot__line--mid"></div>
                        <div class="about-shot__line"></div>
                        <div class="about-shot__line about-shot__line--short"></div>
                        <div class="about-shot__line about-shot__line--mid"></div>
                        <div class="about-shot__line"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <figcaption class="about-shot__caption">{{ $label }}</figcaption>
</figure>
