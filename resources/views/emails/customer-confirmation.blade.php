@extends('emails.layouts.aurora')
@section('title','Ajánlatkérésed beérkezett')
@section('preheader','Megérkezett a megkeresésed. Privát projektoldaladon követheted az állapotát.')
@section('eyebrow','01 / KAPCSOLATFELVÉTEL')
@section('headline','Megérkezett az elképzelésed.')
@section('content')
<p style="color:#c7d0ec;line-height:1.8;">Kedves {{ $quote->customer_name }}!</p>
<p style="color:#c7d0ec;line-height:1.8;">Köszönöm, hogy megkerestél. A <strong style="color:#fff;">#{{ $quote->id }}</strong> számú ajánlatkérésedet megkaptam. Hamarosan átnézem a részleteket.</p>
<table role="presentation" width="100%" style="margin:22px 0;background:#141f3f;border:1px solid #334679;border-radius:12px;"><tr><td style="padding:18px;color:#c7d0ec;font-size:13px;line-height:1.7;">AZ AJÁNLATKÉRÉSED AZONOSÍTÓJA<br><strong style="font-size:24px;color:#fff;">#{{ $quote->id }}</strong></td></tr></table>
@if($quote->promo_percent)
<table role="presentation" width="100%" style="margin:0 0 22px;background:#1d1a3f;border:1px solid #8b74f0;border-radius:12px;"><tr><td style="padding:18px;color:#d9d3ff;font-size:13px;line-height:1.7;">✦ AKCIÓS KEDVEZMÉNY RÖGZÍTVE<br><strong style="font-size:22px;color:#fff;">−{{ $quote->promo_percent }}%</strong>@if($quote->promo_title) <span style="color:#c9c0ff;">– {{ $quote->promo_title }}</span>@endif<br><span style="font-size:12px;color:#b9b3e6;">Az ajánlatkérésed az akció ideje alatt érkezett ({{ $quote->created_at->timezone('Europe/Budapest')->format('Y. m. d.') }}), ezért a kedvezményt az ajánlatban érvényesítem.</span></td></tr></table>
@endif
@if(str_starts_with($quote->project_description ?? '', 'AURORA KVÍZ – IGÉNYFELMÉRÉS'))
<table role="presentation" width="100%" style="margin:22px 0;background:#1b2149;border:1px solid #756bca;border-radius:13px;"><tr><td style="padding:21px;color:#d5daf7;line-height:1.8;">
<span style="display:block;color:#bda8ff;font-size:11px;font-weight:bold;letter-spacing:1px;">✦ AURORA / IGÉNYFELMÉRŐ</span>
<strong style="display:block;color:#fff;font-size:18px;margin:8px 0 12px;">A válaszaid összefoglalása</strong>
<p style="font-size:12px;line-height:1.85;color:#c9d3f1;">{!! nl2br(e($quote->project_description)) !!}</p>
</td></tr></table>
<p style="color:#9fafcf;font-size:12px;line-height:1.7;">A fenti javaslat kiindulópont, nem végleges műszaki terv vagy árajánlat. A részleteket közösen pontosítjuk.</p>
@endif
<p style="color:#c7d0ec;line-height:1.7;">A személyes projektfelületeden követheted a megkeresésedet és a későbbi ajánlatot.</p>
<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="background:#6151ec;border-radius:10px;"><a href="{{ $portalUrl }}" style="display:inline-block;padding:16px 25px;font-size:14px;font-weight:bold;text-decoration:none;color:#fff;">Privát projektfelület megnyitása &rarr;</a></td></tr></table>
<p style="color:#9fafcf;font-size:12px;line-height:1.7;margin-top:26px;">Ez a link személyes hozzáférést ad, ezért kérlek, ne oszd meg másokkal. A visszaigazolás nem végleges árajánlat.</p>
@endsection
