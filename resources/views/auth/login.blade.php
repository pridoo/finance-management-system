<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - San Agustin MSWDO</title>
    <link rel="icon" type="image/png" href="{{ asset('images/sanagustinlogo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-visual" style="background-image: url('{{ asset('images/dswd-relief-hero.webp') }}')">
            <div class="auth-visual__shade"></div>

            <div class="auth-brand">
                {{-- The municipal seal itself. It is the mark that identifies
                     this as a government system, and it is where the green and
                     yellow of the whole portal comes from. --}}
                <img src="{{ asset('images/sanagustinlogo.png') }}" alt="" class="auth-seal" aria-hidden="true">
                <img src="{{ asset('images/dswd-seal.png') }}" alt="DSWD" class="auth-seal">
                <div>
                    <p class="auth-kicker">Municipality of San Agustin</p>
                    <h1>San Agustin-MSWDO</h1>
                </div>
            </div>

            <div class="auth-visual__content">
                <p class="auth-eyebrow">Municipal Social Welfare and Development</p>
                <h2>Assistance records, approvals, and releases in one place.</h2>
                <p>
                    Record a beneficiary, track a request, and release assistance without leaving the system.
                </p>

                <div class="auth-metrics" aria-label="System capabilities">
                    <div>
                        <strong>24/7</strong>
                        <span>Record access</span>
                    </div>
                    <div>
                        <strong>100%</strong>
                        <span>Audit trace</span>
                    </div>
                    <div>
                        <strong>1</strong>
                        <span>Unified database</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="auth-panel" aria-label="Login form">
            <div class="auth-panel__header">
                <p>Management v2.0</p>
                <span>Authorized Access</span>
            </div>

            <div class="auth-form-wrap">
                {{-- Shown only below 860px, where the left pane is hidden and
                     the page would otherwise carry no identity at all. --}}
                <div class="auth-mobile-brand">
                    <img src="{{ asset('images/sanagustinlogo.png') }}" alt="" class="auth-seal" aria-hidden="true">
                    <img src="{{ asset('images/dswd-seal.png') }}" alt="DSWD" class="auth-seal">
                    <div>
                        <p>Municipality of San Agustin</p>
                        <strong>San Agustin-MSWDO</strong>
                    </div>
                </div>

                <div class="auth-copy">
                    <p class="auth-eyebrow">Welcome back</p>
                    <h2>Sign in</h2>
                    <p>Enter the email and password given to you by the MSWDO Officer.</p>
                </div>

                <form method="POST" action="{{ route('login') }}" class="auth-form">
                    @csrf

                    <label class="auth-field">
                        <span>Email address</span>
                        <div class="auth-input-wrap @error('email') is-invalid @enderror">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4.75 6.75h14.5v10.5H4.75V6.75Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                <path d="m5.25 7.25 6.75 5.2 6.75-5.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="email@sanagustin.gov.ph"
                            >
                        </div>
                        @error('email')
                            <small>
                                <svg viewBox="0 0 24 24" fill="none" style="width:16px;height:16px;flex:0 0 auto;" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                                    <path d="M12 7.5v5M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                                {{ $message }}
                            </small>
                        @enderror
                    </label>

                    <label class="auth-field">
                        <span>Password</span>
                        <div class="auth-input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M7.25 10.25V8.4a4.75 4.75 0 0 1 9.5 0v1.85" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                <path d="M5.75 10.25h12.5v9H5.75v-9Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                <path d="M12 14.2v1.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your password"
                            >
                            {{-- A password that cannot be checked before
                                 submitting is a common cause of repeated failed
                                 logins, especially on an unfamiliar keyboard. --}}
                            <button type="button" data-toggle-password class="auth-reveal" aria-label="Show password">
                                <svg data-eye-show viewBox="0 0 24 24" fill="none">
                                    <path d="M2.5 12S6 5.75 12 5.75 21.5 12 21.5 12 18 18.25 12 18.25 2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.6"/>
                                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
                                </svg>
                                <svg data-eye-hide viewBox="0 0 24 24" fill="none" style="display:none;">
                                    <path d="M4 4l16 16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                    <path d="M9.9 5.98A9.3 9.3 0 0 1 12 5.75c6 0 9.5 6.25 9.5 6.25a17 17 0 0 1-2.86 3.53M6.4 7.6A16.9 16.9 0 0 0 2.5 12S6 18.25 12 18.25c.85 0 1.65-.13 2.4-.35" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                    </label>

                    <div class="auth-options">
                        <label class="auth-check">
                            <input type="checkbox" name="remember" id="remember">
                            <span>Keep me signed in on this computer</span>
                        </label>
                        <span>Session protected</span>
                    </div>

                    <button type="submit" class="auth-submit">
                        <span>Sign In</span>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 12h13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="m13 6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </form>

                {{-- Someone who cannot get in has no other route from here.
                     Naming the MSWDO Officer is the whole of the help this
                     page can honestly offer. --}}
                <p class="auth-help">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/>
                        <path d="M12 16.5v-4.75M12 8.25h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                    <span>
                        Forgotten your password, or account disabled? Contact the
                        <strong>MSWDO Officer</strong> — accounts are created and reset from the office.
                    </span>
                </p>
            </div>

            <div class="auth-panel__footer">
                <span>LGU San Agustin</span>
                <span>&copy; {{ date('Y') }} MSWDO</span>
            </div>
        </section>
    </main>

    <script>
    (function () {
        const toggle = document.querySelector('[data-toggle-password]');
        const field  = document.getElementById('password');
        if (!toggle || !field) return;

        const eyeShow = toggle.querySelector('[data-eye-show]');
        const eyeHide = toggle.querySelector('[data-eye-hide]');

        toggle.addEventListener('click', function () {
            const revealed = field.type === 'text';
            field.type = revealed ? 'password' : 'text';
            eyeShow.style.display = revealed ? '' : 'none';
            eyeHide.style.display = revealed ? 'none' : '';
            toggle.setAttribute('aria-label', revealed ? 'Show password' : 'Hide password');
            field.focus();
        });
    })();
    </script>

{{--
    The login screen previously surfaced nothing at all on a failed attempt:
    no inline error, no toast. A clerk who mistyped a password saw the form
    simply reappear, which reads as "the system is broken" rather than "wrong
    password". Including the shared partial gives this page the same SweetAlert
    feedback every other page has.
--}}
@include('mswdo.partials.swal')
</body>
</html>
