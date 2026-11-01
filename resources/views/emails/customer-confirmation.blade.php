@extends('emails.layouts.aurora')
@section('title', mt('Ajánlatkérésed beérkezett'))
@section('preheader', mt('Megérkezett a megkeresésed. Privát projektoldaladon követheted az állapotát.'))
@section('eyebrow', mt('01 / KAPCSOLATFELVÉTEL'))
@section('headline', mt('Megérkezett az elképzelésed.'))
@section('content')
<x-mail.text>{{ mt('Kedves :name!', ['name' => $quote->customer_name]) }}</x-mail.text>
<x-mail.text>{{ mt('Köszönöm, hogy megkerestél. Az ajánlatkérésedet megkaptam, hamarosan átnézem a részleteket, és jelentkezem.') }}</x-mail.text>
<x-mail.highlight :label="mt('AZ AJÁNLATKÉRÉSED AZONOSÍTÓJA')" value="#{{ $quote->id }}" />
@if($quote->promo_percent)
<x-mail.highlight :label="mt('✦ AKCIÓS KEDVEZMÉNY RÖGZÍTVE')" value="−{{ $quote->promo_percent }}%{{ $quote->promo_title ? ' – '.$quote->promo_title : '' }}" :sub="mt('Az ajánlatkérésed az akció ideje alatt érkezett (:date), ezért a kedvezményt az ajánlatban érvényesítem.', ['date' => \App\Support\MailText::date($quote->created_at)])" />
@endif
@if(str_starts_with($quote->project_description ?? '', 'AURORA KVÍZ – IGÉNYFELMÉRÉS'))
<x-mail.details :title="mt('✦ IGÉNYFELMÉRŐ / A VÁLASZAID')">
<tr><td colspan="2" style="padding:6px 20px 18px;color:#c7d0ec;font-size:13px;line-height:1.85;white-space:pre-line;word-break:break-word;">{{ trim(\Illuminate\Support\Str::after($quote->project_description, 'AURORA KVÍZ – IGÉNYFELMÉRÉS')) }}</td></tr>
</x-mail.details>
<x-mail.note>{{ mt('A fenti javaslat kiindulópont, nem végleges műszaki terv vagy árajánlat. A részleteket közösen pontosítjuk.') }}</x-mail.note>
@endif
<p style="margin:22px 0 0;color:#c7d0ec;font-size:15px;line-height:1.75;">{{ mt('A személyes projektfelületeden követheted a megkeresésedet és a későbbi ajánlatot.') }}</p>
<x-mail.button :href="$portalUrl">{{ mt('Privát projektfelület megnyitása') }} &rarr;</x-mail.button>
<x-mail.note>{{ mt('Ez a link személyes hozzáférést ad, ezért kérlek, ne oszd meg másokkal. A visszaigazolás nem végleges árajánlat.') }}</x-mail.note>
@endsection
