@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Form Inspeksi')

@section('content')
    <div class="card shadow-none border mb-24">
        <div class="card-header"><h6 class="mb-0">Target Inspeksi</h6></div>
        <div class="card-body">
            <table class="table table-borderless mb-0">
                <tr><th width="160">Kode</th><td>{{ $target->code }}</td></tr>
                <tr><th>Nama</th><td>{{ $target->name }}</td></tr>
                <tr><th>Template Checklist</th><td>{{ $template->name }}</td></tr>
            </table>
        </div>
    </div>

    <form action="{{ route('emergency-response.inspection.store') }}" method="POST" enctype="multipart/form-data" id="inspection-form">
        @csrf
        <input type="hidden" name="target_type" value="{{ $targetType }}">
        <input type="hidden" name="target_id" value="{{ $target->id }}">
        <input type="hidden" name="checklist_template_id" value="{{ $template->id }}">
        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">
        <input type="hidden" name="signature_data" id="signature_data">

        <div class="card shadow-none border mb-24">
            <div class="card-header"><h6 class="mb-0">Observasi Umum</h6></div>
            <div class="card-body row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Kondisi Hasil Observasi</label>
                    <select name="condition_result" class="form-control">
                        <option value="">-- Pilih --</option>
                        @foreach ($conditions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Catatan Umum</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <span class="text-secondary-light text-sm" id="geo-status">Lokasi GPS: mendeteksi...</span>
                </div>
            </div>
        </div>

        <div class="card shadow-none border mb-24">
            <div class="card-header"><h6 class="mb-0">Checklist</h6></div>
            <div class="card-body">
                @foreach ($template->items as $index => $item)
                    <div class="border rounded p-16 mb-16">
                        <input type="hidden" name="items[{{ $index }}][checklist_template_item_id]" value="{{ $item->id }}">
                        <input type="hidden" name="items[{{ $index }}][item_text]" value="{{ $item->item_text }}">
                        <input type="hidden" name="items[{{ $index }}][answer_type]" value="{{ $item->answer_type }}">
                        <p class="fw-semibold mb-8">{{ $index + 1 }}. {{ $item->item_text }} @if($item->is_required)<span class="text-danger">*</span>@endif</p>

                        @if ($item->answer_type === 'compliance')
                            <div class="d-flex gap-3 mb-8">
                                @foreach ($complianceValues as $value => $label)
                                    <div class="form-check">
                                        <input type="radio" name="items[{{ $index }}][answer_value]" value="{{ $value }}" class="form-check-input" id="item-{{ $index }}-{{ $value }}" @required($item->is_required)>
                                        <label class="form-check-label" for="item-{{ $index }}-{{ $value }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @elseif ($item->answer_type === 'measurement')
                            <input type="number" step="any" name="items[{{ $index }}][answer_value]" class="form-control mb-8" style="max-width: 200px;" placeholder="Nilai pengukuran" @required($item->is_required)>
                        @else
                            <textarea name="items[{{ $index }}][answer_value]" class="form-control mb-8" rows="2" @required($item->is_required)></textarea>
                        @endif

                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <textarea name="items[{{ $index }}][notes]" class="form-control" rows="1" placeholder="Catatan inspector (opsional)"></textarea>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label text-sm mb-1">Foto Sebelum</label>
                                <input type="file" name="items[{{ $index }}][photo_before]" class="form-control form-control-sm" accept="image/*" capture="environment">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label text-sm mb-1">Foto Sesudah</label>
                                <input type="file" name="items[{{ $index }}][photo_after]" class="form-control form-control-sm" accept="image/*" capture="environment">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card shadow-none border mb-24">
            <div class="card-header"><h6 class="mb-0">Tanda Tangan Inspector</h6></div>
            <div class="card-body">
                <canvas id="signature-pad" width="400" height="150" style="border: 1px solid #ccc; touch-action: none; max-width: 100%;"></canvas>
                <div class="mt-8">
                    <button type="button" id="clear-signature" class="btn btn-sm btn-outline-secondary">Hapus Tanda Tangan</button>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">Simpan Draft</button>
            <button type="submit" name="action" value="submit" class="btn btn-primary-600">Submit Inspeksi</button>
            <a href="{{ route('emergency-response.inspection.pick-target') }}" class="btn btn-outline-danger ms-auto">Batal</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            var latEl = document.getElementById('latitude');
            var lngEl = document.getElementById('longitude');
            var statusEl = document.getElementById('geo-status');

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function (pos) {
                    latEl.value = pos.coords.latitude;
                    lngEl.value = pos.coords.longitude;
                    statusEl.textContent = 'Lokasi GPS: ' + pos.coords.latitude.toFixed(5) + ', ' + pos.coords.longitude.toFixed(5);
                }, function () {
                    statusEl.textContent = 'Lokasi GPS: tidak tersedia (izin ditolak/tidak didukung).';
                });
            } else {
                statusEl.textContent = 'Lokasi GPS: tidak didukung browser ini.';
            }

            var canvas = document.getElementById('signature-pad');
            var ctx = canvas.getContext('2d');
            var drawing = false;

            function pos(e) {
                var rect = canvas.getBoundingClientRect();
                var point = e.touches ? e.touches[0] : e;
                return { x: (point.clientX - rect.left) * (canvas.width / rect.width), y: (point.clientY - rect.top) * (canvas.height / rect.height) };
            }

            function start(e) { drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
            function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
            function end() { drawing = false; }

            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            window.addEventListener('mouseup', end);
            canvas.addEventListener('touchstart', start);
            canvas.addEventListener('touchmove', move);
            canvas.addEventListener('touchend', end);

            document.getElementById('clear-signature').addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            });

            document.getElementById('inspection-form').addEventListener('submit', function () {
                document.getElementById('signature_data').value = canvas.toDataURL('image/png');
            });
        })();
    </script>
@endpush
