@extends('emails.layouts.aurora')
@php($M = \App\Support\MailText::class)
@section('title', mt('Ajánlat elfogadva'))
@section('preheader', mt('Visszaigazolás: a #:id számú ajánlatot elfogadtad.', ['id' => $quote->id]))
@section('eyebrow', mt('VISSZAIGAZOLÁS'))
@section('headline', mt('Köszönöm, megkaptam az elfogadást!'))
@section('content')
<x-mail.text>{{ mt('Kedves :name! Ezzel az e-maillel visszaigazolom, hogy a #:id számú ajánlatot elfogadtad. Az elfogadással a szerződés az ajánlatban és az Általános Szerződési Feltételekben (:terms) foglaltak szerint létrejött.', ['name' => $quote->customer_name, 'id' => $quote->id, 'terms' => $quote->terms_version]) }}</x-mail.text>
<x-mail.details :title="mt('A MEGÁLLAPODÁS')">
<x-mail.row :label="mt('Vállalási díj')" :first="true"><span style="font-size:20px;">{{ $M::money($quote->quoted_price_huf) }}</span></x-mail.row>
@if ($quote->promo_percent)
<x-mail.row :label="mt('Akciós kedvezmény')">{{ mt('−:pct% – érvényesítve', ['pct' => $quote->promo_percent]) }} @if($quote->list_price_huf)<br><span style="color:#8c98b4;font-size:12px;font-weight:normal;">{{ mt('Akció előtti ár: :price', ['price' => $M::money($quote->list_price_huf)]) }}</span>@endif</x-mail.row>
@endif
@if ($quote->depositAmount())
<x-mail.row :label="mt('Előleg (:pct%)', ['pct' => $quote->deposit_percent])">{{ $M::money($quote->depositAmount()) }}<br><span style="color:#8c98b4;font-size:12px;font-weight:normal;">{{ mt('A munka az előleg beérkezése után indul.') }}</span></x-mail.row>
@endif
@if ($quote->proposal_accepted_at)
<x-mail.row :label="mt('Elfogadás időpontja')">{{ $M::dateTime($quote->proposal_accepted_at) }}</x-mail.row>
@endif
</x-mail.details>
<x-mail.button :href="$portalUrl">{{ mt('Projekt megnyitása') }} &rarr;</x-mail.button>
<x-mail.note>{{ mt('Az ajánlat teljes szövege és az ÁSZF a projektoldalon bármikor elérhető. A link személyes, ne add tovább.') }}</x-mail.note>
@endsection
