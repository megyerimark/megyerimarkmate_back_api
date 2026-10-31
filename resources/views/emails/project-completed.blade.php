@extends('emails.layouts.aurora')
@section('title','A projekted elkészült')
@section('eyebrow','PROJEKT LEZÁRVA')
@section('headline','Elkészült a projekted!')
@section('content')
<p style="color:#c7d0ec;line-height:1.8;">Kedves {{ $quote->customer_name }}! Örömmel jelzem, hogy a <strong style="color:white;">#{{ $quote->id }}</strong> számú projekt munkái elkészültek.</p>
<table role="presentation" width="100%" style="border:1px solid #24345e;border-radius:12px;background:#0f1830;"><tr><td style="padding:22px;color:#c7d0ec;line-height:1.9;">
Vállalási díj: <strong style="color:#fff;">{{ number_format($quote->quoted_price_huf, 0, ',', ' ') }} Ft</strong><br>
@if ($quote->deposit_paid_at && $quote->depositAmount())
Befizetett előleg: <strong style="color:#fff;">− {{ number_format($quote->depositAmount(), 0, ',', ' ') }} Ft</strong><br>
@endif
@if ($quote->final_amount_huf > 0)
<span style="color:#adb9e1;font-size:11px;letter-spacing:1.3px;">FENNMARADÓ ÖSSZEG</span><br><strong style="font-size:29px;color:#fff;">{{ number_format($quote->final_amount_huf, 0, ',', ' ') }} Ft</strong>
@else
<strong style="color:#fff;">A díj teljes egészében rendezve van.</strong>
@endif
</td></tr></table>
@if ($quote->final_amount_huf > 0)
<p style="color:#cbd4f0;line-height:1.8;">A forráskódot és az átadási csomagot a fennmaradó összeg beérkezése után adom át. Fizetni a projektoldalon @if (\App\Support\Stripe::enabled())bankkártyával (biztonságos Stripe-fizetés) vagy @endif átutalással tudsz.</p>
@else
<p style="color:#cbd4f0;line-height:1.8;">Hamarosan átadom a forráskódot és az átadási csomagot; erről külön e-mailt kapsz.</p>
@endif
<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="background:#4263ff;border-radius:10px;"><a style="display:inline-block;padding:16px 25px;color:white;text-decoration:none;font-weight:bold;font-size:14px;" href="{{ $portalUrl }}">{{ $quote->final_amount_huf > 0 ? 'Fennmaradó összeg rendezése' : 'Projekt megnyitása' }} &rarr;</a></td></tr></table>
<p style="color:#9faece;font-size:12px;line-height:1.75;">A link személyes, kérlek, ne továbbítsd. Kérdés esetén a projektoldalon üzenetben is elérsz.</p>
@endsection
