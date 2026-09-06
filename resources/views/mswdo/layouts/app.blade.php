<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'MSWDO') – San Agustin</title>
    <link rel="icon" type="image/png" href="{{ asset('images/sanagustinlogo.png') }}">
    {{-- app.js imports sweetalert2's stylesheet, so it arrives with the
         bundle. The separate vendor <link> here was the same CSS a second
         time, loaded after the bundle and quietly overriding the branded
         styling with the library defaults. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Page-to-page transition. Kept short and subtle — it should read as
           the page settling, not as an effect. */
        @keyframes page-enter {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: none; }
        }
        .page-enter { animation: page-enter .28s cubic-bezier(.22,.61,.36,1) both; }

        /* Respect the OS setting: motion can be genuinely uncomfortable, and
           this audience is more likely than most to have it switched on. */
        @media (prefers-reduced-motion: reduce) {
            .page-enter { animation: none; }
            *, *::before, *::after {
                animation-duration: .01ms !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
{{--
    Sized for the people who actually use this: municipal staff in their 50s and
    60s, often on older monitors. Base text is 16px rather than Tailwind's 14px
    default, body copy is near-black instead of grey, and interactive targets are
    at least 44px tall.
--}}
{{-- A warm off-white canvas rather than a cold grey. White cards lift off it
     cleanly, and a screen of tables stops reading as a spreadsheet. --}}
<body class="bg-[#faf8f3] font-sans antialiased text-gray-900 text-base">

<div class="flex h-screen overflow-hidden app-shell">

    <button type="button" data-sidebar-overlay data-print-hide aria-label="Close navigation"
            class="fixed inset-0 z-30 hidden bg-teal-950/50 backdrop-blur-sm lg:hidden"></button>

    {{-- Sidebar --}}
    @include('mswdo.layouts.sidebar')

    {{-- Main content --}}
    <div class="flex-1 flex flex-col overflow-hidden">

        {{-- Topbar --}}
        @include('mswdo.layouts.topbar')

        {{-- Page content --}}
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">

            <div class="mx-auto w-full max-w-[1500px]">

            {{--
                Page heading and breadcrumb. Every page already declares a
                `page-title` section for the old topbar, so this reuses it and
                no page needs editing to gain a heading. A page that wants a
                different trail or an action button beside the title overrides
                `breadcrumb` / `page-actions`.
            --}}
            <div data-print-hide class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-extrabold tracking-tight text-gray-900">
                        @yield('page-title', 'Dashboard')
                    </h1>
                    <nav class="crumbs" aria-label="Breadcrumb">
                        @hasSection('breadcrumb')
                            @yield('breadcrumb')
                        @else
                            <a href="{{ auth()->user()->homeRoute() }}">Home</a>
                            <span class="sep" aria-hidden="true">/</span>
                            <span class="current">@yield('page-title', 'Dashboard')</span>
                        @endif
                    </nav>
                </div>
                @yield('page-actions')
            </div>

            {{--
                Success and error now surface as SweetAlert toasts (see the swal
                partial). Validation stays inline as well as in the toast: the
                toast fades, but the clerk still needs to see which field to fix
                while working through the form.
            --}}
            @if($errors->any())
                <div class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3.5 text-sm text-red-800 shadow-sm" role="alert">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                    </svg>
                    <div>
                        <p class="font-bold">Please check the highlighted information.</p>
                        <p class="mt-0.5">{{ $errors->first() }}</p>
                    </div>
                </div>
            @endif

            <div class="page-enter">
                @yield('content')
            </div>
            </div>
        </main>
    </div>
</div>

{{-- SweetAlert is bundled into app.js (which sets window.Swal). It used to
     ALSO be loaded here from public/vendor, so two separate copies were live
     at once and whichever ran last won. That is what made dialogs open and
     instantly close, or never appear — the handlers and the dialogs were
     talking to different instances. One source only. --}}
@include('mswdo.partials.swal')

</body>
</html>
