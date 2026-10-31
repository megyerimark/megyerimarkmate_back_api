@props(['label', 'first' => false, 'labelWidth' => '36%'])
<tr>
<td valign="top" style="width:{{ $labelWidth }};padding:13px 12px 13px 20px;{{ $first ? '' : 'border-top:1px solid #1a2647;' }}color:#8c98b4;font-size:12px;line-height:1.5;">{{ $label }}</td>
<td valign="top" style="padding:13px 20px 13px 8px;{{ $first ? '' : 'border-top:1px solid #1a2647;' }}color:#ffffff;font-size:14px;font-weight:600;line-height:1.5;word-break:break-word;">{{ $slot }}</td>
</tr>
