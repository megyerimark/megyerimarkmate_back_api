@extends('emails.layouts.aurora')
@php($M = \App\Support\MailText::class)
@section('title', mt('Elkészült az egyedi ajánlatod'))
@section('preheader', mt('Elkészült a #:id számú megkeresésedre szóló személyre szabott ajánlat.', ['id' => $quote->id]))
@section('eyebrow', mt('02 / SZEMÉLYRE SZABOTT AJÁNLAT'))
@section('headline', mt('Elkészült az ajánlatod.'))
@section('content')
<x-mail.text>{{ mt('Kedves :name! A #:id számú megkeresésed alapján összeállítottam a javaslatomat.', ['name' => $quote->customer_name, 'id' => $quote->id]) }}</x-mail.text>
<x-mail.details :title="mt('AZ AJÁNLAT')">
@if ($quote->promo_percent && $quote->list_price_huf)
<x-mail.row :label="mt('Akció előtti ár')" :first="true"><span style="color:#aab4ca;text-decoration:line-through;font-weight:normal;">{{ $M::money($quote->list_price_huf) }}</span></x-mail.row>
<x-mail.row :label="mt('Kedvezmény')"><span style="color:#a9c0ff;">−{{ $quote->promo_percent }}%@if($quote->promo_title) ({{ $quote->promo_title }})@endif</span><br><span style="color:#8c98b4;font-size:12px;font-weight:normal;">{{ mt('az ajánlatkérés beküldésekor rögzítve') }}</span></x-mail.row>
@endif
<x-mail.row :label="mt('Ajánlati összeg')" :first="! ($quote->promo_percent && $quote->list_price_huf)"><span style="font-size:22px;">{{ $M::money($quote->quoted_price_huf) }}</span></x-mail.row>
@if ($quote->depositAmount())
<x-mail.row :label="mt('Előleg (:pct%)', ['pct' => $quote->deposit_percent])">{{ $M::money($quote->depositAmount()) }}<br><span style="color:#8c98b4;font-size:12px;font-weight:normal;">{{ \App\Support\Stripe::enabled() ? mt('A munka megkezdésének feltétele. Az elfogadás után azonnal bankkártyával (biztonságos Stripe-fizetés) vagy átutalással fizethető; a végszámlából levonásra kerül.') : mt('A munka megkezdésének feltétele. Az elfogadás után átutalással (díjbekérő alapján) fizethető; a végszámlából levonásra kerül.') }}</span></x-mail.row>
@endif
@if ($quote->proposal_valid_until)
<x-mail.row :label="mt('Érvényes')">{{ mt(':date napjáig', ['date' => $M::date($quote->proposal_valid_until)]) }}</x-mail.row>
@endif
</x-mail.details>
@if ($quote->proposal_summary)
<p style="margin:0 0 16px;color:#c7d0ec;font-size:15px;line-height:1.75;white-space:pre-line;word-break:break-word;">{{ $quote->proposal_summary }}</p>
@endif
<x-mail.button :href="$portalUrl">{{ $quote->depositAmount() ? mt('Ajánlat megtekintése és elfogadása + előleg') : mt('Ajánlat megtekintése és elfogadása') }} &rarr;</x-mail.button>
<x-mail.note>{{ mt('A link személyes, ezért kérlek, ne továbbítsd. Az ajánlat elfogadása a privát projektfelületen lehetséges.') }}</x-mail.note>
@endsection
