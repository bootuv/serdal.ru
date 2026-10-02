{{-- Обсуждение под статьей (App\Livewire\Blog\Comments): форма сверху, ветки — новые сверху, ответы в ветке — по порядку. --}}
<section id="comments" class="blog-comments" aria-labelledby="blog-comments-title">
    <div class="blog-comments-head">
        <h2 id="blog-comments-title" class="blog-comments-title">Обсуждение @if($count)<span>{{ $count }}</span>@endif</h2>
        @if($moderator)
            <button type="button" class="blog-link-button" wire:click="toggleClosed">{{ $post->comments_closed ? 'Открыть обсуждение' : 'Закрыть обсуждение' }}</button>
        @endif
    </div>


    {{-- Новый комментарий --}}
    @if($why === null)
        <form class="blog-comment-form" wire:submit="send">
            @include('partials.userpic', ['user' => $user, 'class' => 'blog-comment-pic'])
            <div class="blog-comment-form-body">
                @include('livewire.blog.partials.field', ['model' => 'body', 'submit' => 'send', 'rows' => 3, 'label' => 'Комментарий', 'placeholder' => $post->comments_closed ? 'Обсуждение закрыто для всех, кроме вас' : 'Что вы думаете?'])
                @error('body')<span class="blog-comment-error">{{ $message }}</span>@enderror
                <div class="blog-comment-form-actions">
                    <span class="blog-comment-hint">Ссылки станут кликабельными. Ctrl + Enter — отправить</span>
                    <button type="submit" class="blog-button" wire:loading.attr="disabled" wire:target="send">Отправить</button>
                </div>
            </div>
        </form>
    @elseif($why === 'guest')
        <div class="blog-comments-guest">
            <span>Войдите, чтобы участвовать в обсуждении и ставить лайки</span>
            <a href="{{ $loginUrl }}" class="blog-button">Войти</a>
        </div>
    @elseif($why !== 'draft')
        <p class="blog-comments-note">{{ \App\Services\BlogCommentService::reason($why) }}</p>
    @endif

    {{-- Ветки --}}
    @if($roots->isEmpty())
        @if($why === null)<p class="blog-comments-empty">Пока никто не написал — начните обсуждение первым.</p>@endif
    @else
        <div class="blog-comment-list">
            @foreach($roots as $comment)
                <div class="blog-thread" wire:key="th-{{ $comment->id }}">
                    @include('livewire.blog.partials.comment', ['comment' => $comment, 'isReply' => false])
                    @if($comment->replies->isNotEmpty())
                        <div class="blog-replies">
                            @foreach($comment->replies as $reply)
                                @include('livewire.blog.partials.comment', ['comment' => $reply, 'isReply' => true])
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @if($more > 0)
            <button type="button" class="blog-more-button" wire:click="more">Показать еще {{ min($more, 20) }}</button>
        @endif
    @endif
</section>
