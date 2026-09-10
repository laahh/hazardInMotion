@php
    $weekInputId = $weekInputId ?? 'ocr-iso-week';
@endphp
<div>
    <label for="{{ $weekInputId }}">Minggu (Minggu–Sabtu)</label>
    <input
        type="week"
        name="iso_week"
        id="{{ $weekInputId }}"
        class="form-control"
        value="{{ $isoWeekValue }}"
        onchange="this.form.submit()"
    >
</div>
<div>
    <label>Periode</label>
    <div class="ocr-week-stepper">
        <a href="{{ $prevWeekUrl }}" aria-label="Minggu sebelumnya">
            <i class="ri-arrow-left-s-line"></i>
        </a>
        <div class="ocr-week-label">
            <strong>{{ $weekRangeLabel }}</strong>
            <span>Minggu {{ $week }} · {{ $year }} · Minggu–Sabtu</span>
        </div>
        <a href="{{ $nextWeekUrl }}" aria-label="Minggu berikutnya">
            <i class="ri-arrow-right-s-line"></i>
        </a>
    </div>
</div>
