<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 4px; }
        th { background: #f2f2f2; }
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
</tr>
</thead>
<tbody>
@foreach($operators as $i => $o)
<tr>
    <td>{{ $i + 1 }}</td>
    <td>{{ $o->full_name }}</td>
    <td>{{ $o->phone }}</td>
    <td>{{ $o->national_id }}</td>
    <td>{{ $o->operator_type }}</td>
    <td>{{ $o->status }}</td>
    <td>{{ $o->introduced_by_member }}</td>
    <td>{{ $o->stage_name }}</td>
    <td>{{ $o->stage_chair }}</td>
    <td>{{ $o->vehicle }}</td>
    <td>{{ $o->vehicle_owner }}</td>
</tr>
@endforeach
</tbody>
</table>

</body>
</html>
