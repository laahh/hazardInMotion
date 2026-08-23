<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $incident->incident_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #222; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        td, th { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th.label { width: 200px; background: #f4f4f4; }
    </style>
</head>
<body>
    <h2>Laporan Insiden {{ $incident->incident_number }}</h2>
    <p>Status: {{ $incident->statusLabel() }}</p>

    <table>
        <tr><th class="label">Jenis Insiden</th><td>{{ $incident->incidentType->name ?? '-' }}</td></tr>
        <tr><th class="label">Keparahan / Prioritas</th><td>{{ $incident->severityLevel->name ?? '-' }} / {{ $incident->priorityLevel->name ?? '-' }}</td></tr>
        <tr><th class="label">Site / Lokasi</th><td>{{ $incident->site->name ?? '-' }} — {{ $incident->location_detail ?: '-' }}</td></tr>
        <tr><th class="label">Waktu Kejadian</th><td>{{ $incident->occurred_at->format('d M Y H:i') }}</td></tr>
        <tr><th class="label">Waktu Dilaporkan</th><td>{{ $incident->reported_at->format('d M Y H:i') }}</td></tr>
        <tr><th class="label">Deskripsi</th><td>{{ $incident->description }}</td></tr>
        <tr><th class="label">Jumlah Korban</th><td>{{ $incident->victim_count }}</td></tr>
        <tr><th class="label">Response Time</th><td>{{ $incident->responseTimeMinutes() ?? '-' }} menit</td></tr>
        <tr><th class="label">Handling Time</th><td>{{ $incident->handlingTimeMinutes() ?? '-' }} menit</td></tr>
    </table>

    <h3>Unit Respons</h3>
    <table>
        <thead><tr><th>Unit</th><th>Status</th><th>Berangkat</th><th>Tiba</th></tr></thead>
        <tbody>
            @forelse ($incident->responseUnits as $unit)
                <tr>
                    <td>{{ $unit->emergencyUnit->name ?? '-' }}</td>
                    <td>{{ $unit->statusLabel() }}</td>
                    <td>{{ optional($unit->departed_at)->format('d M Y H:i') ?? '-' }}</td>
                    <td>{{ optional($unit->arrived_at)->format('d M Y H:i') ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Tidak ada unit dikerahkan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Timeline</h3>
    <table>
        <thead><tr><th>Waktu</th><th>Kejadian</th></tr></thead>
        <tbody>
            @foreach ($incident->timeline->sortBy('created_at') as $entry)
                <tr><td>{{ $entry->created_at->format('d M Y H:i') }}</td><td>{{ $entry->description }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
