<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Read-only, live-backed by bcsid.crontable_bep_vw_m_karyawan_aktif on the
 * hseautomation Postgres RDS (connection `pgsql_direct`). There is no local
 * `ohs_employees` table/cache anymore — every query below hits the remote
 * database directly, real-time. The global scope rewrites the query's FROM
 * clause to a derived table that aliases the view's Indonesian column names
 * to the English names the rest of OhsDashboard already expects
 * (emp_id/emp_name/team/etc.), so no other service needs to change.
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
                url_foto AS photo_url
            FROM bcsid.crontable_bep_vw_m_karyawan_aktif
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
