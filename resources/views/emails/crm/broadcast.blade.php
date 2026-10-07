<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; background:#f3f4f6; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px; width:100%; background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;">
                    <tr>
                        <td style="padding:24px; font-family:Arial, sans-serif; font-size:14px; color:#1f2937; line-height:1.55;">
                            {!! $content !!}
                            @if(! empty($attachments))
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:22px; padding-top:14px; border-top:1px solid #e5e7eb;">
                                    <tr>
                                        <td style="font-family:Arial, sans-serif; font-size:14px; color:#1f2937;">
                                            <strong>Downloads</strong>
                                            @foreach($attachments as $attachment)
                                                @if(! empty($attachment['url']))
                                                    <p style="margin:8px 0 0;"><a href="{{ $attachment['url'] }}" style="color:#135fd2;">{{ $attachment['name'] ?? 'Download file' }}</a></p>
                                                @endif
                                            @endforeach
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
