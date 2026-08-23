<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $workOrder->work_order_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #222; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        td, th { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th.label { width: 200px; background: #f4f4f4; }
    </style>
</head>
<body>
    <h2>Work Order {{ $workOrder->work_order_number }}</h2>
    <p>Status: {{ $workOrder->statusLabel() }}</p>

    <table>
        <tr><th class="label">Equipment</th><td>{{ $workOrder->equipmentable->name ?? '-' }} ({{ $workOrder->equipmentable->code ?? '-' }})</td></tr>
        <tr><th class="label">Jenis Pekerjaan</th><td>{{ $workOrder->workTypeLabel() }}</td></tr>
        <tr><th class="label">Deskripsi</th><td>{{ $workOrder->description }}</td></tr>
        <tr><th class="label">Teknisi</th><td>{{ $workOrder->assignedTechnician->name ?? '-' }}</td></tr>
        <tr><th class="label">Vendor</th><td>{{ $workOrder->vendor->name ?? 'Internal' }}</td></tr>
        <tr><th class="label">Target</th><td>{{ optional($workOrder->target_start_at)->format('d M Y') ?? '-' }} s/d {{ optional($workOrder->target_end_at)->format('d M Y') ?? '-' }}</td></tr>
        <tr><th class="label">Hasil Pekerjaan</th><td>{{ $workOrder->result_notes ?: '-' }}</td></tr>
        <tr><th class="label">Biaya Aktual</th><td>{{ $workOrder->actual_cost ?? '-' }}</td></tr>
    </table>

    <h3>Spare Part</h3>
    <table>
        <thead><tr><th>Nama</th><th>Jumlah</th><th>Harga/Unit</th></tr></thead>
        <tbody>
            @forelse ($workOrder->spareParts as $usage)
                <tr><td>{{ $usage->sparePart->name ?? '-' }}</td><td>{{ $usage->quantity_used }}</td><td>{{ $usage->unit_cost_snapshot ?? '-' }}</td></tr>
            @empty
                <tr><td colspan="3">Tidak ada spare part.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
