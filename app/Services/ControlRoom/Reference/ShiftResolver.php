<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Reference;

use App\Enums\ControlRoomShiftCode;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Menentukan shift dan "tanggal efektif" dari sebuah timestamp.
 *
 * Aturan crosses_midnight (plan-OCR.md T1.1): laporan jam 01:00 tanggal 28
 * masuk Shift 2 TANGGAL 27, bukan Shift 1 tanggal 28 — karena Shift 2 dimulai
 * malam sebelumnya dan belum berakhir. effectiveDate() menerapkan aturan ini;
 * resolve() hanya menentukan kode shift-nya saja (sesuai signature di plan).
 */
final class ShiftResolver
{
    public function resolve(CarbonInterface $timestamp): ControlRoomShiftCode
    {
        $time = $timestamp->format('H:i:s');

        foreach (ControlRoomShiftCode::cases() as $shift) {
            if ($this->timeIsWithinShift($time, $shift)) {
                return $shift;
            }
        }

        // Config start/end bercelah (bukan tutup 24 jam) — tidak boleh terjadi
        // kalau config valid, tapi jangan biarkan resolve() melempar exception
        // untuk kasus data kotor. Pilih shift pertama sebagai fallback.
        return ControlRoomShiftCode::cases()[0];
    }

    public function effectiveDate(CarbonInterface $timestamp): CarbonImmutable
    {
        $date = CarbonImmutable::parse($timestamp)->startOfDay();
        $shift = $this->resolve($timestamp);
        $time = $timestamp->format('H:i:s');

        if ($shift->crossesMidnight() && $time < $shift->start()) {
            return $date->subDay();
        }

        return $date;
    }

    /**
     * Dipakai T3.3 (absen): "hanya dalam rentang shift ±2 jam". $shift di
     * sini adalah shift yang ingin DICEK (bukan hasil resolve($now)) — jadi
     * effectiveDate() tidak bisa dipakai langsung (itu menjawab pertanyaan
     * berbeda: shift apa yang paling cocok untuk $now). Sebagai gantinya,
     * coba dua kandidat tanggal mulai shift (hari ini & kemarin) dan terima
     * kalau $now jatuh di salah satu jendelanya — ini yang membuat laporan
     * lewat tengah malam (mis. jam 01:00) tetap terhitung masuk jendela
     * Shift 2 yang mulai malam SEBELUMNYA.
     */
    public function isWithinShiftWindow(CarbonInterface $now, ControlRoomShiftCode $shift, int $windowHours = 2): bool
    {
        $now = CarbonImmutable::parse($now);

        foreach ([$now->toDateString(), $now->subDay()->toDateString()] as $candidateStartDate) {
            $start = CarbonImmutable::parse($candidateStartDate.' '.$shift->start());
            $end = CarbonImmutable::parse($candidateStartDate.' '.$shift->end());

            if ($shift->crossesMidnight()) {
                $end = $end->addDay();
            }

            if ($now->between($start->subHours($windowHours), $end->addHours($windowHours))) {
                return true;
            }
        }

        return false;
    }

    private function timeIsWithinShift(string $time, ControlRoomShiftCode $shift): bool
    {
        $start = $shift->start();
        $end = $shift->end();

        if (! $shift->crossesMidnight()) {
            return $time >= $start && $time < $end;
        }

        return $time >= $start || $time < $end;
    }
}
