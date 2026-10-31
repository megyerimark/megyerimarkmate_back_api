@extends('emails.layouts.aurora')
@section('title','Ajánlat elfogadva')
@section('eyebrow','VISSZAIGAZOLÁS')
@section('headline','Köszönöm, megkaptam az elfogadást!')
@section('content')
<p style="color:#cad3ed;line-height:1.85;">Kedves {{ $quote->customer_name }}! Ezzel az e-maillel visszaigazolom, hogy a <strong style="color:white;">#{{ $quote->id }}</strong> számú ajánlatot elfogadtad. Az elfogadással a szerződés az ajánlatban és az Általános Szerződési Feltételekben ({{ $quote->terms_version }}) foglaltak szerint létrejött.</p>
<table role="presentation" style="background:#0f1830;border:1px solid #24345e;border-radius:12px;" width="100%"><tr><td style="padding:20px;color:#c7d0ec;line-height:2;">
Vállalási díj<br><strong style="font-size:25px;color:#fff;">{{ number_format($quote->quoted_price_huf, 0, ',', ' ') }} Ft</strong><br>
@if ($quote->promo_percent)
Akciós kedvezmény: −{{ $quote->promo_percent }}%@if($quote->list_price_huf) (akció előtti ár: {{ number_format($quote->list_price_huf, 0, ',', ' ') }} Ft)@endif – érvényesítve.<br>
@endif
@if ($quote->depositAmount())
Előleg ({{ $quote->deposit_percent }}%): <strong style="color:#fff;">{{ number_format($quote->depositAmount(), 0, ',', ' ') }} Ft</strong> – a munka az előleg beérkezése után indul.<br>
@endif
Elfogadás időpontja: {{ $quote->proposal_accepted_at->timezone('Europe/Budapest')->format('Y. m. d. H:i') }}
</td></tr></table>
<p style="margin:26px 0;"><a href="{{ $portalUrl }}" style="display:inline-block;padding:13px 22px;border-radius:999px;background:#4263ff;color:#ffffff;font-weight:700;text-decoration:none;">Projekt megnyitása</a></p>
<p style="color:#8f9bbd;font-size:12px;line-height:1.7;">Az ajánlat teljes szövege és az ÁSZF a projektoldalon bármikor elérhető. A link személyes, ne add tovább.</p>
@endsection
