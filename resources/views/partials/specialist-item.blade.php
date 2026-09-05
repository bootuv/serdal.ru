<a href="{{ route('tutors.show', $specialist) }}" class="specialist-list-item w-inline-block">
    <div class="specialist-list-item-group">
        <div class="list-item-userpic-wrap">
            <img src="{{ $specialist->avatarUrl }}" loading="lazy" width="128" height="128" alt=""
                class="list-item-userpic">
        </div>
        <div class="specialist-list-item-details">
            <div class="list-item-name-tags">
                <div class="p30">{{ $specialist->name }}</div>
                <div class="direction-tags-list">
                    @foreach($specialist->directs as $direct)
                        <div class="direction-tag">
                            <div class="p18">{{ $direct->name }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="list-item-meta p18">
                    @if(($specialist->reviews_count ?? 0) > 0)
                        <span class="list-item-meta-rating">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.95 6.1 6.7.9-4.9 4.7 1.2 6.7L12 17.7l-5.95 3.2 1.2-6.7-4.9-4.7 6.7-.9L12 2.5z"/></svg>
                            {{ number_format($specialist->rating_avg, 1, ',', '') }}
                        </span>
                        <span class="list-item-meta-dot" aria-hidden="true">·</span>
                        <span>{{ plural_ru($specialist->reviews_count, 'отзыв', 'отзыва', 'отзывов') }}</span>
                    @else
                        <span>Пока нет отзывов</span>
                    @endif
                </div>
            </div>
            <div class="list-item-subject-grade">
                @php $cheapest = $specialist->cheapestLesson($lessonFormats ?? []); @endphp
                @if($cheapest)
                    <div class="list-item-price @if($cheapest->isMonthly()) is-estimate @endif"
                         @if($cheapest->isMonthly()) title="{{ number_format($cheapest->price, 0, ',', ' ') }} ₽ в месяц, {{ plural_ru($cheapest->count_per_week, 'занятие', 'занятия', 'занятий') }} в неделю" @endif>
                        <span class="list-item-price-value">{{ $cheapest->isMonthly() ? '≈' : 'от' }} {{ number_format($cheapest->pricePerLesson(), 0, ',', ' ') }} ₽</span>
                        <span class="list-item-price-unit">за урок</span>
                    </div>
                @endif
                <div class="list-item-subject-grade-text">
                    <div class="p24">{{ $specialist->subjectsList }}</div>
                    <div class="p18">{{ $specialist->displayGrade }}</div>
                </div>
            </div>
        </div>
    </div>
</a>