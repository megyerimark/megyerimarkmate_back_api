@extends('emails.layouts.aurora')
@section('title', mt('Emlékeztető: konzultáció'))
@section('preheader', mt(':day :time-kor beszélünk – :date.', ['day' => \App\Support\MailText::isEnglish() ? $dayWord : ucfirst($dayWord), 'time' => $start->format('H:i'), 'date' => \App\Support\MailText::longDate($start)]))
@section('eyebrow', mt('KONZULTÁCIÓ / EMLÉKEZTETŐ'))
@section('headline'){{ in_array($dayWord, [mt('ma'), mt('holnap')], true) ? mt(':day beszélünk.', ['day' => \App\Support\MailText::isEnglish() ? $dayWord : ucfirst($dayWord)]) : mt('Hamarosan beszélünk.') }}@endsection
@section('content')
<x-mail.text>{{ mt('Kedves :name! Csak emlékeztetlek a lefoglalt konzultációs időpontunkra – várom a beszélgetést.', ['name' => $booking->customer_name]) }}</x-mail.text>
@include('emails.partials.date-card')
<x-mail.details :title="mt('HOGY KÉSZÜLJ?')">
<x-mail.row label="1." label-width="28px" :first="true"><span style="font-weight:normal;">{{ mt('Gondold át röviden, mit szeretnél elérni a weboldallal, és kik az ügyfeleid.') }}</span></x-mail.row>
<x-mail.row label="2." label-width="28px"><span style="font-weight:normal;">{{ mt('Ha van olyan oldal, ami tetszik (vagy ami nem), hozd a linkjét.') }}</span></x-mail.row>
<x-mail.row label="3." label-width="28px"><span style="font-weight:normal;">{{ mt('Ha meglévő oldalad, logód vagy szöveged van, azt is nyugodtan megmutathatod.') }}</span></x-mail.row>
</x-mail.details>
@if ($meetingUrl)
<x-mail.button :href="$meetingUrl">{{ mt('Csatlakozás a videóhíváshoz') }} &rarr;</x-mail.button>
<x-mail.note>Link: <a href="{{ $meetingUrl }}" style="color:#8fb0ff;">{{ $meetingUrl }}</a></x-mail.note>
@else
<x-mail.button :href="$googleUrl">{{ mt('Hozzáadás a Google Naptárhoz') }} &rarr;</x-mail.button>
@endif
<x-mail.button :href="$manageUrl" variant="secondary">{{ mt('Időpont módosítása vagy lemondása') }}</x-mail.button>
<x-mail.note>{{ mt('Ha közbejött valami, a fenti személyes linkkel (legkésőbb 2 órával a kezdés előtt) átfoglalhatod vagy lemondhatod, vagy egyszerűen válaszolj erre a levélre. Foglalási azonosító: #:id.', ['id' => $booking->id]) }}</x-mail.note>
@endsection
