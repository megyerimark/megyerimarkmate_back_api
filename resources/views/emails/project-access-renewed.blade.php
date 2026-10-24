@extends('emails.layouts.aurora')
@section('title','Megújult a projekt-hozzáférésed')
@section('eyebrow','BIZTONSÁGOS ÜGYFÉLPORTÁL')
@section('headline','Új hozzáférési linked érkezett.')
@section('content')
<p style="color:#c7d0ec;line-height:1.8;">Kedves {{ $quote->customer_name }}! A #{{ $quote->id }} számú projekted hozzáférése megújult, a korábbi link már nem használható.</p>
<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="background:#6151ec;border-radius:10px;"><a href="{{ $portalUrl }}" style="display:inline-block;padding:16px 25px;color:#fff;text-decoration:none;font-size:14px;font-weight:bold;">Új projektlink megnyitása &rarr;</a></td></tr></table>
<p style="color:#a9b8db;font-size:12px;line-height:1.8;margin-top:24px;">A link hozzáférést ad az ajánlatodhoz, üzeneteidhez és dokumentumaidhoz. Ne oszd meg másokkal. Ha nem te kérted, kérlek, vedd fel velem a kapcsolatot.</p>
@endsection