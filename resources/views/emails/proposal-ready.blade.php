@extends('emails.layouts.aurora')
@section('title','Elkészült az egyedi ajánlatod')
@section('eyebrow','02 / SZEMÉLYRE SZABOTT AJÁNLAT')
@section('headline','Elkészült az ajánlatod.')
@section('content')
<p style="color:#c7d0ec;line-height:1.8;">Kedves {{ $quote->customer_name }}! A #{{ $quote->id }} számú megkeresésed alapján összeállítottam a javaslatomat.</p>
<table role="presentation" width="100%" style="border:1px solid #38457e;border-radius:12px;background:#162247;"><tr><td style="padding:22px;">
<span style="color:#adb9e1;font-size:11px;letter-spacing:1.3px;">AJÁNLATI ÖSSZEG</span><br><strong style="font-size:29px;color:#fff;">{{ number_format($quote->quoted_price_huf, 0, ',', ' ') }} Ft</strong><br>
<span style="font-size:12px;color:#bac6e5;">Érvényes: {{ $quote->proposal_valid_until->format('Y. m. d.') }} napjáig</span></td></tr></table>
<p style="color:#cbd4f0;white-space:pre-line;line-height:1.75;overflow-wrap:anywhere;">{{ $quote->proposal_summary }}</p>
<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="background:#6151ec;border-radius:10px;"><a style="display:inline-block;padding:16px 25px;color:white;text-decoration:none;font-weight:bold;font-size:14px;" href="{{ $portalUrl }}">Ajánlat megtekintése és elfogadása &rarr;</a></td></tr></table>
<p style="color:#9faece;font-size:12px;line-height:1.75;">A link személyes, ezért kérlek, ne továbbítsd. Az ajánlat elfogadása a privát projektfelületen lehetséges.</p>
@endsection