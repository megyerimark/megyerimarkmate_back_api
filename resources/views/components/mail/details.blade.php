@props(['title' => null])
{{-- Adatsorok kerettel: <x-mail.details title="…"><x-mail.row label="…">…</x-mail.row></x-mail.details> --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:22px 0;border-collapse:separate;background:#0f1830;border:1px solid #24345e;border-radius:14px;">
@if ($title)
<tr><td colspan="2" style="padding:16px 20px 6px;color:#8fb0ff;font-size:11px;font-weight:bold;letter-spacing:2px;">{{ $title }}</td></tr>
@endif
{{ $slot }}
</table>
