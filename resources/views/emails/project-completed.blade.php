@extends('emails.layouts.aurora')
@php($M = \App\Support\MailText::class)
@section('title', mt('A projekted elkészült'))
@section('preheader', mt('Elkészült a #:id számú projekted.', ['id' => $quote->id]))
@section('eyebrow', mt('PROJEKT LEZÁRVA'))
@section('headline', mt('Elkészült a projekted!'))
@section('content')
<x-mail.text>{{ mt('Kedves :name! Örömmel jelzem, hogy a #:id számú projekt munkái elkészültek.', ['name' => $quote->customer_name, 'id' => $quote->id]) }}</x-mail.text>
<x-mail.details :title="mt('ELSZÁMOLÁS')">
<x-mail.row :label="mt('Vállalási díj')" :first="true">{{ $M::money($quote->quoted_price_huf) }}</x-mail.row>
@if ($quote->deposit_paid_at && $quote->depositAmount())
<x-mail.row :label="mt('Befizetett előleg')">− {{ $M::money($quote->depositAmount()) }}</x-mail.row>
@endif
@if ($quote->final_amount_huf > 0)
<x-mail.row :label="mt('Fennmaradó összeg')"><span style="font-size:22px;">{{ $M::money($quote->final_amount_huf) }}</span></x-mail.row>
@else
<x-mail.row :label="mt('Állapot')"><span style="color:#5ee0b0;">{{ mt('A díj teljes egészében rendezve.') }}</span></x-mail.row>
@endif
</x-mail.details>
@if ($quote->final_amount_huf > 0)
<x-mail.text>{{ \App\Support\Stripe::enabled() ? mt('A forráskódot és az átadási csomagot a fennmaradó összeg beérkezése után adom át. Fizetni a projektoldalon bankkártyával (biztonságos Stripe-fizetés) vagy átutalással tudsz.') : mt('A forráskódot és az átadási csomagot a fennmaradó összeg beérkezése után adom át. Fizetni a projektoldalon átutalással tudsz.') }}</x-mail.text>
@else
<x-mail.text>{{ mt('Hamarosan átadom a forráskódot és az átadási csomagot; erről külön e-mailt kapsz.') }}</x-mail.text>
@endif
<x-mail.button :href="$portalUrl">{{ $quote->final_amount_huf > 0 ? mt('Fennmaradó összeg rendezése') : mt('Projekt megnyitása') }} &rarr;</x-mail.button>
<x-mail.note>{{ mt('A link személyes, kérlek, ne továbbítsd. Kérdés esetén a projektoldalon üzenetben is elérsz.') }}</x-mail.note>
@endsection
