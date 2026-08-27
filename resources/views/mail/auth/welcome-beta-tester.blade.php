<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>Welcome to OVRLOAD</title>
</head>
<body style="margin:0;padding:0;background-color:#0a0a0a;color:#ebebe0;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#0a0a0a;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:520px;background-color:#111111;border:1px solid #2a2a2a;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 28px 8px 28px;">
                            <p style="margin:0;font-size:28px;font-weight:700;letter-spacing:0.04em;line-height:1.1;">
                                <span style="color:#d9ff00;">OVR</span><span style="color:#ebebe0;">LOAD</span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px 8px 28px;">
                            <p style="margin:0;font-size:18px;font-weight:600;color:#ebebe0;line-height:1.4;">
                                Welcome, {{ $userName }}
                            </p>
                            <p style="margin:12px 0 0 0;font-size:14px;line-height:1.55;color:#b8b8ae;">
                                Thanks for joining the OVRLOAD beta. Two quick places to start:
                            </p>
                            <ul style="margin:16px 0 0 0;padding:0 0 0 18px;font-size:14px;line-height:1.6;color:#b8b8ae;">
                                <li style="margin:0 0 8px 0;">
                                    <a href="{{ $tutorialUrl }}" style="color:#00e5ff;text-decoration:underline;">Tutorial</a>
                                    — how routines, Play, and progression work
                                </li>
                                <li style="margin:0;">
                                    <a href="{{ $faqsUrl }}" style="color:#00e5ff;text-decoration:underline;">Beta FAQs</a>
                                    — common questions and what’s coming next
                                </li>
                            </ul>
                            <p style="margin:16px 0 0 0;font-size:14px;line-height:1.55;color:#b8b8ae;">
                                Stuck, curious, or found a bug? Reply to this email — I read every message and I’m happy to help.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px 32px 28px;">
                            <a href="{{ $tutorialUrl }}"
                               style="display:inline-block;background-color:#d9ff00;color:#0a0a0a;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:0.02em;padding:12px 20px;border-radius:10px;">
                                Open the tutorial
                            </a>
                        </td>
                    </tr>
                </table>
                <p style="margin:20px 0 0 0;font-size:11px;color:#5c5c56;max-width:520px;line-height:1.4;">
                    Progressive strength tracking. Reply anytime to reach {{ $replyToName }}.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
