<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $invitation ? 'Join ' . $instanceName : 'Invitation not found' }}</title>
    <style>
        :root { color-scheme: light dark; --accent: #2f6f5e; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.5; background: Canvas; color: CanvasText; }
        main { max-width: 32rem; margin: 0 auto; padding: 2rem 1.25rem; }
        h1 { font-size: 1.6rem; line-height: 1.25; margin: 0 0 .75rem; }
        .button { display: block; text-align: center; padding: .9rem 1rem; margin: 1.5rem 0; border-radius: .6rem; background: var(--accent); color: #fff; font-size: 1.1rem; font-weight: 600; text-decoration: none; }
        .button:focus-visible { outline: 3px solid CanvasText; outline-offset: 3px; }
        dl { margin: 0; }
        dt { font-weight: 600; margin-top: 1rem; }
        dd { margin: .25rem 0 0; }
        code { display: block; padding: .6rem .75rem; border: 1px solid GrayText; border-radius: .4rem; font-size: 1rem; overflow-wrap: anywhere; user-select: all; }
    </style>
</head>
<body>
<main>
@if ($invitation)
    <h1>You are invited to {{ $instanceName }}</h1>
    <p>{{ $invitation->inviter?->name ?? 'A family member' }} invited you to join the family on Kinlore.</p>

    <a class="button" href="{{ $invitation->inviteLink() }}">Open in Kinlore</a>

    <p>If the button does not work, open the Kinlore app, choose to join with an invitation and enter these details:</p>
    <dl>
        <dt id="server-label">Server address</dt>
        <dd><code aria-labelledby="server-label">{{ $server }}</code></dd>
        <dt id="token-label">Invitation code</dt>
        <dd><code aria-labelledby="token-label">{{ $invitation->token }}</code></dd>
    </dl>
    <p>This invitation expires on {{ $invitation->expires_at->toFormattedDateString() }}.</p>
@else
    <h1>Invitation not found</h1>
    <p>This invitation link is not valid. It may have expired or been used already.</p>
    <p>Ask the person who invited you to send you a new invitation.</p>
@endif
</main>
</body>
</html>
