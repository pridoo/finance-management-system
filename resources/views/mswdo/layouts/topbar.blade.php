{{--
    The topbar carries the search and the account, matching the pattern staff
    already know from every other web app. The page title itself moved down
    into the content area, under a breadcrumb, so this strip stays a thin
    utility bar rather than a second heading.
--}}
<header data-print-hide class="topbar">

    <div class="flex min-w-0 flex-1 items-center gap-3">
        <button type="button" data-sidebar-open
                class="lg:hidden shrink-0 rounded-xl border border-[#ece7dc] bg-white p-2.5 text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-teal-500"
                aria-label="Open navigation">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        {{-- The municipal seal, with the office named beside it. This is a
             government system and the seal is what identifies it; on narrow
             screens the wordmark drops away and the seal alone carries it. --}}
        <div class="flex min-w-0 items-center gap-3">
            <img src="{{ asset('images/sanagustinlogo.png') }}" alt=""
                 class="h-10 w-10 shrink-0 rounded-full object-contain" aria-hidden="true">
            <div class="hidden min-w-0 sm:block">
                <p class="truncate text-sm font-bold leading-tight text-gray-900">Municipality of San Agustin</p>
                <p class="truncate text-xs font-medium text-gray-500">Social Welfare and Development Office</p>
            </div>
        </div>
    </div>

    <div class="flex shrink-0 items-center gap-2.5">
        <span class="hidden lg:inline text-sm font-medium text-gray-500">{{ now()->format('M d, Y') }}</span>

        {{--
            The account menu. Plain <details>, so it opens and closes without a
            line of JavaScript and still works if the bundle fails to load —
            which matters, because logging out lives inside it.
        --}}
        @php
            $me = auth()->user();
            $initials = collect(preg_split('/\s+/', trim($me->name)))
                ->filter()->take(2)
                ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                ->implode('') ?: mb_strtoupper(mb_substr($me->name, 0, 1));
        @endphp
        <details class="relative" data-user-menu>
            <summary class="icon-btn list-none cursor-pointer" aria-haspopup="menu">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-teal-600 to-teal-700 text-xs font-extrabold text-white" aria-hidden="true">{{ $initials }}</span>
                <span class="hidden max-w-32 truncate sm:inline">{{ $me->name }}</span>
                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>

            <div class="absolute right-0 z-50 mt-2 w-60 overflow-hidden rounded-2xl border border-[#ece7dc] bg-white shadow-xl" role="menu">
                <div class="border-b border-[#f2eee5] px-4 py-3">
                    <p class="truncate text-sm font-bold text-gray-900">{{ $me->name }}</p>
                    <p class="truncate text-xs text-gray-500">{{ $me->email }}</p>
                    <p class="mt-1.5 inline-flex rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-teal-800 ring-1 ring-inset ring-teal-200">
                        {{ $me->isAdmin() ? 'Administrator' : str_replace('_', ' ', $me->role) }}
                    </p>
                </div>

                {{-- data-swal-logout only. This form used to carry
                     data-swal-confirm as well, so the click handler and the
                     submit handler both fired: the second dialog closed the
                     first, and the button looked dead. --}}
                <form method="POST" action="{{ route('logout') }}"
                      data-swal-title="Log out?"
                      data-swal-text="You will be signed out of the MSWDO portal. Any unsaved work on this page will be lost."
                      data-swal-confirm-text="Yes, log out">
                    @csrf
                    <button type="button" data-swal-logout role="menuitem"
                            class="flex w-full items-center gap-2.5 px-4 py-3 text-left text-sm font-semibold text-gray-700 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:bg-red-50 focus:text-red-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Log out
                    </button>
                </form>
            </div>
        </details>
    </div>
</header>
