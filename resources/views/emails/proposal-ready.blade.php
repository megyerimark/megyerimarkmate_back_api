@extends('emails.layouts.aurora')
@section('title','Elkészült az egyedi ajánlatod')
@section('preheader','Elkészült a #'.$quote->id.' számú megkeresésedre szóló személyre szabott ajánlat.')
@section('eyebrow','02 / SZEMÉLYRE SZABOTT AJÁNLAT')
@section('headline','Elkészült az ajánlatod.')
@section('content')
<x-mail.text>Kedves {{ $quote->customer_name }}! A #{{ $quote->id }} számú megkeresésed alapján összeállítottam a javaslatomat.</x-mail.text>
<x-mail.details title="AZ AJÁNLAT">
@if ($quote->promo_percent && $quote->list_price_huf)
<x-mail.row label="Akció előtti ár" :first="true"><span style="color:#aab4ca;text-decoration:line-through;font-weight:normal;">{{ number_format($quote->list_price_huf, 0, ',', ' ') }} Ft</span></x-mail.row>
<x-mail.row label="Kedvezmény"><span style="color:#a9c0ff;">−{{ $quote->promo_percent }}%@if($quote->promo_title) ({{ $quote->promo_title }})@endif</span><br><span style="color:#8c98b4;font-size:12px;font-weight:normal;">az ajánlatkérés beküldésekor rögzítve</span></x-mail.row>
@endif
<x-mail.row label="Ajánlati összeg" :first="! ($quote->promo_percent && $quote->list_price_huf)"><span style="font-size:22px;">{{ number_format($quote->quoted_price_huf, 0, ',', ' ') }} Ft</span></x-mail.row>
@if ($quote->depositAmount())
<x-mail.row label="Előleg ({{ $quote->deposit_percent }}%)">{{ number_format($quote->depositAmount(), 0, ',', ' ') }} Ft<br><span style="color:#8c98b4;font-size:12px;font-weight:normal;">A munka megkezdésének feltétele. Az elfogadás után {{ \App\Support\Stripe::enabled() ? 'azonnal bankkártyával (biztonságos Stripe-fizetés) vagy átutalással' : 'átutalással (díjbekérő alapján)' }} fizethető; a végszámlából levonásra kerül.</span></x-mail.row>
@endif
@if ($quote->proposal_valid_until)
<x-mail.row label="Érvényes">{{ $quote->proposal_valid_until->format('Y. m. d.') }} napjáig</x-mail.row>
@endif
</x-mail.details>
@if ($quote->proposal_summary)
<p style="margin:0 0 16px;color:#c7d0ec;font-size:15px;line-height:1.75;white-space:pre-line;word-break:break-word;">{{ $quote->proposal_summary }}</p>
@endif
<x-mail.button :href="$portalUrl">Ajánlat megtekintése és elfogadása{{ $quote->depositAmount() ? ' + előleg' : '' }} &rarr;</x-mail.button>
<x-mail.note>A link személyes, ezért kérlek, ne továbbítsd. Az ajánlat elfogadása a privát projektfelületen lehetséges.</x-mail.note>
@endsection
