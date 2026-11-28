<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;color:#333;line-height:1.6;">
    <h2 style="margin:0 0 16px;">{{ __('bank-payments::emails.review_heading') }}</h2>
    <p style="margin:0 0 16px;">
        {{ __('bank-payments::emails.review_intro', ['mailbox' => $mailboxName, 'count' => $matchCount]) }}
    </p>
    <p style="margin:0 0 20px;">
        <a href="{{ $queueUrl }}" style="display:inline-block;padding:10px 16px;background:#1a4d80;color:#fff;text-decoration:none;border-radius:4px;">
            {{ __('bank-payments::emails.review_action') }}
        </a>
    </p>
    <p style="font-size:12px;color:#777;margin:0;">{{ __('bank-payments::emails.review_note') }}</p>
</body>
</html>
