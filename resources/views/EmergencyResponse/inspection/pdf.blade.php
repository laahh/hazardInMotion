<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $inspection->inspection_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #222; }
        h2 { margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        td, th { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th.label { width: 180px; background: #f4f4f4; }
        .non-compliant { background: #fde2e2; }
    </style>
</head>
<body>
    <h2>Laporan Inspeksi {{ $inspection->inspection_number }}</h2>
    <p>Status: {{ $inspection->statusLabel() }}</p>

    <table>
        <tr><th class="label">Target</th><td>{{ $inspection->target->name ?? '-' }} ({{ $inspection->target->code ?? '-' }})</td></tr>
        <tr><th class="label">Site</th><td>{{ $inspection->site->name ?? '-' }}</td></tr>
        <tr><th class="label">Template Checklist</th><td>{{ $inspection->checklistTemplate->name ?? '-' }}</td></tr>
        <tr><th class="label">Inspector</th><td>{{ $inspection->inspector->name ?? '-' }}</td></tr>
        <tr><th class="label">Waktu Inspeksi</th><td>{{ optional($inspection->inspected_at)->format('d M Y H:i') ?? '-' }}</td></tr>
        <tr><th class="label">Kondisi Hasil Observasi</th><td>{{ $inspection->condition_result ?? '-' }}</td></tr>
        <tr><th class="label">Catatan</th><td>{{ $inspection->notes ?: '-' }}</td></tr>
    </table>

    <h3>Hasil Checklist</h3>
    <table>
        <thead>
            <tr><th>#</th><th>Item</th><th>Jawaban</th><th>Catatan</th></tr>
        </thead>
        <tbody>
            @foreach ($inspection->results as $i => $result)
                <tr class="{{ $result->isNonCompliant() ? 'non-compliant' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $result->item_text_snapshot }}</td>
                    <td>
                        @if ($result->answer_type_snapshot === 'compliance')
                            {{ \App\Models\EmergencyResponse\Inspection\InspectionResult::COMPLIANCE_VALUES[$result->answer_value] ?? $result->answer_value }}
                        @else
                            {{ $result->answer_value ?: '-' }}
                        @endif
                    </td>
                    <td>{{ $result->notes ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($inspection->findings->isNotEmpty())
        <h3>Temuan</h3>
        <table>
            <thead><tr><th>Deskripsi</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($inspection->findings as $finding)
                    <tr><td>{{ $finding->description }}</td><td>{{ $finding->statusLabel() }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
