<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label {{ $device->code }}</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 24px; }
        .label { display: inline-block; border: 1px dashed #999; padding: 16px; border-radius: 8px; }
        .label img { width: 180px; height: 180px; }
        .label h3 { margin: 8px 0 0; }
        .label p { margin: 2px 0; color: #555; }
        .no-print { margin-top: 16px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="label">
        <img src="{{ route('emergency-response.safety-device.qr', $device) }}" alt="QR Code">
        <h3>{{ $device->code }}</h3>
        <p>{{ $device->name }}</p>
        <p>{{ $device->site->name ?? '' }} @if($device->location) - {{ $device->location->name }} @endif</p>
    </div>
    <div class="no-print">
        <button onclick="window.print()">Cetak</button>
    </div>
</body>
</html>
