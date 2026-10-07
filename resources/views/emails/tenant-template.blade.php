@php
    $tenantName = optional($tenant)->company_name ?: config('app.name');
    $logo = optional($tenant)->logo;
    $logoUrl = null;
    if ($logo) {
        if (\Illuminate\Support\Str::startsWith($logo, ['http://', 'https://'])) {
            $logoUrl = $logo;
        } else {
            $logoPath = ltrim($logo, '/');
            $publicPath = public_path($logoPath);

            if (isset($message) && method_exists($message, 'embed') && is_file($publicPath)) {
                $logoUrl = $message->embed($publicPath);
            } else {
                $logoUrl = url($logoPath);
            }
        }
    }
    $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $tenantName), 0, 2)) ?: 'CO';
    $headerCost = $details['Cost'] ?? null;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; background:#f3f4f6; font-family:Segoe UI, Arial, sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px; background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td style="background:#073b70; padding:24px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="width:64px;">
                                        @if($logoUrl)
                                            <img src="{{ $logoUrl }}" alt="{{ $tenantName }}" width="54" height="54" style="display:block; object-fit:contain; background:#fff; border-radius:10px; padding:5px;">
                                        @else
                                            <div style="width:54px; height:54px; border-radius:10px; background:#135fd2; color:#fff; display:grid; place-items:center; font-weight:900; font-size:18px;">{{ $initials }}</div>
                                        @endif
                                    </td>
                                    <td style="color:#fff;">
                                        <div style="font-size:20px; font-weight:900; line-height:1.2;">{{ $tenantName }}</div>
                                        <div style="color:#bfdbfe; font-size:13px; margin-top:4px;">{{ $subject }}</div>
                                    </td>
                                    @if($headerCost)
                                        <td align="right" style="color:#fff; font-size:18px; font-weight:900; white-space:nowrap;">{{ $headerCost }}</td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 28px;">
                            <h1 style="margin:0 0 14px; font-size:26px; line-height:1.2; color:#111827;">{{ $headline }}</h1>
                            <div style="font-size:15px; line-height:1.65; color:#374151;">{!! $bodyHtml !!}</div>

                            @if(! empty($details))
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:24px; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
                                    @foreach($details as $label => $value)
                                        <tr>
                                            <td style="padding:11px 14px; background:#f8fafc; border-bottom:1px solid #e5e7eb; color:#64748b; font-size:13px; font-weight:800;">{{ $label }}</td>
                                            <td style="padding:11px 14px; border-bottom:1px solid #e5e7eb; color:#111827; font-size:13px; font-weight:700; text-align:right; white-space:pre-line;">{{ $value ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if(! empty($mailingAddresses))
                                <div style="margin-top:26px; font-size:17px; font-weight:900; color:#111827;">Your Mailing Addresses</div>
                                <div style="margin-top:5px; color:#64748b; font-size:13px; line-height:1.5;">Use the address that matches your shipment method and include the mailbox or marks exactly as shown.</div>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:12px; border-collapse:collapse; table-layout:fixed;">
                                    @foreach(array_chunk($mailingAddresses, 2) as $addressRow)
                                        <tr>
                                            @foreach($addressRow as $address)
                                                <td width="50%" valign="top" style="padding:6px;">
                                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="height:100%; border:1px solid #cbd5e1; border-radius:8px; overflow:hidden;">
                                                        <tr>
                                                            <td style="padding:10px 12px; background:#f8fafc; border-bottom:1px solid #cbd5e1; color:#073b70; font-size:14px; font-weight:900; text-align:center;">
                                                                {{ $address['label'] }}
                                                                @if(! empty($address['country_code']))
                                                                    <span style="color:#64748b; font-size:11px;">({{ $address['country_code'] }})</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td valign="top" style="padding:12px; color:#111827; font-size:12px; line-height:1.45;">
                                                                @foreach($address['lines'] as $line)
                                                                    <div style="margin-bottom:3px; overflow-wrap:anywhere;">
                                                                        <strong>{{ $line['label'] }}:</strong> {{ $line['value'] }}
                                                                    </div>
                                                                @endforeach
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            @endforeach
                                            @if(count($addressRow) === 1)
                                                <td width="50%" style="padding:6px;">&nbsp;</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if($buttonLabel && $buttonUrl)
                                <div style="margin-top:26px;">
                                    <a href="{{ $buttonUrl }}" style="display:inline-block; background:#135fd2; color:#fff; text-decoration:none; padding:12px 18px; border-radius:8px; font-weight:800;">{{ $buttonLabel }}</a>
                                </div>
                            @endif

                            @if($attachmentUrl || ! empty($attachments))
                                <div style="margin:22px 0 0;">
                                    <div style="font-weight:900; color:#111827; margin-bottom:8px;">Downloads</div>
                                    @if($attachmentUrl)
                                        <p style="margin:0 0 8px;"><a href="{{ $attachmentUrl }}" style="color:#135fd2; font-weight:800;">Download attachment</a></p>
                                    @endif
                                    @foreach(($attachments ?? []) as $attachment)
                                        @if(! empty($attachment['url']))
                                            <p style="margin:0 0 8px;"><a href="{{ $attachment['url'] }}" style="color:#135fd2; font-weight:800;">{{ $attachment['name'] ?? 'Download form' }}</a></p>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px; background:#f8fafc; border-top:1px solid #e5e7eb; color:#64748b; font-size:13px; line-height:1.5;">
                            @if($footerHtml)
                                {!! $footerHtml !!}
                            @elseif($footerText)
                                {{ $footerText }}
                            @else
                                Thank you for choosing {{ $tenantName }}.
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
