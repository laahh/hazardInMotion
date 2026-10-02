<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Equipment;

use App\Models\Company;
use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use App\Models\EmergencyResponse\MasterData\Area;
use App\Models\EmergencyResponse\MasterData\Department;
use App\Models\EmergencyResponse\MasterData\EmergencyUnit;
use App\Models\EmergencyResponse\MasterData\EquipmentCategory;
use App\Models\EmergencyResponse\MasterData\Location;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\EmergencyResponse\Shared\Concerns\TracksEquipmentStatusHistory;
use App\Models\EmergencyResponse\Shared\EquipmentDocument;
use App\Models\User;
use App\Support\EmergencyResponse\EquipmentUuidGenerator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kolom `code` menyimpan UUID peralatan yang dibaca manusia dan digenerate
 * otomatis dari site-kategori-nama-urutan (mis. "PMO-Fire Equipment-Hose-1").
 * Nilainya tidak diinput manual dan ikut dipakai sebagai kunci QR/scan.
 * Nomor registrasi fisik peralatan ada di `registration_number`.
 */
class EmergencyEquipment extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail, TracksEquipmentStatusHistory;

    protected $table = 'er_emergency_equipment';

    /** Kolom yang mengubah UUID bila nilainya berubah. */
    private const UUID_SOURCE_COLUMNS = ['site_id', 'equipment_category_id', 'name'];

    public const CONDITIONS = [
        'baik' => 'Baik',
        'perlu_perbaikan' => 'Perlu Perbaikan',
        'rusak' => 'Rusak',
        'maintenance' => 'Dalam Maintenance',
        'tidak_aktif' => 'Tidak Aktif',
    ];

    public const OPERATIONAL_STATUSES = [
        'available' => 'Available',
        'in_use' => 'In Use',
        'maintenance' => 'Maintenance',
        'out_of_service' => 'Out of Service',
    ];

    /** Status Posisi Barang. */
    public const POSITION_STATUSES = [
        'di_tempat' => 'Di Tempat',
        'dipindahkan' => 'Dipindahkan',
        'dibawa_keluar' => 'Dibawa Keluar Site',
        'hilang' => 'Hilang',
    ];

    /** Progress BA (Berita Acara). */
    public const BA_PROGRESSES = [
        'belum_ada' => 'Belum Ada BA',
        'draft' => 'Draft',
        'proses_ttd' => 'Proses Tanda Tangan',
        'selesai' => 'Selesai / Close',
    ];

    /** Status Barang. */
    public const ITEM_STATUSES = [
        'aktif' => 'Aktif',
        'proses_perbaikan' => 'Proses Perbaikan',
        'proses_penggantian' => 'Proses Penggantian',
        'dihapuskan' => 'Dihapuskan',
    ];

    protected $fillable = [
        'name', 'equipment_category_id', 'type_model', 'brand', 'serial_number',
        'registration_number', 'classification', 'equipment_detail',
        'site_id', 'company_id', 'location_id', 'location_name', 'area_id', 'area_name',
        'position_detail', 'latitude', 'longitude',
        'department_id', 'emergency_unit_id', 'purchased_at', 'commissioned_at',
        'condition', 'operational_status', 'equipment_remarks', 'damage_remarks', 'position_status',
        'ba_progress', 'ba_remarks', 'item_status', 'ba_closed_at',
        'last_inspection_at', 'next_inspection_at',
        'last_calibration_at', 'expires_at', 'certificate_number', 'certificate_expires_at',
        'photo_path', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'commissioned_at' => 'date',
        'last_inspection_at' => 'date',
        'next_inspection_at' => 'date',
        'last_calibration_at' => 'date',
        'expires_at' => 'date',
        'certificate_expires_at' => 'date',
        'ba_closed_at' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'sequence_number' => 'integer',
    ];

    protected array $auditExcept = ['photo_path'];

    protected static function booted(): void
    {
        static::saving(function (self $equipment): void {
            $equipment->refreshGeneratedUuid();
        });
    }

    /**
     * Mengisi ulang `code`/`sequence_number` saat peralatan baru dibuat atau
     * saat site/kategori/nama berubah, sehingga UUID selalu mencerminkan
     * kombinasi terbaru.
     */
    public function refreshGeneratedUuid(): void
    {
        if (! $this->needsGeneratedUuid()) {
            return;
        }

        [$sequence, $uuid] = app(EquipmentUuidGenerator::class)->generate($this);

        $this->sequence_number = $sequence;
        $this->code = $uuid;
    }

    private function needsGeneratedUuid(): bool
    {
        if (trim((string) $this->code) === '' || $this->sequence_number === null) {
            return true;
        }

        return $this->isDirty(self::UUID_SOURCE_COLUMNS);
    }

    public function category()
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function emergencyUnit()
    {
        return $this->belongsTo(EmergencyUnit::class);
    }

    public function documents()
    {
        return $this->morphMany(EquipmentDocument::class, 'documentable');
    }

    public function inspections()
    {
        return $this->morphMany(\App\Models\EmergencyResponse\Inspection\Inspection::class, 'target')->latest('inspected_at');
    }

    public function workOrders()
    {
        return $this->morphMany(\App\Models\EmergencyResponse\Maintenance\WorkOrder::class, 'equipmentable')->latest('requested_at');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function locationLabel(): ?string
    {
        $name = trim((string) ($this->location_name ?? ''));

        return $name !== '' ? $name : $this->location?->name;
    }

    public function areaLabel(): ?string
    {
        $name = trim((string) ($this->area_name ?? ''));

        return $name !== '' ? $name : $this->area?->name;
    }

    public function conditionLabel(): string
    {
        return self::CONDITIONS[$this->condition] ?? $this->condition;
    }

    public function operationalStatusLabel(): string
    {
        return self::OPERATIONAL_STATUSES[$this->operational_status] ?? $this->operational_status;
    }

    public function positionStatusLabel(): ?string
    {
        return $this->lookupLabel(self::POSITION_STATUSES, $this->position_status);
    }

    public function baProgressLabel(): ?string
    {
        return $this->lookupLabel(self::BA_PROGRESSES, $this->ba_progress);
    }

    public function itemStatusLabel(): ?string
    {
        return $this->lookupLabel(self::ITEM_STATUSES, $this->item_status);
    }

    /**
     * Nilai di luar daftar tetap ditampilkan apa adanya, supaya data lama /
     * hasil import tidak hilang saat daftar pilihan berubah.
     *
     * @param  array<string, string>  $options
     */
    private function lookupLabel(array $options, ?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $options[$value] ?? $value;
    }
}
