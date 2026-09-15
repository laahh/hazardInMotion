<?php

declare(strict_types=1);

namespace App\Actions\Isc;

use App\Models\Isc\IscBoundaryEvent;
use App\Models\Isc\IscHazardReport;
use App\Models\User;
use App\Services\Isc\IscHazardEmployeeLookupService;
use App\Services\Isc\IscHazardSysUserLookupService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class IscHazardReportStoreAction
{
    public function __construct(
        private readonly IscHazardEmployeeLookupService $employees,
        private readonly IscHazardSysUserLookupService $sysUsers,
        private readonly IscInterventionStoreAction $interventions,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(User $user, array $payload, ?UploadedFile $foto = null): IscHazardReport
    {
        $pic = $this->resolvePerson(
            (string) ($payload['pic_sid'] ?? ''),
            $payload['pic_npk'] ?? null,
            $payload['pic_nama'] ?? null,
            $payload['pic_jabatan'] ?? null,
        );
        $pelapor = $this->resolvePelapor(
            (string) ($payload['sid_pelapor'] ?? ''),
            $payload['npk_pelapor'] ?? null,
            $payload['nama_pelapor'] ?? null,
            $payload['jabatan_pelapor'] ?? null,
            $payload['username'] ?? null,
            $payload['password'] ?? null,
        );

        $eventId = isset($payload['event_id']) ? (int) $payload['event_id'] : null;
        $isDemo = (bool) ($payload['demo'] ?? false) || ($eventId !== null && $eventId >= 9000);
        $tableReady = Schema::hasTable('isc_hazard_reports');

        $fotoPath = null;
        if ($foto instanceof UploadedFile && $tableReady && ! $isDemo) {
            $fotoPath = $foto->store('isc-hazard-reports', 'public');
        } elseif ($foto instanceof UploadedFile) {
            $fotoPath = 'demo/'.$foto->getClientOriginalName();
        }

        $attrs = [
            'event_id' => ($isDemo || $eventId === null || $eventId < 1) ? null : $eventId,
            'intervention_id' => null,
            'created_by' => $user->id,
            'username' => $pelapor['username'],
            'password' => $pelapor['password'],
            'perusahaan' => $payload['perusahaan'] ?? null,
            'pic_sid' => $pic['sid'] !== '' ? $pic['sid'] : null,
            'pic_npk' => $pic['npk'],
            'pic_nama' => $pic['nama'],
            'pic_jabatan' => $pic['jabatan'],
            'tools_pengamatan' => $payload['tools_pengamatan'] ?? 'Post Event - BeSigma',
            'site' => $payload['site'] ?? null,
            'lokasi' => $payload['lokasi'] ?? null,
            'detail_lokasi' => $payload['detail_lokasi'] ?? null,
            'keterangan_lokasi' => $payload['keterangan_lokasi'] ?? null,
            'sid_pelapor' => $pelapor['sid'],
            'npk_pelapor' => $pelapor['npk'],
            'nama_pelapor' => $pelapor['nama'],
            'jabatan_pelapor' => $pelapor['jabatan'],
            'area_pja_bc' => $payload['area_pja_bc'] ?? null,
            'area_pja_mitra' => $payload['area_pja_mitra'] ?? null,
            'foto_path' => $fotoPath,
            'is_observasi_area_kritis' => (bool) ($payload['is_observasi_area_kritis'] ?? false),
            'ketidaksesuaian' => $payload['ketidaksesuaian'] ?? null,
            'sub_ketidaksesuaian' => $payload['sub_ketidaksesuaian'] ?? null,
            'quick_action' => $payload['quick_action'] ?? null,
            'deskripsi_temuan' => $payload['deskripsi_temuan'] ?? null,
            'status' => $isDemo || ! $tableReady ? 'demo' : 'submitted',
        ];

        if ($isDemo || ! $tableReady) {
            $report = new IscHazardReport($attrs);
            $report->id = 700000 + random_int(1, 99999);
            $report->exists = false;

            return $report;
        }

        return DB::transaction(function () use ($user, $payload, $attrs, $eventId): IscHazardReport {
            $interventionId = null;
            if ($eventId !== null && $eventId > 0 && IscBoundaryEvent::query()->whereKey($eventId)->exists()) {
                $intervention = $this->interventions->execute($user, [
                    'event_id' => $eventId,
                    'type' => 'lainnya',
                    'notes' => $payload['deskripsi_temuan'] ?? null,
                ], []);
                $interventionId = $intervention->id;
            }
            $attrs['intervention_id'] = $interventionId;

            return IscHazardReport::query()->create($attrs);
        });
    }

    /**
     * @return array{sid:string,npk:?string,nama:?string,jabatan:?string}
     */
    private function resolvePerson(string $sid, mixed $npk, mixed $nama, mixed $jabatan): array
    {
        $sid = strtoupper(trim($sid));
        $found = $sid !== '' ? $this->employees->findBySid($sid) : null;

        return [
            'sid' => $sid,
            'npk' => $this->nullableString($npk) ?? ($found['npk'] ?? null),
            'nama' => $this->nullableString($nama) ?? ($found['nama'] ?? null),
            'jabatan' => $this->nullableString($jabatan) ?? ($found['jabatan'] ?? null),
        ];
    }

    /**
     * @return array{sid:string,npk:?string,nama:?string,jabatan:?string,username:?string,password:?string}
     */
    private function resolvePelapor(
        string $sid,
        mixed $npk,
        mixed $nama,
        mixed $jabatan,
        mixed $username,
        mixed $password,
    ): array {
        $sid = strtoupper(trim($sid));
        $found = $sid !== '' ? $this->sysUsers->findBySid($sid) : null;

        return [
            'sid' => $sid,
            'npk' => $this->nullableString($npk) ?? ($found['npk'] ?? null),
            'nama' => $this->nullableString($nama) ?? ($found['nama'] ?? null),
            'jabatan' => $this->nullableString($jabatan) ?? ($found['jabatan'] ?? null),
            'username' => $this->nullableString($username) ?? ($found['username'] ?? null),
            'password' => $this->nullableString($password) ?? ($found['password'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
