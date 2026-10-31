@props(['label', 'value', 'sub' => null])
{{-- Kiemelt összeg / azonosító. --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:22px 0;border-collapse:separate;background:#0f1830;border:1px solid #24345e;border-radius:14px;"><tr><td style="padding:20px 22px;">
<span style="display:block;color:#8fb0ff;font-size:11px;font-weight:bold;letter-spacing:2px;">{{ $label }}</span>
<span style="display:block;margin-top:6px;color:#ffffff;font-size:28px;font-weight:bold;letter-spacing:-.5px;line-height:1.2;">{{ $value }}</span>
@if ($sub)<span style="display:block;margin-top:6px;color:#aab4ca;font-size:12px;line-height:1.6;">{{ $sub }}</span>@endif
{{ $slot }}
</td></tr></table>
