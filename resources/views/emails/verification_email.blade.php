@component('mail::message')
    <h2>{{ $maildata['title'] }}</h2>

    {{ $maildata['message_body'] }}

    @component('mail::button', ['url' => $maildata['link']])
        Verify Email
    @endcomponent
    </table>
    Thanks,
    {{ config('app.name') }}
@endcomponent
