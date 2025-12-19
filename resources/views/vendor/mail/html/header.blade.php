@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if(isset($message) && method_exists($message, 'embed') && file_exists(public_path('images/Logo.svg')))
                <img src="{{ $message->embed(public_path('images/Logo.svg')) }}" class="logo" alt="Serdal Logo"
                    style="height: 48px; width: auto;">
            @else
                <img src="https://serdal.ru/images/Logo.svg" class="logo" alt="Serdal Logo"
                    style="height: 48px; width: auto;">
            @endif
        </a>
    </td>
</tr>