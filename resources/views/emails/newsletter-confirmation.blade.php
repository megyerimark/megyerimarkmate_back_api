@php($en = $subscriber->lang === 'en')
@extends('emails.layouts.aurora')
@if ($en) @section('en', '1') @endif
@section('title', $en ? 'Confirm subscription' : 'Feliratkozás megerősítése')
@section('preheader', $en ? 'Confirm your newsletter subscription.' : 'Erősítsd meg a hírlevél-feliratkozásodat.')
@section('eyebrow', $en ? 'NEWSLETTER / CONFIRMATION' : 'HÍRLEVÉL / MEGERŐSÍTÉS')
@section('headline'){{ $en ? 'One click and you are in.' : 'Még egy kattintás, és kész.' }}@endsection
@section('content')
<x-mail.text>{{ $en
    ? 'Someone (hopefully you) subscribed this address to the Megyeri Márk Máté newsletter for discounts and news. Please confirm with the button below.'
    : 'Valaki (remélhetőleg te) feliratkoztatta ezt a címet a Megyeri Márk Máté hírlevelére, hogy elsőként értesüljön az akciókról és kedvezményekről. Kérlek, erősítsd meg az alábbi gombbal.' }}</x-mail.text>
<x-mail.button :href="$confirmUrl">{{ $en ? 'Confirm subscription' : 'Feliratkozás megerősítése' }} &rarr;</x-mail.button>
<x-mail.note>{{ $en
    ? 'If you did not request this, simply ignore this email – you will not receive anything else. The link is valid for 7 days.'
    : 'Ha nem te kérted, egyszerűen hagyd figyelmen kívül ezt a levelet – nem kapsz többet. A link 7 napig érvényes.' }}</x-mail.note>
@endsection
