<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Under Maintenance – San Agustin MSWDO</title>
    <link rel="icon" type="image/png" href="{{ asset('images/sanagustinlogo.png') }}">
    {{-- Styles are inline rather than from the Vite bundle: this page is shown
         exactly when the app is being changed, which can include the bundle
         itself being replaced. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            color: #111827;
            background: #faf8f3;
        }
        .backdrop {
            min-height: 100%;
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
            background: rgba(4, 47, 46, .55);
            backdrop-filter: blur(4px);
        }
        .card {
            width: 100%; max-width: 440px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .35);
            overflow: hidden;
            text-align: center;
            animation: rise .3s cubic-bezier(.22, .61, .36, 1) both;
        }
        .bar { height: 6px; background: linear-gradient(90deg, #0d9488, #84cc16, #f59e0b); }
        .body { padding: 32px 28px 28px; }
        .logo { width: 72px; height: 72px; object-fit: contain; margin: 0 auto 16px; display: block; }
        .icon {
            width: 52px; height: 52px; margin: 0 auto 14px;
            border-radius: 9999px; background: #fef3c7; color: #b45309;
            display: flex; align-items: center; justify-content: center;
        }
        h1 { font-size: 22px; font-weight: 800; margin: 0 0 8px; letter-spacing: -.01em; }
        p { font-size: 16px; line-height: 1.55; color: #374151; margin: 0 0 10px; }
        .muted { font-size: 14px; color: #6b7280; }
        .status {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: 14px; padding: 8px 14px;
            border-radius: 9999px; background: #f0fdfa; color: #0f766e;
            font-size: 14px; font-weight: 600;
        }
        .dot { width: 8px; height: 8px; border-radius: 9999px; background: #14b8a6; animation: pulse 1.4s ease-in-out infinite; }
        button {
            display: block; width: 100%; min-height: 48px; margin-top: 22px;
            border: 0; border-radius: 12px;
            background: #0d9488; color: #fff;
            font: inherit; font-size: 16px; font-weight: 700;
            cursor: pointer;
        }
        button:hover { background: #0f766e; }
        button:focus-visible { outline: 3px solid #5eead4; outline-offset: 2px; }
        @keyframes rise { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: none; } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }
        @media (prefers-reduced-motion: reduce) { .card, .dot { animation: none; } }
    </style>
</head>
<body>
    <div class="backdrop">
        <div class="card" role="alertdialog" aria-labelledby="mt-title" aria-describedby="mt-text">
            <div class="bar"></div>
            <div class="body">
                <img class="logo" src="{{ asset('images/sanagustinlogo.png') }}" alt="Municipality of San Agustin">
                <div class="icon" aria-hidden="true">
                    <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437"/>
                    </svg>
                </div>
                <h1 id="mt-title">System Under Maintenance</h1>
                <p id="mt-text">The MSWDO portal is being updated and is briefly unavailable.</p>
                <p class="muted">No records have been lost. Please try again in a few minutes.</p>
                <div class="status" aria-live="polite">
                    <span class="dot" aria-hidden="true"></span>
                    <span data-status>Checking again automatically…</span>
                </div>
                <button type="button" onclick="location.reload()">Try again now</button>
            </div>
        </div>
    </div>

    {{-- Reloads by itself once the portal answers again, so nobody has to sit
         pressing refresh. --}}
    <script>
    (function () {
        var url = @json(route('ping'));
        setInterval(function () {
            fetch(url, { cache: 'no-store', credentials: 'same-origin' })
                .then(function (res) {
                    if (res.status !== 503) {
                        document.querySelector('[data-status]').textContent = 'Back online — reloading…';
                        location.reload();
                    }
                })
                .catch(function () {});
        }, 15000);
    })();
    </script>
</body>
</html>
