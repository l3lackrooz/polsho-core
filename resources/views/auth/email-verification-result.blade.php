<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title>Email verification · Polsho</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #0b1118;
            color: #f4f7fb;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at top, rgba(0, 194, 168, .18), transparent 38%),
                #0b1118;
        }
        main {
            width: min(100%, 480px);
            padding: 32px;
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 20px;
            background: #141d27;
            box-shadow: 0 24px 80px rgba(0, 0, 0, .3);
            text-align: center;
        }
        .mark {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #00c2a8;
            color: #07110f;
            font-size: 34px;
            font-weight: 800;
        }
        h1 { margin: 0 0 12px; font-size: 26px; }
        p { margin: 0; color: #b6c2d0; line-height: 1.65; }
        .email { margin-top: 8px; color: #f4f7fb; word-break: break-word; }
        .hint { margin-top: 24px; font-size: 14px; }
    </style>
</head>
<body>
<main>
    <div class="mark" aria-hidden="true">✓</div>
    <h1>{{ $alreadyVerified ? 'Email already verified' : 'Email verified' }}</h1>
    <p>Your Polsho email address is ready.</p>
    <p class="email">{{ $email }}</p>
    <p class="hint">You can close this page and return to the Polsho app.</p>
</main>
</body>
</html>
