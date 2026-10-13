{{--
    Downloadable PDF of a report, rendered by Dompdf on the official MSWDO
    letterhead: municipal seal left, DSWD logo right, the centred government
    heading, the script office name, and the Nuang Festival mascot as a faint
    watermark on every page.

    Dompdf has no flexbox or grid, so the letterhead is laid out with a table.
    Images are read from disk (public_path) rather than by URL so the export
    works without the server fetching from itself.
--}}
@php
    $numericColumns = match ($type) {
        'recipient' => [3, 4],
        'budget' => [2, 3, 4, 5],
        default => [],
    };
    $dateFrom = request('date_from');
    $dateTo = request('date_to');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }} Report</title>
    <style>
        @font-face {
            font-family: 'Pinyon Script';
            font-style: normal;
            font-weight: normal;
            src: url('{{ resource_path('fonts/PinyonScript-Regular.ttf') }}') format('truetype');
        }

        @page { margin: 12mm 14mm 16mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            color: #111;
        }

        .watermark {
            position: fixed;
            top: 32%;
            left: 15%;
            width: 70%;
            opacity: 0.12;
            z-index: -1000;
        }

        .letterhead { width: 100%; border-collapse: collapse; }
        .letterhead td { vertical-align: middle; padding: 0; }
        .letterhead .logo { width: 90px; }
        .letterhead .logo img { height: 78px; }
        .letterhead .logo-right { text-align: right; }
        .letterhead .heading { text-align: center; color: #2e7d32; line-height: 1.35; }
        .heading p { margin: 0; font-size: 11.5pt; font-weight: bold; }
        .heading .lgu { font-size: 14pt; }
        .heading .sep { font-size: 8pt; font-weight: normal; }

        .office {
            margin: 6px 0 0;
            text-align: center;
            font-family: 'Pinyon Script', serif;
            font-size: 23pt;
            color: #2e7d32;
        }

        .title { margin: 22px 0 2px; text-align: center; font-size: 12.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .meta { margin: 0 0 12px; text-align: center; font-size: 8.5pt; color: #444; }

        /* Sized so the seven-column recipient report fits A4 portrait. */
        table.data { width: 100%; border-collapse: collapse; font-size: 8pt; }
        table.data th {
            background: #e8f5e9;
            color: #1b5e20;
            font-size: 7pt;
            text-align: left;
            text-transform: uppercase;
            padding: 5px 5px;
            border: 1px solid #a5d6a7;
        }
        table.data td { padding: 4px 5px; border: 1px solid #cfd8dc; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        .num { text-align: right; }
        .empty { text-align: center; padding: 18px; color: #555; }
        .total td { font-weight: bold; background: #f1f8e9; }
    </style>
</head>
<body>
    <img class="watermark" src="{{ public_path('images/nuang-festival.jpg') }}" alt="">

    <table class="letterhead">
        <tr>
            <td class="logo"><img src="{{ public_path('images/sanagustinlogo.png') }}" alt="San Agustin seal"></td>
            <td class="heading">
                <p>Republic of the Philippines</p>
                <p>Province of Isabela</p>
                <p class="lgu">MUNICIPALITY OF SAN AGUSTIN</p>
                <p class="sep">-oOo-</p>
            </td>
            <td class="logo logo-right"><img src="{{ public_path('images/dswd.png') }}" alt="DSWD logo"></td>
        </tr>
    </table>

    <p class="office">Municipal Social Welfare &amp; Development Office</p>

    <p class="title">{{ $report['title'] }} Report</p>
    <p class="meta">
        {{ request('assistance_type') ?: 'All assistance types' }} ·
        @if ($dateFrom || $dateTo)
            {{ $dateFrom ?: 'start' }} to {{ $dateTo ?: 'present' }}
        @else
            All dates
        @endif
        · Generated {{ now()->format('M d, Y g:i A') }}
    </p>

    <table class="data">
        <thead>
            <tr>
                @foreach ($report['columns'] as $ci => $col)
                    <th class="{{ in_array($ci, $numericColumns) ? 'num' : '' }}">{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($row as $ci => $value)
                        <td class="{{ in_array($ci, $numericColumns) ? 'num' : '' }}">{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['columns']) }}" class="empty">No records match these filters.</td>
                </tr>
            @endforelse

            @if ($type === 'recipient' && count($report['rows']) > 0)
                @php
                    $totalReleased = collect($report['rows'])->sum(fn ($r) => (float) str_replace(',', '', $r[4] ?? '0'));
                @endphp
                <tr class="total">
                    <td colspan="4">{{ count($report['rows']) }} released {{ count($report['rows']) === 1 ? 'request' : 'requests' }}</td>
                    <td class="num">₱ {{ number_format($totalReleased, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            @endif
        </tbody>
    </table>

    @if (! empty($report['summary']))
        @php $summary = $report['summary']; @endphp
        <p class="title" style="margin-top: 18px; font-size: 10.5pt;">{{ $summary['title'] }}</p>
        <table class="data">
            <thead>
                <tr>
                    @foreach ($summary['columns'] as $ci => $col)
                        <th class="{{ in_array($ci, $summary['numeric']) ? 'num' : '' }}">{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['rows'] as $row)
                    <tr>
                        @foreach ($row as $ci => $value)
                            <td class="{{ in_array($ci, $summary['numeric']) ? 'num' : '' }}">{{ $value }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="total">
                    @foreach ($summary['total'] as $ci => $value)
                        <td class="{{ in_array($ci, $summary['numeric']) ? 'num' : '' }}">{{ $value }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
