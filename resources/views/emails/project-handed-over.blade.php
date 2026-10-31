@extends('emails.layouts.aurora')
@section('title','Átadtam a projektedet')
@section('eyebrow','KÓDÁTADÁS')
@section('headline','Minden a tiéd.')
@section('content')
<p style="color:#c7d0ec;line-height:1.8;">Kedves {{ $quote->customer_name }}! Köszönöm a fizetést – a <strong style="color:white;">#{{ $quote->id }}</strong> számú projekt forráskódját és átadási csomagját átadtam.</p>
@if ($quote->documents()->where('is_deliverable', true)->exists())
<p style="color:#cbd4f0;line-height:1.8;">Az átadott fájlokat a projektoldalon töltheted le.</p>
@endif
@if ($quote->handover_note)
<table role="presentation" width="100%" style="border:1px solid #24345e;border-radius:12px;background:#0f1830;"><tr><td style="padding:20px;color:#cbd4f0;white-space:pre-line;line-height:1.75;overflow-wrap:anywhere;">{{ $quote->handover_note }}</td></tr></table>
@endif
<p style="margin:26px 0;"><a href="{{ $portalUrl }}" style="display:inline-block;padding:13px 22px;border-radius:999px;background:#4263ff;color:#ffffff;font-weight:700;text-decoration:none;">Átadott fájlok megnyitása</a></p>
<p style="color:#8f9bbd;font-size:12px;line-height:1.7;">A link személyes, ne add tovább. A belépési adatokat és a fájlokat őrizd biztonságos helyen.</p>
@endsection
