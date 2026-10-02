<?php

namespace App\Http\Controllers;

use App\Models\Announcement;

/** «Новости Serdal» на сайте: новости из админки с отметкой «На сайте» (is_public), для всех без входа. */
class PublicNewsController extends Controller
{
    private const PER_PAGE = 12;

    public function index()
    {
        return view('news.index', [
            'news' => Announcement::onSite()->latest('published_at')->latest('id')->paginate(self::PER_PAGE),
        ]);
    }

    public function show(string $slug)
    {
        $item = Announcement::onSite()->where('slug', $slug)->firstOrFail();

        return view('news.show', [
            'item' => $item,
            'more' => Announcement::onSite()->whereKeyNot($item->id)->latest('published_at')->limit(3)->get(),
        ]);
    }
}
