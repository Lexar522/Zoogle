@props([
    'preheader' => '',
    'subject' => null,
    'message' => null,
    'contentPadding' => '0 22px 8px',
    'fullBleed' => false,
])
@php
    $logoSrc = \App\Support\ShopMailBranding::logoSrc($message);
    $homeUrl = url('/');
    $contactEmail = \App\Support\ShopMailBranding::contactEmail();
    $preheader = trim((string) $preheader);
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="uk">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f0f3f7;font-family:Arial,Helvetica,sans-serif;color:#1a1c1e;">
@if ($preheader !== '')
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f3f7;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:18px;border:1px solid #e6eaf0;">
                <tr>
                    <td width="600" style="width:600px;height:6px;background:#367df1;font-size:0;line-height:0;">&nbsp;</td>
                </tr>
                <tr>
                    <td width="600" align="center" style="width:600px;padding:26px 24px 6px;text-align:center;">
                        <a href="{{ $homeUrl }}" style="text-decoration:none;display:inline-block;">
                            <img src="{{ $logoSrc }}" alt="ZOOGLE" width="210" height="78" style="display:block;width:210px;max-width:78%;height:auto;margin:0 auto;border:0;" />
                        </a>
                    </td>
                </tr>
                @if ($fullBleed)
                    {{ $slot }}
                @else
                <tr>
                    <td width="600" align="left" style="width:600px;padding:{{ $contentPadding }};text-align:left;">
                        {{ $slot }}
                    </td>
                </tr>
                @endif
                <tr>
                    <td width="600" align="center" style="width:600px;padding:18px 22px 26px;text-align:center;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="border-top:1px solid #e8eaed;font-size:0;line-height:0;height:1px;">&nbsp;</td>
                            </tr>
                        </table>
                        <center>
                            <p align="center" style="margin:14px auto 0;text-align:center;color:#80868b;font-size:13px;line-height:1.55;">
                                З повагою,<br />
                                <strong style="color:#202124;">ZOOGLE</strong>
                                @if ($contactEmail)
                                    <br /><a href="mailto:{{ $contactEmail }}" style="color:#367df1;text-decoration:none;">{{ $contactEmail }}</a>
                                @endif
                                <br /><a href="{{ $homeUrl }}" style="color:#367df1;text-decoration:none;">zoogle.in.ua</a>
                            </p>
                        </center>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
