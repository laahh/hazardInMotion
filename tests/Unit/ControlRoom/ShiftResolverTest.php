<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Services\ControlRoom\Reference\ShiftResolver;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class ShiftResolverTest extends TestCase
{
    private ShiftResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ShiftResolver();
    }

    public function test_tengah_shift_1_terdeteksi_benar(): void
    {
        $timestamp = CarbonImmutable::parse('2026-01-27 10:00:00');

        $this->assertSame(ControlRoomShiftCode::S1, $this->resolver->resolve($timestamp));
        $this->assertTrue($this->resolver->effectiveDate($timestamp)->isSameDay(CarbonImmutable::parse('2026-01-27')));
    }

    public function test_tengah_shift_2_terdeteksi_benar(): void
    {
        $timestamp = CarbonImmutable::parse('2026-01-27 22:00:00');

        $this->assertSame(ControlRoomShiftCode::S2, $this->resolver->resolve($timestamp));
        $this->assertTrue($this->resolver->effectiveDate($timestamp)->isSameDay(CarbonImmutable::parse('2026-01-27')));
    }

    public function test_tepat_jam_pergantian_shift_1_ke_shift_2(): void
    {
        $timestamp = CarbonImmutable::parse('2026-01-27 18:00:00');

        $this->assertSame(ControlRoomShiftCode::S2, $this->resolver->resolve($timestamp));
        $this->assertTrue($this->resolver->effectiveDate($timestamp)->isSameDay(CarbonImmutable::parse('2026-01-27')));
    }

    public function test_tepat_jam_pergantian_shift_2_ke_shift_1(): void
    {
        $timestamp = CarbonImmutable::parse('2026-01-28 06:00:00');

        $this->assertSame(ControlRoomShiftCode::S1, $this->resolver->resolve($timestamp));
        $this->assertTrue($this->resolver->effectiveDate($timestamp)->isSameDay(CarbonImmutable::parse('2026-01-28')));
    }

    public function test_laporan_lewat_tengah_malam_masuk_shift_2_tanggal_sebelumnya(): void
    {
        $timestamp = CarbonImmutable::parse('2026-01-28 01:00:00');

        $this->assertSame(ControlRoomShiftCode::S2, $this->resolver->resolve($timestamp));
        $this->assertTrue($this->resolver->effectiveDate($timestamp)->isSameDay(CarbonImmutable::parse('2026-01-27')));
    }

    public function test_tepat_tengah_malam_masuk_shift_2_tanggal_sebelumnya(): void
    {
        $timestamp = CarbonImmutable::parse('2026-01-28 00:00:00');

        $this->assertSame(ControlRoomShiftCode::S2, $this->resolver->resolve($timestamp));
        $this->assertTrue($this->resolver->effectiveDate($timestamp)->isSameDay(CarbonImmutable::parse('2026-01-27')));
    }

    public function test_absen_di_dalam_jendela_shift_2_jam_diterima(): void
    {
        // Shift 1: 06:00-18:00. Jam 05:00 (1 jam sebelum mulai) masih dalam jendela ±2 jam.
        $timestamp = CarbonImmutable::parse('2026-01-27 05:00:00');

        $this->assertTrue($this->resolver->isWithinShiftWindow($timestamp, ControlRoomShiftCode::S1));
    }

    public function test_absen_di_luar_jendela_shift_ditolak(): void
    {
        // Shift 1: 06:00-18:00. Jam 21:00 sudah lebih dari 2 jam setelah shift berakhir (18:00).
        $timestamp = CarbonImmutable::parse('2026-01-27 21:00:00');

        $this->assertFalse($this->resolver->isWithinShiftWindow($timestamp, ControlRoomShiftCode::S1));
    }

    public function test_absen_lewat_tengah_malam_tetap_dalam_jendela_shift_2_yang_benar(): void
    {
        // Shift 2 dimulai 2026-01-27 18:00, berakhir 2026-01-28 06:00.
        // Jam 01:00 tanggal 28 harus dianggap masih di dalam jendela shift itu
        // (bukan dibandingkan terhadap Shift 2 tanggal 28 yang belum mulai).
        $timestamp = CarbonImmutable::parse('2026-01-28 01:00:00');

        $this->assertTrue($this->resolver->isWithinShiftWindow($timestamp, ControlRoomShiftCode::S2));
    }
}
