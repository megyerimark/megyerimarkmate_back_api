@props(['href', 'variant' => 'primary'])
{{-- „Golyóálló” gomb: a háttér a cellán van, így Outlookban is gombként jelenik meg. --}}
@php($primary = $variant === 'primary')
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 6px;"><tr><td style="border-radius:999px;background:{{ $primary ? '#4263ff' : '#0f1627' }};{{ $primary ? '' : 'border:1px solid #2a3a66;' }}"><a href="{{ $href }}" style="display:inline-block;padding:15px 26px;border-radius:999px;color:#ffffff;font-family:'Space Grotesk','Manrope',Arial,sans-serif;font-size:14px;font-weight:bold;line-height:1;text-decoration:none;">{{ $slot }}</a></td></tr></table>
