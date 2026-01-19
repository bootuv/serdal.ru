@php use App\Models\User; @endphp
<div class="review-item" data-role="student">
    <div class="review-item-user">
        <img src="{{ $review->user->avatarUrl }}" loading="lazy" alt="" class="list-item-userpic">
        <div class="list-item-name-bio">
            <div class="user-type">{{ $review->user->displayRole }}</div>
            <div class="p24-medium">{{ $review->user->name }}</div>
        </div>
    </div>
    <p class="p24">
        @if($review->teacher)
            <a href="{{ route('tutors.show', ['username' => $review->teacher->username]) }}"
                class="review-teacher-mention">{{ '@' . $review->teacher->username }}</a>
        @endif
        {{ $review->text }}
    </p>
</div>