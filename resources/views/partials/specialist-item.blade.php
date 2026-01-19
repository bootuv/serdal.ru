<a href="{{ route('tutors.show', $specialist) }}" class="specialist-list-item w-inline-block">
    <div class="specialist-list-item-group">
        <img src="{{ $specialist->avatarUrl }}" loading="lazy" width="112" height="112" alt=""
            class="list-item-userpic">
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
                <div class="p18">{{ $specialist->displayGrade }}</div>
            </div>
        </div>
    </div>
</a>