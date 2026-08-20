<!doctype html>
<html lang="{{ $lang }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0; padding:24px; background:#f4f7f6; font-family: Arial, Helvetica, sans-serif; color:#1f2a24;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8e5;">
        <tr>
            <td style="background:#0f9d6c; padding:20px 28px;">
                <span style="color:#ffffff; font-size:18px; font-weight:bold;">{{ $companyName }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding:28px; font-size:15px; line-height:1.6;">
                {!! nl2br(e($body)) !!}
            </td>
        </tr>
    </table>
</body>
</html>
