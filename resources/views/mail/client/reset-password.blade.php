<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('mail.reset.subject', ['brand' => $brand]) }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f6f5f2; font-family: 'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color: #121212;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f6f5f2;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 520px;">
                    <tr>
                        <td style="padding: 0 0 24px 0; font-size: 20px; font-weight: 650; letter-spacing: -0.01em; color: #121212;">
                            {{ $brand }}
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e7e4de;">
                            <p style="margin: 0 0 16px 0; font-size: 16px; line-height: 24px; color: #121212;">
                                {{ __('mail.reset.greeting', ['name' => $user->name]) }}
                            </p>
                            <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 24px; color: #121212;">
                                {{ __('mail.reset.body', ['brand' => $brand]) }}
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px 0;">
                                <tr>
                                    <td style="border-radius: 8px; background-color: #f26a1b;">
                                        <a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; padding: 12px 24px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px; background-color: #f26a1b;">
                                            {{ __('mail.reset.button') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 8px 0; font-size: 13px; line-height: 20px; color: #6e6b65;">
                                {{ __('mail.reset.fallback') }}
                            </p>
                            <p style="margin: 0 0 24px 0; font-size: 13px; line-height: 20px; word-break: break-all;">
                                <a href="{{ $url }}" target="_blank" rel="noopener" style="color: #b44108; text-decoration: underline;">{{ $url }}</a>
                            </p>
                            <p style="margin: 0 0 8px 0; font-size: 13px; line-height: 20px; color: #6e6b65;">
                                {{ __('mail.reset.expiry', ['minutes' => $expireMinutes]) }}
                            </p>
                            <p style="margin: 0; font-size: 13px; line-height: 20px; color: #6e6b65;">
                                {{ __('mail.reset.ignore') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
