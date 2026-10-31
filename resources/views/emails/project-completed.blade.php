@extends('emails.layouts.aurora')
@section('title','A projekted elkészült')
@section('preheader','Elkészült a #'.$quote->id.' számú projekted.')
@section('eyebrow','PROJEKT LEZÁRVA')
@section('headline','Elkészült a projekted!')
@section('content')
<x-mail.text>Kedves {{ $quote->customer_name }}! Örömmel jelzem, hogy a #{{ $quote->id }} számú projekt munkái elkészültek.</x-mail.text>
<x-mail.details title="ELSZÁMOLÁS">
<x-mail.row label="Vállalási díj" :first="true">{{ number_format($quote->quoted_price_huf, 0, ',', ' ') }} Ft</x-mail.row>
@if ($quote->deposit_paid_at && $quote->depositAmount())
<x-mail.row label="Befizetett előleg">− {{ number_format($quote->depositAmount(), 0, ',', ' ') }} Ft</x-mail.row>
@endif
@if ($quote->final_amount_huf > 0)
<x-mail.row label="Fennmaradó összeg"><span style="font-size:22px;">{{ number_format($quote->final_amount_huf, 0, ',', ' ') }} Ft</span></x-mail.row>
@else
<x-mail.row label="Állapot"><span style="color:#5ee0b0;">A díj teljes egészében rendezve.</span></x-mail.row>
@endif
</x-mail.details>
@if ($quote->final_amount_huf > 0)
<x-mail.text>A forráskódot és az átadási csomagot a fennmaradó összeg beérkezése után adom át. Fizetni a projektoldalon {{ \App\Support\Stripe::enabled() ? 'bankkártyával (biztonságos Stripe-fizetés) vagy átutalással' : 'átutalással' }} tudsz.</x-mail.text>
@else
<x-mail.text>Hamarosan átadom a forráskódot és az átadási csomagot; erről külön e-mailt kapsz.</x-mail.text>
@endif
<x-mail.button :href="$portalUrl">{{ $quote->final_amount_huf > 0 ? 'Fennmaradó összeg rendezése' : 'Projekt megnyitása' }} &rarr;</x-mail.button>
<x-mail.note>A link személyes, kérlek, ne továbbítsd. Kérdés esetén a projektoldalon üzenetben is elérsz.</x-mail.note>
@endsection
