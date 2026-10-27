<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SidRosterBannedMaster extends Model
{
    /** aturan_pelanggaran: periode kerja > 70 hari on-site (banned 14 hari). */
    public const ATURAN_ONSITE = 'ONSITE_70';

    /** aturan_pelanggaran: bekerja > 13 hari berturut-turut (banned 1 hari off). */
    public const ATURAN_BERUNTUN = 'BERUNTUN_13';

    /** @var array<string, string> kode aturan => label tampilan */
    public const LABEL_ATURAN = [
        self::ATURAN_ONSITE => '> 70 hari on-site',
        self::ATURAN_BERUNTUN => '> 13 hari beruntun',
    ];

    protected $table = 'sid_roster_banned_master';

    protected $fillable = [
        'nik',
        'sid',
        'nama',
        'perusahaan',
        'site_dedicated',
        'alasan_pelanggaran',
        'aturan_pelanggaran',
        'lama_banned_hari',
        'tanggal_pelanggaran',
        'created_by',
    ];

    private static ?bool $adaKolomAturan = null;

    /**
     * Sudahkah kolom aturan_pelanggaran / lama_banned_hari dimigrasi
     * (2026_10_28_110000, dijalankan manual). Sebelum itu kode tidak menulis
     * atau menyaring kolom tersebut.
     */
    public static function adaKolomAturan(): bool
    {
        if (self::$adaKolomAturan === null) {
            try {
                self::$adaKolomAturan = \Illuminate\Support\Facades\Schema::hasColumn('sid_roster_banned_master', 'aturan_pelanggaran');
            } catch (\Throwable) {
                self::$adaKolomAturan = false;
            }
        }

        return self::$adaKolomAturan;
    }

    protected $casts = [
        'tanggal_pelanggaran' => 'date',
    ];

    public function treatmentEvidences(): HasMany
    {
        return $this->hasMany(SidRosterTreatmentEvidence::class, 'master_id');
    }
}
