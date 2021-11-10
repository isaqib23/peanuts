@component('mail::message')
    <h2>{{ $maildata['title'] }}</h2>

    <h3>{{ $maildata['message_body'] }}</h3>

    @component('mail::button', ['url' => $maildata['link']])
        Verify Email
    @endcomponent

    Thanks,
    {{ config('app.name') }}
@endcomponent
