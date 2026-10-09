<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RoomController;

Route::get('/', [IndexController::class, 'index']);

// Файлы для поисковых систем и ИИ-краулеров
Route::get('/robots.txt', [\App\Http\Controllers\SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [\App\Http\Controllers\SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/llms.txt', [\App\Http\Controllers\SeoController::class, 'llms'])->name('seo.llms');
Route::get('/llms-full.txt', [\App\Http\Controllers\SeoController::class, 'llmsFull'])->name('seo.llms-full');
Route::get('/indexnow.txt', [\App\Http\Controllers\SeoController::class, 'indexNowKey'])->name('seo.indexnow');

// Каталог репетиторов для поиска: по предмету, по направлению и их сочетанию
Route::get('/repetitory', [\App\Http\Controllers\TutorCatalogController::class, 'index'])->name('catalog.index');
Route::get('/repetitory/{subject}', [\App\Http\Controllers\TutorCatalogController::class, 'subject'])->name('catalog.subject');
Route::get('/repetitory/{subject}/{direct}', [\App\Http\Controllers\TutorCatalogController::class, 'combo'])->name('catalog.combo');
Route::get('/napravleniya/{direct}', [\App\Http\Controllers\TutorCatalogController::class, 'direct'])->name('catalog.direct');



// Блог: статьи про образование (админка «Блог»)
Route::get('/news', [\App\Http\Controllers\PublicNewsController::class, 'index'])->name('news.index');
Route::get('/news/{slug}', [\App\Http\Controllers\PublicNewsController::class, 'show'])->name('news.show');
Route::get('/blog', [\App\Http\Controllers\BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/tag/{slug}', [\App\Http\Controllers\BlogController::class, 'tag'])->name('blog.tag');
Route::get('/blog/author/{username}', [\App\Http\Controllers\BlogController::class, 'author'])->name('blog.author');
// Для поисковиков, агрегаторов и ИИ-агентов: лента RSS с полным текстом и статья в Markdown
Route::get('/blog/rss.xml', [\App\Http\Controllers\BlogController::class, 'rss'])->name('blog.rss');
Route::get('/blog/{slug}/og.jpg', [\App\Http\Controllers\BlogController::class, 'shareImage'])->name('blog.og');
Route::get('/blog/{slug}.md', [\App\Http\Controllers\BlogController::class, 'markdown'])->name('blog.markdown');
Route::get('/blog/{slug}', [\App\Http\Controllers\BlogController::class, 'show'])->name('blog.show');

// Help Center (база знаний) — публичные страницы
Route::get('/help', [\App\Http\Controllers\HelpController::class, 'index'])->name('help.index');
Route::get('/help/{audience}', [\App\Http\Controllers\HelpController::class, 'section'])->name('help.section');
Route::get('/help/{audience}/{category}', [\App\Http\Controllers\HelpController::class, 'category'])->name('help.category');
Route::get('/help/{audience}/{category}/{article}', [\App\Http\Controllers\HelpController::class, 'article'])->name('help.article');

Route::get('/about', [PageController::class, 'aboutPage'])->name('about');
Route::get('/reviews', [PageController::class, 'reviewsPage'])->name('reviews');
Route::get('/reviews/load-more', [PageController::class, 'loadMoreReviews'])->name('reviews.load-more');
Route::get('/privacy', [PageController::class, 'privacyPage'])->name('privacy');
Route::get('/terms', [PageController::class, 'termsPage'])->name('terms');
Route::get('/tariffs', [PageController::class, 'tariffsPage'])->name('tariffs');
Route::get('/offer', [PageController::class, 'offerPage'])->name('offer');

// Интернет-эквайринг ЮKassa: возврат с платёжной страницы и серверное уведомление
Route::get('/subscription/payment/{payment}/return', [\App\Http\Controllers\SubscriptionPaymentController::class, 'return'])
    ->name('subscription.payment.return');
Route::post('/payments/yookassa/callback', [\App\Http\Controllers\SubscriptionPaymentController::class, 'callback'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('subscription.payment.callback');

// Ссылки из писем рассылки (админка «Рассылки»): учёт открытий и переходов, отписка.
// POST отписки — без CSRF: «отписку в один клик» присылает почтовая программа, а не страница.
Route::get('/m/o/{token}', [\App\Http\Controllers\MailingTrackController::class, 'open'])->name('mailing.open');
Route::get('/m/c/{token}/{link}', [\App\Http\Controllers\MailingTrackController::class, 'click'])->whereNumber('link')->name('mailing.click');
Route::get('/m/u/{token}', [\App\Http\Controllers\MailingTrackController::class, 'unsubscribePage'])->name('mailing.unsubscribe');
Route::post('/m/u/{token}', [\App\Http\Controllers\MailingTrackController::class, 'unsubscribe'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Личная страница основателя: сбор на расходы. Только после входа под профилем, привязанным к основателю (админ или учитель)
Route::get('/founder', \App\Livewire\FounderPage::class)->middleware(['auth', \App\Http\Middleware\CheckUserActive::class])->name('founders.page');

// Вход для всех ролей и восстановление пароля (docs/design/BRAND.md, экраны без сайдбара).
// Старые адреса входа (/tutor/login и т. п.) ведут сюда через маршрут legacy.cabinet.
Route::get('/login', \App\Livewire\Auth\Login::class)->name('login');
Route::get('/forgot-password', \App\Livewire\Auth\ForgotPassword::class)->name('password.request');
Route::get('/reset-password/{token}', \App\Livewire\Auth\ResetPassword::class)->name('password.reset');
Route::post('/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

// Teacher choice page - choose between new LMS and old Greenlight
Route::get('/welcome', fn() => view('welcome-choice'))->name('teacher.choice');
Route::get('/application', \App\Livewire\BecomeTutorPage::class)->name('become-tutor');

// Партнёрская программа: ссылка-приглашение коллеги
Route::get('/r/{code}', [\App\Http\Controllers\ReferralController::class, 'invite'])
    ->where('code', '[A-Za-z0-9]{4,16}')
    ->name('referral.invite');

// Invitation Registration
Route::get('/register/invite', \App\Livewire\RegisterInvitedStudent::class)->name('student.invitation');

Route::middleware(['auth'])->group(function () {
    Route::get('/rooms/{room}/start', [RoomController::class, 'start'])->name('rooms.start');
    Route::get('/payments/{payment}/receipt', [\App\Http\Controllers\PaymentReceiptController::class, 'show'])->name('subscription.payment.receipt');
    Route::get('/recordings/{recording}/download', \App\Http\Controllers\RecordingDownloadController::class)->name('recordings.download');
    // Route::get('/rooms/{room}/join', [RoomController::class, 'join'])->name('rooms.join'); // Moved to public
    Route::get('/rooms/{room}/stop', [RoomController::class, 'stop'])->name('rooms.stop');

    // Session logout redirect - handles different roles after BBB session ends
    Route::get('/session/{session}/logout', \App\Http\Controllers\SessionLogoutController::class)->name('session.logout');

    // Google Calendar Integration
    Route::get('/google/calendar/connect', [\App\Http\Controllers\GoogleCalendarController::class, 'redirectToGoogle'])->name('google.calendar.connect');
    Route::get('/google/calendar/callback', [\App\Http\Controllers\GoogleCalendarController::class, 'handleGoogleCallback'])->name('google.calendar.callback');
    Route::get('/google/calendar/disconnect', [\App\Http\Controllers\GoogleCalendarController::class, 'disconnect'])->name('google.calendar.disconnect');
    Route::get('/google/calendar/sync', [\App\Http\Controllers\GoogleCalendarController::class, 'syncSchedule'])->name('google.calendar.sync');

    // Share review as social media story card
    Route::get('/reviews/{review}/share-card', \App\Http\Controllers\ReviewShareCardController::class)->name('reviews.share-card');

    // Push Notifications
    Route::post('/push-subscription', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push-subscription', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::get('/push-subscription/check', [\App\Http\Controllers\PushSubscriptionController::class, 'check'])->name('push.check');
    Route::delete('/push-subscription/cleanup', [\App\Http\Controllers\PushSubscriptionController::class, 'cleanup'])->name('push.cleanup');
});

// Кабинеты подключаем раньше /{username}, иначе /cabinet откроется как страница учителя
require __DIR__ . '/cabinet.php';

Route::get('/{username}', [PageController::class, 'tutorPage'])->name('tutors.show');

// Public Room Access
Route::get('/rooms/{room}/join', \App\Livewire\GuestJoinRoom::class)->name('rooms.join');
Route::get('/rooms/{room}/connect', [RoomController::class, 'connect'])->name('rooms.connect');
Route::get('/rooms/{room}/join-failed', [RoomController::class, 'joinFailed'])->name('rooms.join-failed');
Route::post('/rooms/{room}/join/guest', [RoomController::class, 'joinAsGuest'])->name('rooms.join.guest');

