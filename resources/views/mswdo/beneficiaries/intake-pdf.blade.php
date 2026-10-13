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

        table.sign { width: 100%; margin-top: 34px; border-collapse: collapse; }
        table.sign td { width: 50%; text-align: center; padding-top: 22px; }
        .sig { display: inline-block; width: 75%; border-top: 1px solid #333; padding-top: 2px; }
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

    <table class="fields">
        <tr>
            <td class="label">Name of Client:</td>
            <td class="line" style="width:30%">{!! $v($client->last_name . ($client->suffix ? ' ' . $client->suffix : '')) !!}</td>
            <td class="line" style="width:30%">{!! $v($client->first_name) !!}</td>
            <td class="line" style="width:30%">{!! $v($client->middle_name) !!}</td>
        </tr>
        <tr>
            <td></td>
            <td class="hint">(Last Name)</td>
            <td class="hint">(First Name)</td>
            <td class="hint">(Middle Name)</td>
        </tr>
    </table>

    <table class="fields">
        <tr>
            <td class="label">Birth Date:</td>
            <td class="line">{!! $v($client->date_of_birth?->format('F d, Y')) !!}</td>
            <td class="label">Age:</td>
            <td class="line" style="width:8%">{!! $v($client->date_of_birth?->age) !!}</td>
            <td class="label">Sex:</td>
            <td class="line" style="width:10%">{!! $v($client->sex) !!}</td>
            <td class="label">Civil Status:</td>
            <td class="line">{!! $v($client->civil_status) !!}</td>
        </tr>
    </table>
    <table class="fields">
        <tr>
            <td class="label">Religion:</td>
            <td class="line" style="width:40%">{!! $v($client->religion) !!}</td>
            <td class="label">Educational Attainment:</td>
            <td class="line">{!! $v($client->educational_attainment) !!}</td>
        </tr>
        <tr>
            <td class="label">Birth Place:</td>
            <td class="line">{!! $v($client->birth_place) !!}</td>
            <td class="label">Occupation:</td>
            <td class="line">{!! $v($client->occupation) !!}</td>
        </tr>
        <tr>
            <td class="label">Income:</td>
            <td class="line">{!! $client->monthly_income !== null ? 'PHP ' . number_format((float) $client->monthly_income, 2) . ' / month' : '&nbsp;' !!}</td>
            <td class="label">CP#:</td>
            <td class="line">{!! $v($client->contact_number) !!}</td>
        </tr>
    </table>
    <table class="fields">
        <tr>
            <td class="label">Address: Purok</td>
            <td class="line" style="width:8%">{!! $v($client->purok) !!}</td>
            <td class="line" style="width:30%">{!! $v($client->barangay) !!}</td>
            <td class="label">, San Agustin, Isabela</td>
            <td class="label">&nbsp;&nbsp;Category:</td>
            <td class="line">{!! $v($client->category) !!}</td>
        </tr>
    </table>

    <h2>B. Family Composition</h2>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:24%">Name</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Civil Status</th>
                <th>Birth-date</th>
                <th>Relationship to client</th>
                <th>Educational Attainment</th>
                <th>Occupation/ Income</th>
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
                    <td>{{ $m->occupation_income }}</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < $blankRows; $i++)
                <tr>@for ($c = 0; $c < 8; $c++)<td>&nbsp;</td>@endfor</tr>
            @endfor
        </tbody>
    </table>

    <h2>C. Additional Income:</h2>
    <p style="margin: 2px 0 4px">1. Nature of Employment:
        @foreach (\App\Models\Client::EMPLOYMENT_NATURES as $nature)
            <span class="box">{{ $client->employment_nature === $nature ? 'X' : '' }}</span>{{ $nature }}
        @endforeach
    </p>
    <p style="margin: 6px 0 2px">2. Describing housing &amp; other condition:</p>
    <table class="fields">
        <tr><td class="label">A. Housing (structure, materials space):</td><td class="line">{!! $v($client->housing) !!}</td></tr>
        <tr><td class="label">B. Use and source of potable water:</td><td class="line">{!! $v($client->water_source) !!}</td></tr>
        <tr><td class="label">C. Toilet:</td><td class="line">{!! $v($client->toilet) !!}</td></tr>
        <tr><td class="label">D. Light:</td><td class="line">{!! $v($client->light) !!}</td></tr>
    </table>

    @foreach ([
        ['D. Problem Presented:',      $client->problem_presented],
        ['E. Initial Assessment:',     $client->initial_assessment],
        ['F. Recommendation:',         $client->recommendation],
        ['G. Plan of Action/Taken:',   $client->plan_of_action],
    ] as [$label, $value])
        <h2>{{ $label }}</h2>
        <div class="notes">{{ $value }}</div>
    @endforeach

    <table class="sign">
        <tr>
            <td>&nbsp;<br><span class="sig">Social Worker</span></td>
            <td>{{ $client->full_name }}<br><span class="sig">Signature over printed name of client</span></td>
        </tr>
    </table>
</body>
</html>
