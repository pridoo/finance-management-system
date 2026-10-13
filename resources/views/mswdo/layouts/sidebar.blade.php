{{--
    A light rail rather than a dark slab. With the navigation on a pale ground,
    green stops being the background and becomes the thing that marks where you
    are — which is the sidebar's whole job. Yellow appears twice: on the brand
    mark, and as the bar beside the current page.

    Entries are grouped under headings so six links read as two short lists
    rather than one undifferentiated column.
--}}
<aside data-sidebar data-print-hide
       class="rail fixed inset-y-0 left-0 z-40 w-72 -translate-x-full shrink-0 shadow-2xl transition-transform duration-200 lg:static lg:w-64 lg:translate-x-0 lg:shadow-none">

    {{-- Brand. The municipal seal itself, rather than a stand-in glyph — and
         it happens to be green and yellow, which is where this palette came
         from in the first place. --}}
    <div class="rail-brand">
        <img src="{{ asset('images/sanagustinlogo.png') }}" alt="" class="rail-seal" aria-hidden="true">
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-sm font-extrabold leading-tight text-gray-900">San Agustin-MSWDO</h1>
            <p class="mt-0.5 text-[11px] font-semibold text-teal-700">v2.0</p>
        </div>
        <button type="button" data-sidebar-close
                class="lg:hidden shrink-0 rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900"
                aria-label="Close navigation">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto pb-4">

        @php
            $me = auth()->user();

            // Every MSWDO entry is keyed by its permission module so the nav
            // shows only what this user may actually open — a link to a 403 is
            // worse than no link at all.
            //
            // Grouped rather than flat: the first group is the daily casework
            // path, the second is the money and the paperwork. The grouping is
            // presentational only; the permission filter below is unchanged.
            // The account-administration pair. The admin sees nothing else; the
            // officer sees it appended below their own casework, because
            // managing logins is the occasional task and the welfare modules
            // are the daily one — the order follows how often each is used.
            $administration = [
                ['route' => 'admin.users.index', 'label' => 'User Management',    'module' => null, 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                ['route' => 'admin.roles.index', 'label' => 'Roles & Permissions', 'module' => null, 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
            ];

            $groups = $me->isAdmin()
                ? [
                    'Administration' => $administration,
                ]
                : [
                    'Main menu' => [
                        ['route' => 'mswdo.dashboard',           'module' => 'dashboard',     'label' => 'Dashboard',           'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                        ['route' => 'mswdo.beneficiaries.index', 'module' => 'beneficiaries', 'label' => 'Beneficiaries',       'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                        ['route' => 'mswdo.requests.index',      'module' => 'requests',      'label' => 'Assistance Requests', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        ['route' => 'mswdo.approvals.index',     'module' => 'approvals',     'label' => 'Approvals',           'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                    ],
                    'Funds & records' => [
                        ['route' => 'mswdo.budget.index',        'module' => 'budget',        'label' => 'Budget Tracking',     'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['route' => 'mswdo.reports.index',       'module' => 'reports',       'label' => 'Reports',             'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ],
                ];

            // Officers administer staff logins as well as running the welfare
            // programme, so they get the Administration group too. Keyed off
            // the same predicate the route guard uses, so the nav can never
            // offer a page the middleware would then refuse.
            if (! $me->isAdmin() && $me->canAdministerAccounts()) {
                $groups['Administration'] = $administration;
            }
        @endphp

        @foreach ($groups as $heading => $items)
            @php
                $items = array_filter($items, fn ($item) => $item['module'] === null || $me->canAccess($item['module']));
            @endphp

            {{-- A group whose every entry was filtered out prints nothing at
                 all, rather than a heading with no list under it. --}}
            @continue(empty($items))

            <p class="rail-section">{{ $heading }}</p>

            @foreach ($items as $item)
                @php
                    $pattern = $item['route'] === 'mswdo.dashboard'
                        ? 'mswdo.dashboard'
                        : preg_replace('/\.index$/', '.*', $item['route']);
                    $active = request()->routeIs($pattern);
                @endphp
                <a href="{{ route($item['route']) }}"
                   @if($active) aria-current="page" @endif
                   class="rail-link {{ $active ? 'is-active' : '' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                    </svg>
                    <span class="truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach
        @endforeach
    </nav>

    {{-- Signed-in user --}}
    @php
        // First letters of the name, for the avatar disc. Falls back to the
        // first character so a single-word name still renders something.
        $initials = collect(preg_split('/\s+/', trim($me->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: mb_strtoupper(mb_substr($me->name, 0, 1));
    @endphp
    <div class="rail-user">
        <span class="rail-avatar" aria-hidden="true">{{ $initials }}</span>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-bold text-gray-900">{{ $me->name }}</p>
            <p class="truncate text-[11px] font-medium capitalize text-gray-500">
                {{ $me->isAdmin() ? 'Administrator' : str_replace('_', ' ', $me->role) }}
            </p>
        </div>
        {{-- Read-only users get told so once, here, rather than discovering it
             each time an action fails. --}}
        @if($me->view_only)
            <span class="shrink-0 rounded-full bg-yellow-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-yellow-800 ring-1 ring-inset ring-yellow-300"
                  title="You can view records but not change them">
                View
            </span>
        @endif
    </div>
</aside>
