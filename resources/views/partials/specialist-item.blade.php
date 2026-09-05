<a href="{{ route('tutors.show', $specialist) }}" class="specialist-list-item w-inline-block">
    <div class="specialist-list-item-group">
        <div class="list-item-userpic-wrap">
            <img src="{{ $specialist->avatarUrl }}" loading="lazy" width="112" height="112" alt=""
                class="list-item-userpic">
            @if(($specialist->reviews_count ?? 0) > 0)
                <div class="list-item-rating" title="Средняя оценка по {{ plural_ru($specialist->reviews_count, 'отзыву', 'отзывам', 'отзывам') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.95 6.1 6.7.9-4.9 4.7 1.2 6.7L12 17.7l-5.95 3.2 1.2-6.7-4.9-4.7 6.7-.9L12 2.5z"/></svg>
                    <span>{{ number_format($specialist->rating_avg, 1, ',', '') }}</span>
                </div>
            @endif
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
            </div>
            <div class="list-item-subject-grade">
                <div class="p24">{{ $specialist->subjectsList }}</div>
                <div class="list-item-grade-price">
                    <div class="p18">{{ $specialist->displayGrade }}</div>
                    @if(($specialist->min_price ?? 0) > 0)
                        <div class="p18 list-item-price">от {{ number_format($specialist->min_price, 0, ',', ' ') }} ₽ за урок</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</a>