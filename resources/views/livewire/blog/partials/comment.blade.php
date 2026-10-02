{{-- Один комментарий или ответ: автор, когда, текст, действия (ответить, изменить, удалить, пожаловаться, запретить писать). --}}
@php
    $author = $comment->user;
    $mine = $user && $comment->user_id === $user->id;
    $isPostAuthor = $author && $post->author_id === $author->id;
    $role = $author ? match ($author->role) { \App\Models\User::ROLE_TUTOR => 'учитель', \App\Models\User::ROLE_STUDENT => 'ученик', \App\Models\User::ROLE_ADMIN => 'команда Serdal', default => null } : null;
    $canEdit = $service->canEdit($user, $comment);
    $canDelete = $service->canDelete($user, $comment, $post);
    $reportedByMe = in_array($comment->id, $reported, true);
    $canReport = $user && ! $mine && ! $comment->isDeleted() && ! $reportedByMe;
    $canBan = $moderator && $author && ! $mine && $author->role !== \App\Models\User::ROLE_ADMIN;
    $isBanned = $author && in_array($author->id, $banned, true);
@endphp
<article id="comment-{{ $comment->id }}" @class(['blog-comment', 'blog-comment--reply' => $isReply]) wire:key="c-{{ $comment->id }}">
    @if($comment->isDeleted())
        <div class="blog-comment-pic blog-comment-pic--empty" aria-hidden="true"></div>
        <div class="blog-comment-body">
            <p class="blog-comment-deleted">{{ $comment->deleted_by === \App\Models\BlogComment::DELETED_BY_MODERATOR ? 'Комментарий удален модератором' : 'Комментарий удален автором' }}</p>
        </div>
    @else
        @if($author)
            @include('partials.userpic', ['user' => $author, 'class' => 'blog-comment-pic'])
        @else
            <div class="blog-comment-pic blog-comment-pic--empty" aria-hidden="true"></div>
        @endif
        <div class="blog-comment-body">
            <div class="blog-comment-meta">
                <span class="blog-comment-name">{{ $author?->name ?? 'Удаленный пользователь' }}</span>
                @if($isPostAuthor)<span class="blog-comment-badge">Автор статьи</span>@elseif($role)<span class="blog-comment-role">{{ $role }}</span>@endif
                @if($isBanned)<span class="blog-comment-role">не может писать</span>@endif
                <a href="#comment-{{ $comment->id }}" class="blog-comment-time">{{ \App\Support\HumanDate::at($comment->created_at) }}</a>
                @if($comment->edited_at)<span class="blog-comment-time">· изменено</span>@endif
                @if($canEdit || $canDelete || $canReport || $canBan)
                    <div class="blog-comment-more" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                        <button type="button" class="blog-comment-more-toggle" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu" aria-label="Действия с комментарием">
                            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M5 12h.01M12 12h.01M19 12h.01"/></svg>
                        </button>
                        <div class="blog-comment-menu" x-show="open" x-cloak x-on:click="open = false" role="menu">
                            @if($canEdit)<button type="button" role="menuitem" wire:click="startEdit({{ $comment->id }})">Изменить</button>@endif
                            @if($canDelete)<button type="button" role="menuitem" wire:click="askDelete({{ $comment->id }})">Удалить</button>@endif
                            @if($canReport)<button type="button" role="menuitem" wire:click="startReport({{ $comment->id }})">Пожаловаться</button>@endif
                            @if($canBan)
                                @if($isBanned)
                                    <button type="button" role="menuitem" wire:click="unban({{ $author->id }})">Разрешить писать</button>
                                @else
                                    <button type="button" role="menuitem" wire:click="ban({{ $author->id }})" wire:confirm="{{ $user->role === \App\Models\User::ROLE_ADMIN ? 'Запретить этому человеку комментировать во всем блоге?' : 'Запретить этому человеку комментировать ваши статьи?' }}">Запретить писать</button>
                                @endif
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            @if($editing === $comment->id)
                <div class="blog-comment-inline">
                    @include('livewire.blog.partials.field', ['model' => 'editBody', 'submit' => 'saveEdit', 'rows' => 3, 'label' => 'Изменить комментарий'])
                    @error('editBody')<span class="blog-comment-error">{{ $message }}</span>@enderror
                    <div class="blog-comment-form-actions">
                        <button type="button" class="blog-link-button" wire:click="cancelAll">Отмена</button>
                        <button type="button" class="blog-button" wire:click="saveEdit">Сохранить</button>
                    </div>
                </div>
            @else
                <div class="blog-comment-text">
                    @if($comment->replyTo && $isReply)<span class="blog-comment-mention">{{ $comment->replyTo->name }},</span> @endif{!! nl2br(str_replace('rel="noopener noreferrer"', 'rel="nofollow ugc noopener noreferrer"', (string) \App\Support\RichText::linkify((string) $comment->body))) !!}
                </div>
            @endif

            @if($reportedByMe)
                <div class="blog-comment-reported" role="status">
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg>
                    <span><b>Жалоба отправлена.</b> Команда Serdal проверит комментарий и, если он нарушает правила, удалит его.</span>
                </div>
            @endif

            @if($deleting === $comment->id)
                <div class="blog-comment-confirm">
                    <span>Удалить комментарий? Вернуть его будет нельзя.</span>
                    <button type="button" class="blog-link-button" wire:click="cancelAll">Отмена</button>
                    <button type="button" class="blog-button blog-button--dark" wire:click="delete">Удалить</button>
                </div>
            @elseif($reporting === $comment->id)
                <div class="blog-comment-inline">
                    <input type="text" wire:model="reportReason" maxlength="500" placeholder="Что не так? Необязательно" aria-label="Причина жалобы">
                    <div class="blog-comment-form-actions">
                        <button type="button" class="blog-link-button" wire:click="cancelAll">Отмена</button>
                        <button type="button" class="blog-button" wire:click="sendReport">Пожаловаться</button>
                    </div>
                </div>
            @elseif($editing !== $comment->id && $why === null)
                <div class="blog-comment-actions">
                    <button type="button" class="blog-comment-reply" wire:click="startReply({{ $comment->id }})">Ответить</button>
                </div>
            @endif

            @if($replyTo === $comment->id)
                <div class="blog-comment-inline">
                    @include('livewire.blog.partials.field', ['model' => 'replyBody', 'submit' => 'sendReply', 'rows' => 2, 'label' => 'Ответ', 'placeholder' => 'Ответ для ' . ($author?->name ?? ''), 'autofocus' => true])
                    @error('replyBody')<span class="blog-comment-error">{{ $message }}</span>@enderror
                    <div class="blog-comment-form-actions">
                        <button type="button" class="blog-link-button" wire:click="cancelAll">Отмена</button>
                        <button type="button" class="blog-button" wire:click="sendReply">Ответить</button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</article>
