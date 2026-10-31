@extends('emails.layouts.aurora')
@section('title','Ajánlat elfogadva')
@section('preheader', $quote->customer_name.' elfogadta a #'.$quote->id.' számú ajánlatot.')
@section('eyebrow','ADMIN / PROJEKTJÓVÁHAGYÁS')
@section('headline','Elfogadtak egy ajánlatot.')
@section('content')
<x-mail.text>{{ $quote->customer_name }} elfogadta a #{{ $quote->id }} számú ajánlatot.</x-mail.text>
<x-mail.details title="ÖSSZEFOGLALÓ">
<x-mail.row label="Ügyfél" :first="true">{{ $quote->customer_name }}<br><a href="mailto:{{ $quote->email }}" style="color:#8fb0ff;text-decoration:none;font-weight:normal;">{{ $quote->email }}</a></x-mail.row>
<x-mail.row label="Ajánlat értéke"><span style="font-size:20px;">{{ number_format($quote->quoted_price_huf, 0, ',', ' ') }} Ft</span></x-mail.row>
@if ($quote->depositAmount())
<x-mail.row label="Előleg ({{ $quote->deposit_percent }}%)">{{ number_format($quote->depositAmount(), 0, ',', ' ') }} Ft</x-mail.row>
@endif
@if ($quote->proposal_accepted_at)
<x-mail.row label="Elfogadás">{{ $quote->proposal_accepted_at->copy()->timezone('Europe/Budapest')->format('Y. m. d. H:i') }}</x-mail.row>
@endif
</x-mail.details>
<x-mail.text>Egyeztesd a következő lépéseket az ügyféllel.</x-mail.text>
@endsection
