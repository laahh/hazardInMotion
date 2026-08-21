<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #27851f; color: #fff; }
    </style>
</head>
<body>
    <h2>Reminder Due Date Project & Issue Tracker</h2>
    <table>
        <tr>
            <th>Sisa Hari</th>
            <th>Tipe</th>
            <th>Project/Issue</th>
            <th>Item</th>
            <th>PIC</th>
            <th>Due Date</th>
            <th>% Complete</th>
        </tr>
        @foreach($items as $item)
            <tr>
                <td>{{ $item['sisaHari'] }}</td>
                <td>{{ $item['tipe'] }}</td>
                <td>{{ $item['projectIssue'] }}</td>
                <td>{{ $item['item'] }}</td>
                <td>{{ $item['pic'] }}</td>
                <td>{{ $item['dueDate'] }}</td>
                <td>{{ $item['percent'] }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
