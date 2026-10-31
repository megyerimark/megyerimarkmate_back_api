@extends('emails.layouts.aurora')
@section('title','Ajánlatkérésed beérkezett')
@section('preheader','Megérkezett a megkeresésed. Privát projektoldaladon követheted az állapotát.')
@section('eyebrow','01 / KAPCSOLATFELVÉTEL')
@section('headline','Megérkezett az elképzelésed.')
@section('content')
<x-mail.text>Kedves {{ $quote->customer_name }}!</x-mail.text>
<x-mail.text>Köszönöm, hogy megkerestél. Az ajánlatkérésedet megkaptam, hamarosan átnézem a részleteket, és jelentkezem.</x-mail.text>
<x-mail.highlight label="AZ AJÁNLATKÉRÉSED AZONOSÍTÓJA" value="#{{ $quote->id }}" />
@if($quote->promo_percent)
<x-mail.highlight label="✦ AKCIÓS KEDVEZMÉNY RÖGZÍTVE" value="−{{ $quote->promo_percent }}%{{ $quote->promo_title ? ' – '.$quote->promo_title : '' }}" sub="Az ajánlatkérésed az akció ideje alatt érkezett ({{ $quote->created_at->copy()->timezone('Europe/Budapest')->format('Y. m. d.') }}), ezért a kedvezményt az ajánlatban érvényesítem." />
@endif
@if(str_starts_with($quote->project_description ?? '', 'AURORA KVÍZ – IGÉNYFELMÉRÉS'))
<x-mail.details title="✦ IGÉNYFELMÉRŐ / A VÁLASZAID">
<tr><td colspan="2" style="padding:6px 20px 18px;color:#c7d0ec;font-size:13px;line-height:1.85;white-space:pre-line;word-break:break-word;">{{ trim(\Illuminate\Support\Str::after($quote->project_description, 'AURORA KVÍZ – IGÉNYFELMÉRÉS')) }}</td></tr>
</x-mail.details>
<x-mail.note>A fenti javaslat kiindulópont, nem végleges műszaki terv vagy árajánlat. A részleteket közösen pontosítjuk.</x-mail.note>
@endif
<p style="margin:22px 0 0;color:#c7d0ec;font-size:15px;line-height:1.75;">A személyes projektfelületeden követheted a megkeresésedet és a későbbi ajánlatot.</p>
<x-mail.button :href="$portalUrl">Privát projektfelület megnyitása &rarr;</x-mail.button>
<x-mail.note>Ez a link személyes hozzáférést ad, ezért kérlek, ne oszd meg másokkal. A visszaigazolás nem végleges árajánlat.</x-mail.note>
@endsection
