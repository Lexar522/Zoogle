@php
    $productTitle = mt((string) $question->product?->title);
    $productUrl = $question->product ? route('catalog.show', $question->product->slug).'#questions' : route('home');
    $preheader = __('shop.mail_question_intro', ['name' => $question->publicName(), 'product' => $productTitle]);
@endphp
<x-emails.shop-layout :preheader="$preheader" :subject="__('shop.mail_question_heading')" :message="$message ?? null">
    <h1 style="margin:8px 0 6px;font-size:24px;line-height:1.25;color:#121417;font-weight:700;">{{ __('shop.mail_question_heading') }}</h1>
    <p style="margin:0 0 18px;font-size:15px;line-height:1.5;color:#5f6368;">{{ $preheader }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 12px;">
        <tr>
            <td style="background:#f4f7fc;border:1px solid #e4eaf3;border-radius:12px;padding:12px 14px;">
                <div style="font-size:11px;letter-spacing:0.06em;text-transform:uppercase;color:#5f6368;font-weight:700;">{{ __('shop.mail_question_your') }}</div>
                <div style="margin-top:6px;font-size:14px;line-height:1.5;color:#202124;white-space:pre-line;">{{ $question->body }}</div>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">
        <tr>
            <td style="background:#f8fbff;border:1px solid #d7e3f8;border-left:4px solid #367df1;border-radius:12px;padding:12px 14px;">
                <div style="font-size:11px;letter-spacing:0.06em;text-transform:uppercase;color:#0b57d0;font-weight:700;">{{ __('shop.mail_question_answer') }}</div>
                <div style="margin-top:6px;font-size:15px;line-height:1.55;color:#202124;white-space:pre-line;">{{ $question->answer }}</div>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0;">
        <tr>
            <td align="center">
                <a href="{{ $productUrl }}" style="display:inline-block;background:#367df1;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 22px;border-radius:10px;">
                    {{ __('shop.mail_question_button') }}
                </a>
            </td>
        </tr>
    </table>
</x-emails.shop-layout>
