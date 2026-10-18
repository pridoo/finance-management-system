{{--
    The Family Intake Data Form, filled in, for printing and signing. Follows
    the office's paper form section for section (A–G) under the same MSWDO
    letterhead as the report PDFs. Dompdf has no flexbox or grid, so the layout
    is tables throughout.
--}}
@php
    $v = fn ($value) => filled($value) ? e($value) : '&nbsp;';
    $rows = $client->familyMembers;
    // The paper form has seven ruled rows; never print fewer.
    $blankRows = max(0, 7 - $rows->count());
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Family Intake Data Form — {{ $client->full_name }}</title>
    <style>
        @page { margin: 12mm 14mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #111; }

        .letterhead { width: 100%; border-collapse: collapse; }
        .letterhead td { vertical-align: middle; padding: 0; }
        .letterhead .logo { width: 70px; }
        .letterhead .logo img { height: 60px; }
        .letterhead .logo-right { text-align: right; }
        .letterhead .heading { text-align: center; color: #2e7d32; line-height: 1.3; }
        .heading p { margin: 0; font-size: 9.5pt; font-weight: bold; }
        .heading .lgu { font-size: 11.5pt; }

        h1 { margin: 14px 0 8px; text-align: center; font-size: 13pt; letter-spacing: .5px; }
        h2 { margin: 12px 0 5px; font-size: 9pt; text-transform: uppercase; }

        table.fields { width: 100%; border-collapse: collapse; }
        table.fields td { padding: 3px 4px 2px 0; vertical-align: bottom; }
        .label { white-space: nowrap; width: 1%; }
        .line { border-bottom: 1px solid #333; padding-left: 4px !important; }
        .hint { font-size: 6.5pt; color: #555; text-align: center; }

        table.grid { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        table.grid th { border: 1px solid #333; padding: 3px; font-size: 7pt; background: #f1f8e9; }
        table.grid td { border: 1px solid #333; padding: 3px; height: 13px; }

        .box { display: inline-block; width: 9px; height: 9px; border: 1px solid #333; text-align: center; line-height: 9px; font-size: 7pt; margin: 0 3px 0 10px; }
        .notes { border-bottom: 1px solid #333; min-height: 34px; padding: 2px 4px; white-space: pre-line; }

    </style>
</head>
<body>
    <table class="letterhead">
        <tr>
            <td class="logo"><img src="{{ public_path('images/sanagustinlogo.png') }}" alt=""></td>
            <td class="heading">
                <p>Republic of the Philippines</p>
                <p>Province of Isabela</p>
                <p class="lgu">MUNICIPALITY OF SAN AGUSTIN</p>
                <p>Municipal Social Welfare &amp; Development Office</p>
            </td>
            <td class="logo logo-right"><img src="{{ public_path('images/dswd.png') }}" alt=""></td>
        </tr>
    </table>

    <h1>FAMILY INTAKE DATA FORM</h1>

    <table class="fields">
        <tr>
            <td style="width:70%"></td>
            <td class="label">DATE:</td>
            <td class="line">{!! $v($client->intake_date?->format('F d, Y')) !!}</td>
        </tr>
    </table>

    {{-- A and B are laid out like the paper form: two columns of
         "Label : value" lines. --}}
    @php
        $peso = fn ($n) => $n !== null ? 'PHP ' . number_format((float) $n, 2) . ' / month' : null;
        $sections = [
            'A. SOURCE OF INFORMATION/REFERRING PARTY' => [
                [['Name', $client->referrer_name], ['Religion', $client->referrer_religion]],
                [['Age', $client->referrer_date_of_birth?->age], ['Educational Attainment', $client->referrer_educational_attainment]],
                [['Civil Status', $client->referrer_civil_status], ['Occupation', $client->referrer_occupation]],
                [['Sex', $client->referrer_sex], ['Present Address', $client->referrer_address]],
                [['Date of Birth', $client->referrer_date_of_birth?->format('F d, Y')], ['Income', $peso($client->referrer_income)]],
                [['Place of Birth', $client->referrer_birth_place], ['CP #', $client->referrer_contact_number]],
                [['', null], ['Relationship to Client', $client->referrer_relationship]],
            ],
            "B. CLIENT'S INFORMATION" => [
                [['Name', $client->full_name], ['Religion', $client->religion]],
                [['Age', $client->date_of_birth?->age], ['Educational Attainment', $client->educational_attainment]],
                [['Civil Status', $client->civil_status], ['Occupation', $client->occupation]],
                [['Sex', $client->sex], ['Present Address', $client->address]],
                [['Date of Birth', $client->date_of_birth?->format('F d, Y')], ['Income', $peso($client->monthly_income)]],
                [['Place of Birth', $client->birth_place], ['CP #', $client->contact_number]],
                [['Category', $client->category], ['Relationship to Client', $client->client_relationship]],
            ],
        ];
    @endphp

    @foreach ($sections as $heading => $lines)
        <h2>{{ $heading }}</h2>
        <table class="fields">
            @foreach ($lines as $pair)
                <tr>
                    @foreach ($pair as [$label, $value])
                        @if ($label === '')
                            <td colspan="2" style="width:50%"></td>
                        @else
                            <td class="label">{{ $label }}:</td>
                            <td class="line" style="width:{{ $loop->first ? '32%' : '30%' }}">{!! $v($value) !!}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endforeach

    <h2>C. Family Composition</h2>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:24%">Name</th>
                <th>Age</th>
                <th>Sex</th>
                <th>Civil Status</th>
                <th>Birth-date</th>
                <th>Relationship to client</th>
                <th>Educational Attainment</th>
                <th>Occupation</th>
                <th>Monthly Income</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $m)
                <tr>
                    <td>{{ $m->name }}</td>
                    <td>{{ $m->current_age }}</td>
                    <td>{{ $m->sex }}</td>
                    <td>{{ $m->civil_status }}</td>
                    <td>{{ $m->birth_date?->format('m/d/Y') }}</td>
                    <td>{{ $m->relationship }}</td>
                    <td>{{ $m->educational_attainment }}</td>
                    <td>{{ $m->occupation ?? $m->occupation_income }}</td>
                    <td>{{ $m->monthly_income !== null ? number_format((float) $m->monthly_income, 2) : '' }}</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < $blankRows; $i++)
                <tr>@for ($c = 0; $c < 9; $c++)<td>&nbsp;</td>@endfor</tr>
            @endfor
        </tbody>
    </table>
    <p style="margin: 4px 0 0; text-align: right;"><b>Total Family Income:</b> PHP {{ number_format($client->family_income, 2) }} / month</p>

    <h2>D. Additional Income:</h2>
    <p style="margin: 2px 0 4px">1. Nature of Employment:
        @foreach (\App\Models\Client::EMPLOYMENT_NATURES as $nature)
            <span class="box">{{ $client->employment_nature === $nature ? 'X' : '' }}</span>{{ $nature }}
        @endforeach
    </p>
    <p style="margin: 6px 0 2px">2. Describing housing &amp; other condition:</p>
    <table class="fields">
        <tr><td class="label">A. Housing (structure, materials space):</td><td class="line">{!! $v(collect([$client->housing_ownership, $client->housing_structure, $client->housing])->filter()->join(', ')) !!}</td></tr>
        <tr><td class="label">B. Use and source of potable water:</td><td class="line">{!! $v($client->water_source) !!}</td></tr>
        <tr><td class="label">C. Toilet Facilities:</td><td class="line">{!! $v($client->toilet) !!}</td></tr>
        <tr><td class="label">D. Household Light:</td><td class="line">{!! $v($client->light) !!}</td></tr>
    </table>

    @foreach ([
        ['E. Problem Presented:',      $client->problem_presented],
        ['F. Initial Assessment:',     $client->initial_assessment],
        ['G. Recommendation:',         $client->recommendation],
        ['H. Plan of Action/Taken:',   $client->plan_of_action],
    ] as [$label, $value])
        <h2>{{ $label }}</h2>
        <div class="notes">{{ $value }}</div>
    @endforeach

</body>
</html>
