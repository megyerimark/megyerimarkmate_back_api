@extends('emails.layouts.aurora')
@section('title', $forClient ? 'Konzultáció visszaigazolása' : 'Új konzultációfoglalás')
@section('preheader', ($forClient ? 'Lefoglaltad az időpontot: ' : 'Új foglalás: ').$start->translatedFormat('Y. F j., l').' '.$start->format('H:i'))
@section('eyebrow', $forClient ? 'KONZULTÁCIÓ / VISSZAIGAZOLÁS' : 'ADMIN / ÚJ FOGLALÁS')
@section('headline'){{ $forClient ? 'Az időpontod le van foglalva.' : 'Új konzultációfoglalás érkezett.' }}@endsection
@section('content')
<x-mail.text>{{ $forClient ? 'Kedves '.$booking->customer_name.'! Köszönöm a foglalásod, örülök, hogy beszélhetünk az elképzelésedről.' : $booking->customer_name.' időpontot foglalt konzultációra.' }}</x-mail.text>

@include('emails.partials.date-card')

<x-mail.details :title="$forClient ? 'A FOGLALÁS ADATAI' : 'AZ ÜGYFÉL ADATAI'">
<x-mail.row label="Név" :first="true">{{ $booking->customer_name }}</x-mail.row>
@unless ($forClient)
<x-mail.row label="E-mail"><a href="mailto:{{ $booking->email }}" style="color:#8fb0ff;text-decoration:none;">{{ $booking->email }}</a></x-mail.row>
<x-mail.row label="Telefon">@if ($booking->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->phone) }}" style="color:#8fb0ff;text-decoration:none;">{{ $booking->phone }}</a>@else<span style="color:#8c98b4;font-weight:normal;">Nincs megadva</span>@endif</x-mail.row>
@endunless
<x-mail.row label="Foglalási azonosító">#{{ $booking->id }}</x-mail.row>
<x-mail.row label="Formája">Online vagy telefonon – egyeztetjük</x-mail.row>
@if ($booking->notes)
<x-mail.row label="{{ $forClient ? 'Amit írtál' : 'Megjegyzés' }}"><span style="font-weight:normal;white-space:pre-line;">{{ $booking->notes }}</span></x-mail.row>
@endif
</x-mail.details>

<x-mail.button :href="$googleUrl">Hozzáadás a Google Naptárhoz &rarr;</x-mail.button>
<x-mail.note>A levélhez naptárfájlt (.ics) is csatoltam – Apple Naptárban és Outlookban egy kattintással felveheted.</x-mail.note>

@if ($forClient)
<x-mail.details title="MI TÖRTÉNIK EZUTÁN?">
<x-mail.row label="1." label-width="28px" :first="true"><span style="font-weight:normal;">A találkozó előtt e-mailben jelzem, online (videóhívás) vagy telefonon beszélünk.</span></x-mail.row>
<x-mail.row label="2." label-width="28px"><span style="font-weight:normal;">Ha van ötleted, mintaoldalad vagy anyagod, hozd nyugodtan – sokat segít.</span></x-mail.row>
<x-mail.row label="3." label-width="28px"><span style="font-weight:normal;">Módosítás vagy lemondás: egyszerűen válaszolj erre a levélre.</span></x-mail.row>
</x-mail.details>
@else
<x-mail.button href="mailto:{{ $booking->email }}" variant="secondary">Válasz az ügyfélnek</x-mail.button>
<x-mail.note>Erre a levélre válaszolva közvetlenül az ügyfélnek írsz. A foglalás az adminban, a Konzultációk menüben is látható.</x-mail.note>
@endif
@endsection
