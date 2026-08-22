<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Read-only, live-backed by bcsid.bep_vw_safety_karyawan_aktif on the
 * hseautomation Postgres RDS (connection `pgsql_direct`). There is no local
 * `ohs_employees` table/cache anymore — every query below hits the remote
 * database directly, real-time. The global scope rewrites the query's FROM
 * clause to a derived table that aliases the view's Indonesian column names
 * to the English names the rest of OhsDashboard already expects
 * (emp_id/emp_name/team/etc.), so no other service needs to change.
 *
 * bep_vw_safety_karyawan_aktif (not crontable_bep_vw_m_karyawan_aktif, which
 * is a periodically-refreshed cron export with its own undocumented filter
 * that was silently dropping some active employees, e.g. HO staff) was
 * chosen after verifying it's a real live view, already pre-filtered to
 * status_karyawan = 'AKTIF', and has ~750 more rows (25,502 vs 24,750) that
 * include employees the cron table was missing. It doesn't expose a photo
 * URL column, so photo_url is always empty here.
 */
class Employee extends Model
{
    protected $connection = 'pgsql_direct';

    protected $table = 'ohs_employees_live';

    protected $primaryKey = 'emp_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope('live-hse-source', function (Builder $builder): void {
            $builder->getQuery()->from = DB::raw('('.self::liveSourceSql().') as ohs_employees_live');
        });
    }

    public static function liveSourceSql(): string
    {
        return "SELECT
                nik AS emp_id,
                kode_sid AS sid,
                nama AS emp_name,
                jabatan_struktural AS position,
                departement AS team,
                site_dedicated AS site_dedicated,
                nama_perusahaan AS company,
                '' AS photo_url
            FROM bcsid.bep_vw_safety_karyawan_aktif
            WHERE status_karyawan = 'AKTIF'";
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'emp_id', 'emp_id');
    }

    public function backupLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'backup_emp_id', 'emp_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'EmpId' => $this->emp_id,
            'SID' => $this->sid ?? '',
            'EmpName' => $this->emp_name,
            'Position' => $this->position ?? '',
            'Team' => $this->team ?? '',
            'SiteDedicated' => $this->site_dedicated ?? '',
            'Company' => $this->company ?? '',
            'PhotoUrl' => $this->photo_url ?? '',
        ];
    }
}
