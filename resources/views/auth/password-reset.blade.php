<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password · Polsho</title>
    <style>
        body { margin: 0; background: #070b12; color: #eaf0f7; font: 16px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        main { max-width: 420px; margin: 8vh auto; padding: 32px; background: #111b2a; border: 1px solid #263244; border-radius: 20px; }
        h1 { margin-top: 0; } label { display: block; margin: 18px 0 6px; color: #b4bdca; }
        input { box-sizing: border-box; width: 100%; padding: 12px; border: 1px solid #263244; border-radius: 10px; background: #0c1420; color: #eaf0f7; }
        button { width: 100%; margin-top: 24px; padding: 13px; border: 0; border-radius: 10px; background: #00c2a8; color: #070b12; font-weight: 700; cursor: pointer; }
        .message { padding: 12px; border-radius: 10px; } .success { background: #123c2a; } .error { background: #451d28; }
    </style>
</head>
<body><main>
    <h1>Set a new password</h1>
    @if (session('status'))<p class="message success">{{ session('status') }}</p>@endif
    @if (session('error'))<p class="message error">{{ session('error') }}</p>@endif
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autofocus>
        <label for="password">New password</label>
        <input id="password" name="password" type="password" required minlength="8">
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8">
        <button type="submit">Reset password</button>
    </form>
    @if ($errors->any())<p class="message error">{{ $errors->first() }}</p>@endif
</main></body>
</html>
