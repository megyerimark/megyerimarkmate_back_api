@extends('emails.layouts.aurora')
@section('title','Emlékeztető: konzultáció')
@section('preheader', ucfirst($dayWord).' '.$start->format('H:i').'-kor beszélünk – '.$start->translatedFormat('Y. F j., l').'.')
@section('eyebrow','KONZULTÁCIÓ / EMLÉKEZTETŐ')
@section('headline'){{ in_array($dayWord, ['ma', 'holnap'], true) ? ucfirst($dayWord).' beszélünk.' : 'Hamarosan beszélünk.' }}@endsection
@section('content')
<x-mail.text>Kedves {{ $booking->customer_name }}! Csak emlékeztetlek a lefoglalt konzultációs időpontunkra – várom a beszélgetést.</x-mail.text>
@include('emails.partials.date-card')
<x-mail.details title="HOGY KÉSZÜLJ?">
<x-mail.row label="1." label-width="28px" :first="true"><span style="font-weight:normal;">Gondold át röviden, mit szeretnél elérni a weboldallal, és kik az ügyfeleid.</span></x-mail.row>
<x-mail.row label="2." label-width="28px"><span style="font-weight:normal;">Ha van olyan oldal, ami tetszik (vagy ami nem), hozd a linkjét.</span></x-mail.row>
<x-mail.row label="3." label-width="28px"><span style="font-weight:normal;">Ha meglévő oldalad, logód vagy szöveged van, azt is nyugodtan megmutathatod.</span></x-mail.row>
</x-mail.details>
@if ($meetingUrl)
<x-mail.button :href="$meetingUrl">Csatlakozás a videóhíváshoz &rarr;</x-mail.button>
<x-mail.note>Link: <a href="{{ $meetingUrl }}" style="color:#8fb0ff;">{{ $meetingUrl }}</a></x-mail.note>
@else
<x-mail.button :href="$googleUrl">Hozzáadás a Google Naptárhoz &rarr;</x-mail.button>
@endif
<x-mail.button :href="$manageUrl" variant="secondary">Időpont módosítása vagy lemondása</x-mail.button>
<x-mail.note>Ha közbejött valami, a fenti személyes linkkel (legkésőbb 2 órával a kezdés előtt) átfoglalhatod vagy lemondhatod, vagy egyszerűen válaszolj erre a levélre. Foglalási azonosító: #{{ $booking->id }}.</x-mail.note>
@endsection
