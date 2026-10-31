@php($en = $subscriber->lang === 'en')
@extends('emails.layouts.aurora')
@section('title', $en ? 'Confirm subscription' : 'Feliratkozás megerősítése')
@section('eyebrow', $en ? 'NEWSLETTER / CONFIRMATION' : 'HÍRLEVÉL / MEGERŐSÍTÉS')
@section('headline'){{ $en ? 'One click and you are in.' : 'Még egy kattintás, és kész.' }}@endsection
@section('content')
<p style="color:#c7d0ec;line-height:1.8;">{{ $en
    ? 'Someone (hopefully you) subscribed this address to the Megyeri Márk Máté newsletter for discounts and news. Please confirm with the button below.'
    : 'Valaki (remélhetőleg te) feliratkoztatta ezt a címet a Megyeri Márk Máté hírlevelére, hogy elsőként értesüljön az akciókról és kedvezményekről. Kérlek, erősítsd meg az alábbi gombbal.' }}</p>
<p style="margin:26px 0;"><a href="{{ $confirmUrl }}" style="display:inline-block;padding:14px 26px;border-radius:12px;background:#4263ff;color:#ffffff;font-weight:bold;text-decoration:none;">{{ $en ? 'Confirm subscription' : 'Feliratkozás megerősítése' }}</a></p>
<p style="color:#97a7c9;font-size:12px;line-height:1.7;">{{ $en
    ? 'If you did not request this, simply ignore this email – you will not receive anything else. The link is valid for 7 days.'
    : 'Ha nem te kérted, egyszerűen hagyd figyelmen kívül ezt a levelet – nem kapsz többet. A link 7 napig érvényes.' }}</p>
@endsection
