<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-size: 10px;
            font-family: DejaVu Sans, sans-serif;
        }

        h3 {
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: top;
        }

        th {
            background: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }

        td {
            text-align: center;
        }

        td.text-left {
            text-align: left;
        }

        .muted {
            font-size: 9px;
            color: #555;
        }
    </style>
</head>
<body>

<h3>Operators Register</h3>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Operator</th>
            <th>Phone</th>
            <th>ID</th>
            <th>Type</th>
            <th>Status</th>
            <th>Introduced By</th>
            <th>Stage</th>
            <th>Chair</th>
            <th>Vehicle</th>
            <th>Owner</th>
            <th>Date Added</th>
        </tr>
    </thead>
    <tbody>
    @foreach($operators as $i => $o)
        <tr>
            <td>{{ $i + 1 }}</td>

            <td class="text-left">
                <strong>{{ $o->operator_name }}</strong>
            </td>

            <td>{{ $o->operator_phone }}</td>
            <td>{{ $o->operator_national_id }}</td>

            <td>{{ ucfirst($o->operator_type) }}</td>
            <td>{{ ucfirst($o->operator_status) }}</td>

            <td class="text-left">
                {{ $o->introduced_by_member_name ?? '-' }}
                @if(!empty($o->introduced_by_member_phone))
                    <div class="muted">
                        {{ $o->introduced_by_member_phone }}
                    </div>
                @endif
            </td>

            <td>{{ $o->stage_name ?? '-' }}</td>

            <td class="text-left">
                {{ $o->stage_chair_name ?? '-' }}
                @if(!empty($o->stage_chair_phone))
                    <div class="muted">
                        {{ $o->stage_chair_phone }}
                    </div>
                @endif
            </td>

            <td>
                {{ $o->vehicles_registration_number ?? '-' }}
                @if(!empty($o->vehicle_status))
                    <div class="muted">
                        {{ $o->vehicle_status }}
                    </div>
                @endif
            </td>

            <td class="text-left">
                {{ $o->vehicle_owner_name ?? '-' }}
                @if(!empty($o->vehicle_owner_phone))
                    <div class="muted">
                        {{ $o->vehicle_owner_phone }}
                    </div>
                @endif
            </td>

            <td>
                @if(!empty($o->operator_created_at))
                    {{ \Carbon\Carbon::parse($o->operator_created_at)->format('d M Y') }}
                @else
                    -
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>
