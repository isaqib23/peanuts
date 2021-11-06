@component('mail::message')
    # {{ $maildata['title'] }}

    {{ $maildata['message_body'] }}
    {{ $maildata['message_body1'] }}

    Thanks,
    {{ config('app.name') }}
@endcomponent
