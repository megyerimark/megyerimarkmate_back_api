@extends('emails.layouts.aurora')
@section('title','Ajánlat elfogadva')
@section('eyebrow','ADMIN / PROJEKTJÓVÁHAGYÁS')
@section('headline','Elfogadtak egy ajánlatot.')
@section('content')
<p style="color:#cad3ed;line-height:1.85;"><strong style="color:white;">{{ $quote->customer_name }}</strong> elfogadta a <strong>#{{ $quote->id }}</strong> számú ajánlatot.</p>
<table role="presentation" style="background:#152344;border:1px solid #344578;border-radius:12px;" width="100%"><tr><td style="padding:20px;color:#c7d0ec;line-height:2;">Ajánlat értéke<br><strong style="font-size:27px;color:#fff;">{{ number_format($quote->quoted_price_huf, 0, ',', ' ') }} Ft</strong><br>Elfogadás: {{ $quote->proposal_accepted_at->format('Y. m. d. H:i') }}</td></tr></table>
<p style="color:#c7d0ec;line-height:1.8;">Egyeztesd a következő lépéseket az ügyféllel.</p>
@endsection