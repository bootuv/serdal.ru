@php $rating = max(0, min(5, (int) ($rating ?? 0))); @endphp
<div class="star-rating" role="img" aria-label="Оценка: {{ $rating }} из 5">
    @for($i = 1; $i <= 5; $i++)
        <svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
            fill="{{ $i <= $rating ? '#ffa41c' : 'var(--line-light)' }}" aria-hidden="true">
            <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
        </svg>
    @endfor
</div>
