{{-- RSS блога (/blog/rss.xml): последние статьи с полным текстом — для Яндекса, агрегаторов и ИИ-агентов --}}
@php
    $site = \App\Support\Seo::baseUrl();
    $xml = fn ($text) => htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    // Относительные ссылки и картинки в тексте — абсолютными: читалка RSS открывает их не на сайте
    $abs = fn ($html) => preg_replace('/(href|src)="\/(?!\/)/', '$1="' . $site . '/', (string) $html);
@endphp
{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title>Блог Serdal</title>
    <link>{{ \App\Support\Seo::url(route('blog.index', [], false)) }}</link>
    <description>Опыт, который не найти в учебниках: статьи репетиторов о подготовке к экзаменам, занятиях онлайн и учебе.</description>
    <language>ru</language>
    <atom:link href="{{ \App\Support\Seo::url(route('blog.rss', [], false)) }}" rel="self" type="application/rss+xml" />
    @if($posts->isNotEmpty())
    <lastBuildDate>{{ $posts->max('updated_at')->toRssString() }}</lastBuildDate>
    @endif
    @foreach($posts as $post)
    @php($url = \App\Support\Seo::url(route('blog.show', $post->slug, false)))
    <item>
        <title>{!! $xml($post->title) !!}</title>
        <link>{{ $url }}</link>
        <guid isPermaLink="true">{{ $url }}</guid>
        <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
        <dc:creator>{!! $xml($post->authorName()) !!}</dc:creator>
        <description>{!! $xml($post->description(300)) !!}</description>
        @foreach($post->tags as $tag)
        <category>{!! $xml($tag->name) !!}</category>
        @endforeach
        @if($post->cover_url)
        <enclosure url="{!! $xml($post->cover_url) !!}" type="image/{{ str_ends_with(strtolower(parse_url($post->cover_url, PHP_URL_PATH) ?? ''), '.png') ? 'png' : (str_ends_with(strtolower(parse_url($post->cover_url, PHP_URL_PATH) ?? ''), '.webp') ? 'webp' : 'jpeg') }}" length="0" />
        @endif
        <content:encoded><![CDATA[{!! str_replace(']]>', ']]]]><![CDATA[>', ($post->cover_url ? '<img src="' . $xml($post->cover_url) . '" alt="' . $xml($post->title) . '">' : '') . $abs(\App\Support\RichText::html($post->body))) !!}]]></content:encoded>
    </item>
    @endforeach
</channel>
</rss>
