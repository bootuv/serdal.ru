{{-- Частые вопросы: видимый текст и разметка FAQPage для поисковиков и ИИ-ассистентов. $faq = [['question' => ..., 'answer' => ...]] --}}
@if(!empty($faq))
  @push('jsonld')
    {!! \App\Support\Seo::jsonLd(\App\Support\Seo::faqPage($faq)) !!}
  @endpush
  <section class="faq">
    <h2 class="h2 faq__title">{{ $faqTitle ?? 'Частые вопросы' }}</h2>
    <div class="faq__list">
      @foreach($faq as $item)
        <details class="faq__item" @if($loop->first) open @endif>
          <summary class="faq__question p24">{{ $item['question'] }}</summary>
          <p class="faq__answer p18">{{ $item['answer'] }}</p>
        </details>
      @endforeach
    </div>
  </section>
@endif
