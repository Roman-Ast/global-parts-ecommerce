@component('mail::message')
# Здравствуйте!

@if (! empty($greeting))
# {{ $greeting }}
@endif

@foreach ($introLines as $line)
{{ $line }}

@endforeach

@isset($actionText)
@component('mail::button', ['url' => $actionUrl])
{{ $actionText }}
@endcomponent
@endisset

@foreach ($outroLines as $line)
{{ $line }}

@endforeach

@if (! empty($salutation))
{{ $salutation }}
@else
С уважением,<br>
**{{ config('app.name') }}**
@endif

@isset($actionText)
@component('mail::subcopy')
Если кнопка "{{ $actionText }}" не работает, скопируйте и вставьте ссылку в браузер: [{{ $displayableActionUrl }}]({{ $actionUrl }})
@endcomponent
@endisset
@endcomponent