@extends('emails.layouts.aurora')
@section('title','Ajánlatkérésed beérkezett')
@section('preheader','Megérkezett a megkeresésed. Privát projektoldaladon követheted az állapotát.')
@section('eyebrow','01 / KAPCSOLATFELVÉTEL')
@section('headline','Megérkezett az elképzelésed.')
@section('content')
<p style="color:#c7d0ec;line-height:1.8;">Kedves {{ $quote->customer_name }}!</p>
<p style="color:#c7d0ec;line-height:1.8;">Köszönöm, hogy megkerestél. A <strong style="color:#fff;">#{{ $quote->id }}</strong> számú ajánlatkérésedet megkaptam. Hamarosan átnézem a részleteket.</p>
<table role="presentation" width="100%" style="margin:22px 0;background:#141f3f;border:1px solid #334679;border-radius:12px;"><tr><td style="padding:18px;color:#c7d0ec;font-size:13px;line-height:1.7;">AZ AJÁNLATKÉRÉSED AZONOSÍTÓJA<br><strong style="font-size:24px;color:#fff;">#{{ $quote->id }}</strong></td></tr></table>
<p style="color:#c7d0ec;line-height:1.7;">A személyes projektfelületeden követheted a megkeresésedet és a későbbi ajánlatot.</p>
<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="background:#6151ec;border-radius:10px;"><a href="{{ $portalUrl }}" style="display:inline-block;padding:16px 25px;font-size:14px;font-weight:bold;text-decoration:none;color:#fff;">Privát projektfelület megnyitása &rarr;</a></td></tr></table>
<p style="color:#9fafcf;font-size:12px;line-height:1.7;margin-top:26px;">Ez a link személyes hozzáférést ad, ezért kérlek, ne oszd meg másokkal. A visszaigazolás nem végleges árajánlat.</p>
@endsection