@extends('emails.layouts.aurora')
@section('title','Ajánlat elfogadva')
@section('preheader','Visszaigazolás: a #'.$quote->id.' számú ajánlatot elfogadtad.')
@section('eyebrow','VISSZAIGAZOLÁS')
@section('headline','Köszönöm, megkaptam az elfogadást!')
@section('content')
<x-mail.text>Kedves {{ $quote->customer_name }}! Ezzel az e-maillel visszaigazolom, hogy a #{{ $quote->id }} számú ajánlatot elfogadtad. Az elfogadással a szerződés az ajánlatban és az Általános Szerződési Feltételekben ({{ $quote->terms_version }}) foglaltak szerint létrejött.</x-mail.text>
<x-mail.details title="A MEGÁLLAPODÁS">
<x-mail.row label="Vállalási díj" :first="true"><span style="font-size:20px;">{{ number_format($quote->quoted_price_huf, 0, ',', ' ') }} Ft</span></x-mail.row>
@if ($quote->promo_percent)
<x-mail.row label="Akciós kedvezmény">−{{ $quote->promo_percent }}% – érvényesítve @if($quote->list_price_huf)<br><span style="color:#8c98b4;font-size:12px;font-weight:normal;">Akció előtti ár: {{ number_format($quote->list_price_huf, 0, ',', ' ') }} Ft</span>@endif</x-mail.row>
@endif
@if ($quote->depositAmount())
<x-mail.row label="Előleg ({{ $quote->deposit_percent }}%)">{{ number_format($quote->depositAmount(), 0, ',', ' ') }} Ft<br><span style="color:#8c98b4;font-size:12px;font-weight:normal;">A munka az előleg beérkezése után indul.</span></x-mail.row>
@endif
@if ($quote->proposal_accepted_at)
<x-mail.row label="Elfogadás időpontja">{{ $quote->proposal_accepted_at->copy()->timezone('Europe/Budapest')->format('Y. m. d. H:i') }}</x-mail.row>
@endif
</x-mail.details>
<x-mail.button :href="$portalUrl">Projekt megnyitása &rarr;</x-mail.button>
<x-mail.note>Az ajánlat teljes szövege és az ÁSZF a projektoldalon bármikor elérhető. A link személyes, ne add tovább.</x-mail.note>
@endsection
