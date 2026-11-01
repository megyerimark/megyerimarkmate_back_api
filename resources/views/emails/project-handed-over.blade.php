@extends('emails.layouts.aurora')
@section('title', mt('Átadtam a projektedet'))
@section('preheader', mt('A #:id számú projekt forráskódját és átadási csomagját átadtam.', ['id' => $quote->id]))
@section('eyebrow', mt('KÓDÁTADÁS'))
@section('headline', mt('Minden a tiéd.'))
@section('content')
<x-mail.text>{{ mt('Kedves :name! Köszönöm a fizetést – a #:id számú projekt forráskódját és átadási csomagját átadtam.', ['name' => $quote->customer_name, 'id' => $quote->id]) }}</x-mail.text>
@if ($quote->documents()->where('is_deliverable', true)->exists())
<x-mail.text>{{ mt('Az átadott fájlokat a projektoldalon töltheted le.') }}</x-mail.text>
@endif
@if ($quote->handover_note)
<x-mail.details :title="mt('ÁTADÁSI MEGJEGYZÉS')">
<tr><td colspan="2" style="padding:6px 20px 18px;color:#c7d0ec;font-size:14px;line-height:1.75;white-space:pre-line;word-break:break-word;">{{ $quote->handover_note }}</td></tr>
</x-mail.details>
@endif
<x-mail.button :href="$portalUrl">{{ mt('Átadott fájlok megnyitása') }} &rarr;</x-mail.button>
<x-mail.note>{{ mt('A link személyes, ne add tovább. A belépési adatokat és a fájlokat őrizd biztonságos helyen.') }}</x-mail.note>
@endsection
