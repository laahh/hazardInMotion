<?php

declare(strict_types=1);

namespace App\Services\Dms;

/**
 * Kontrak baca data dashboard DMS — diimplementasi oleh
 * DmsAlertMonitoringDataReader supaya service bisa di-test tanpa mock class final.
 */
interface DmsDashboardDataSource
{
    public function isUp(): bool;

    /**
     * @return array{
     *     total:int, l1_reviewed:int, l1_confirmed:int, l1_dismissed:int, l1_belum:int,
     *     l2_reviewed:int, l2_confirmed:int, post_event_eligible:int
     * }
     */
    public function alertSummary(string $start, string $end): array;

    /**
     * @return list<string>
     */
    public function distinctAlertSids(string $start, string $end): array;

    public function unitsOperatingInRange(string $start, string $end): int;

    public function unitsOperatingNow(int $withinMinutes = 30): int;

    /**
     * @return list<array{hari:string, total:int, confirmed:int, dismissed:int, pending:int, operators:int}>
     */
    public function dailyAlertSeries(string $start, string $end): array;

    /**
     * @return list<array{nama_pelanggaran:string, total:int, confirmed:int, confirmation_rate:float}>
     */
    public function categoryQuadrant(string $start, string $end): array;

    /**
     * @return list<array{site:string, total:int, confirmed:int}>
     */
    public function alertsBySite(string $start, string $end, int $limit = 8): array;

    /**
     * @return list<array{kode_sid:string, nama:string, total:int, confirmed:int}>
     */
    public function alertsByOperator(string $start, string $end, int $limit = 20): array;

    /**
     * @return list<array{id_alert:string, kode_sid:string, nama:string, nama_pelanggaran:string, unit:string, site:string, waktu_deteksi:string|null, sudah_direview_l1:bool, l1_confirmed:bool|null}>
     */
    public function recentAlerts(string $start, string $end, int $limit = 10, bool $confirmedOnly = false): array;
}
