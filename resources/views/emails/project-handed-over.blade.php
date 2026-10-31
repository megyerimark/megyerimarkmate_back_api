@extends('emails.layouts.aurora')
@section('title','Átadtam a projektedet')
@section('preheader','A #'.$quote->id.' számú projekt forráskódját és átadási csomagját átadtam.')
@section('eyebrow','KÓDÁTADÁS')
@section('headline','Minden a tiéd.')
@section('content')
<x-mail.text>Kedves {{ $quote->customer_name }}! Köszönöm a fizetést – a #{{ $quote->id }} számú projekt forráskódját és átadási csomagját átadtam.</x-mail.text>
@if ($quote->documents()->where('is_deliverable', true)->exists())
<x-mail.text>Az átadott fájlokat a projektoldalon töltheted le.</x-mail.text>
@endif
@if ($quote->handover_note)
<x-mail.details title="ÁTADÁSI MEGJEGYZÉS">
<tr><td colspan="2" style="padding:6px 20px 18px;color:#c7d0ec;font-size:14px;line-height:1.75;white-space:pre-line;word-break:break-word;">{{ $quote->handover_note }}</td></tr>
</x-mail.details>
@endif
<x-mail.button :href="$portalUrl">Átadott fájlok megnyitása &rarr;</x-mail.button>
<x-mail.note>A link személyes, ne add tovább. A belépési adatokat és a fájlokat őrizd biztonságos helyen.</x-mail.note>
@endsection
