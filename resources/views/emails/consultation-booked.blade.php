@extends('emails.layouts.aurora')
@section('title', $forClient ? mt('Konzultáció visszaigazolása') : 'Új konzultációfoglalás')
@section('preheader', ($forClient ? mt('Lefoglaltad az időpontot:') : 'Új foglalás:').' '.\App\Support\MailText::longDate($start).' '.$start->format('H:i'))
@section('eyebrow', $forClient ? mt('KONZULTÁCIÓ / VISSZAIGAZOLÁS') : 'ADMIN / ÚJ FOGLALÁS')
@section('headline'){{ $changed ? ($forClient ? mt('Az új időpontod rögzítve.') : 'Átfoglaltak egy konzultációt.') : ($forClient ? mt('Az időpontod le van foglalva.') : 'Új konzultációfoglalás érkezett.') }}@endsection
@section('content')
<x-mail.text>{{ $changed ? ($forClient ? mt('Kedves :name! Visszaigazolom a módosított konzultációs időpontot – az előző időpont felszabadult.', ['name' => $booking->customer_name]) : $booking->customer_name.' másik időpontra foglalta át a konzultációt.') : ($forClient ? mt('Kedves :name! Köszönöm a foglalásod, örülök, hogy beszélhetünk az elképzelésedről.', ['name' => $booking->customer_name]) : $booking->customer_name.' időpontot foglalt konzultációra.') }}</x-mail.text>

@include('emails.partials.date-card')

<x-mail.details :title="$forClient ? mt('A FOGLALÁS ADATAI') : 'AZ ÜGYFÉL ADATAI'">
<x-mail.row :label="mt('Név')" :first="true">{{ $booking->customer_name }}</x-mail.row>
@unless ($forClient)
<x-mail.row label="E-mail"><a href="mailto:{{ $booking->email }}" style="color:#8fb0ff;text-decoration:none;">{{ $booking->email }}</a></x-mail.row>
<x-mail.row label="Telefon">@if ($booking->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->phone) }}" style="color:#8fb0ff;text-decoration:none;">{{ $booking->phone }}</a>@else<span style="color:#8c98b4;font-weight:normal;">Nincs megadva</span>@endif</x-mail.row>
<x-mail.row label="Nyelv">{{ $booking->lang === 'en' ? 'angol (az ügyfél angolul kapja a leveleket)' : 'magyar' }}</x-mail.row>
@endunless
<x-mail.row :label="mt('Foglalási azonosító')">#{{ $booking->id }}</x-mail.row>
<x-mail.row :label="mt('Formája')">@if ($meetingUrl){{ mt('Online videóhívás') }} <span style="color:#8c98b4;font-weight:normal;">{{ mt('(link lent)') }}</span>@else{{ mt('Online vagy telefonon – egyeztetjük') }}@endif</x-mail.row>
@if ($booking->notes)
<x-mail.row :label="$forClient ? mt('Amit írtál') : 'Megjegyzés'"><span style="font-weight:normal;white-space:pre-line;">{{ $booking->notes }}</span></x-mail.row>
@endif
</x-mail.details>

@if ($meetingUrl)
<x-mail.button :href="$meetingUrl">{{ mt('Csatlakozás a videóhíváshoz') }} &rarr;</x-mail.button>
<x-mail.note>{{ mt('A videóhívás linkje:') }} <a href="{{ $meetingUrl }}" style="color:#8fb0ff;">{{ $meetingUrl }}</a> – {{ mt('a kezdés előtt pár perccel nyugodtan csatlakozz.') }}</x-mail.note>
@endif
<x-mail.button :href="$googleUrl" :variant="$meetingUrl ? 'secondary' : 'primary'">{{ mt('Hozzáadás a Google Naptárhoz') }} &rarr;</x-mail.button>
<x-mail.note>{{ mt('A levélhez naptárfájlt (.ics) is csatoltam – Apple Naptárban és Outlookban egy kattintással felveheted.') }}</x-mail.note>

@if ($forClient)
<x-mail.details :title="mt('MI TÖRTÉNIK EZUTÁN?')">
<x-mail.row label="1." label-width="28px" :first="true"><span style="font-weight:normal;">{{ $meetingUrl ? mt('A megadott időben csatlakozz a videóhíváshoz a fenti linkkel.') : mt('A találkozó előtt e-mailben jelzem, online (videóhívás) vagy telefonon beszélünk.') }}</span></x-mail.row>
<x-mail.row label="2." label-width="28px"><span style="font-weight:normal;">{{ mt('Ha van ötleted, mintaoldalad vagy anyagod, hozd nyugodtan – sokat segít.') }}</span></x-mail.row>
<x-mail.row label="3." label-width="28px"><span style="font-weight:normal;">{{ mt('Módosítás vagy lemondás: a lenti személyes linkkel, legkésőbb 2 órával a kezdés előtt.') }}</span></x-mail.row>
</x-mail.details>
<x-mail.button :href="$manageUrl" variant="secondary">{{ mt('Időpont módosítása vagy lemondása') }}</x-mail.button>
<x-mail.note>{{ mt('Ez a link személyes – ne továbbítsd.') }}</x-mail.note>
@else
<x-mail.button href="mailto:{{ $booking->email }}" variant="secondary">Válasz az ügyfélnek</x-mail.button>
<x-mail.note>Erre a levélre válaszolva közvetlenül az ügyfélnek írsz. A foglalás az adminban, a Konzultációk menüben is látható.</x-mail.note>
@endif
@endsection
