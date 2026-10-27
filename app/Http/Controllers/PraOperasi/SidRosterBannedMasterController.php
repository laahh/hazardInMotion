<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Models\SidRosterBannedLog;
use App\Models\SidRosterBannedMaster;
use App\Services\Dms\Roster\DmsRosterBannedAktif;
use Exception;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Master roster karyawan yang di-banned (sid_roster_banned_master).
 * CRUD + import Excel, dipakai dari /pra-operasi.
 */
class SidRosterBannedMasterController extends Controller
{
    private const BANNED_SUDAH = 'BANNED';

    private const BANNED_BELUM = 'BELUM';

    private const BANNED_UNBANNED = 'UNBANNED';

    /** Kode filter "belum ada baris di sid_roster_banned_log". */
    private const LOG_NONE = 'NONE';

    /** @var array<string, array{0:string, 1:string}> kode => [label, warna badge] */
    private const STATUS_BANNED = [
        self::BANNED_SUDAH => ['Sudah Banned', 'danger'],
        self::BANNED_BELUM => ['Belum Banned', 'warning'],
        self::BANNED_UNBANNED => ['Sudah Unbanned', 'success'],
    ];

    /** @var array<string, array{0:string, 1:string}> automation_status => [label, warna badge] */
    private const STATUS_LOG = [
        SidRosterBannedLog::STATUS_SUCCESS => ['Sukses', 'success'],
        SidRosterBannedLog::STATUS_FAILED => ['Gagal / Error', 'danger'],
        SidRosterBannedLog::STATUS_SKIPPED => ['Dilewati', 'warning'],
        SidRosterBannedLog::STATUS_PROCESSING => ['Sedang Proses', 'info'],
        self::LOG_NONE => ['Belum Ada Log', 'secondary'],
    ];

    public function index(): View
    {
        return view('pra-operasi.roster-banned.index');
    }

    /**
     * DataTables server-side data (JSON).
     */
    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 0);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length < 1 || $length > 100) {
            $length = 25;
        }
        $orderColIndex = (int) $request->input('order.0.column', 1);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Indeks = urutan kolom tabel (kolom 1 "Karyawan" = nama + SID + NIK, urut menurut nama).
        $orderColumns = [
            1 => 'm.nama',
            2 => 'm.perusahaan',
            3 => 'm.site_dedicated',
            4 => 'm.alasan_pelanggaran',
            5 => 'm.tanggal_pelanggaran',
            7 => 'bl.automation_status',
            8 => 'm.created_by',
        ];
        $orderBy = $orderColumns[$orderColIndex] ?? 'm.nama';

        $query = $this->listQuery($request, trim((string) ($request->input('search.value') ?? '')));

        $recordsTotal = SidRosterBannedMaster::count();
        $recordsFiltered = (clone $query)->count();
        $items = $query->orderBy($orderBy, $orderDir)->skip($start)->take($length)->get();

        $data = [];
        foreach ($items as $idx => $item) {
            [$bannedLabel, $bannedColor] = self::STATUS_BANNED[$this->statusBanned($item)];
            [$logLabel, $logColor] = self::STATUS_LOG[$item->ban_status ?? self::LOG_NONE] ?? [(string) $item->ban_status, 'secondary'];

            $logHtml = '<span class="badge bg-'.$logColor.'-focus text-'.$logColor.'-main">'.e($logLabel).'</span>';
            if ($item->ban_error) {
                $logHtml .= '<div class="text-xs text-secondary-light mt-4 rb-log-error" title="'.e($item->ban_error).'">'.e(Str::limit($item->ban_error, 90)).'</div>';
            }
            if ($item->ban_logged_at) {
                $logHtml .= '<div class="text-xs text-secondary-light mt-2">'.e($this->formatWaktu($item->ban_completed_at ?? $item->ban_logged_at)).'</div>';
            }

            $editUrl = route('pra-operasi.roster-banned.edit', $item->id);
            $destroyUrl = route('pra-operasi.roster-banned.destroy', $item->id);
            $csrf = csrf_token();
            $aksi = '<a href="'.e($editUrl).'" class="btn btn-sm btn-outline-primary" title="Edit"><iconify-icon icon="solar:pen-outline"></iconify-icon></a> ';
            $aksi .= '<form action="'.e($destroyUrl).'" method="POST" class="d-inline" onsubmit="return confirm(\'Yakin hapus data ini?\');"><input type="hidden" name="_token" value="'.e($csrf).'"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><iconify-icon icon="solar:trash-bin-trash-outline"></iconify-icon></button></form>';

            $data[] = [
                'DT_RowIndex' => $start + $idx + 1,
                'karyawan' => '<span class="rb-karyawan-nama">'.e($item->nama ?: '-').'</span>'
                    .'<span class="rb-karyawan-id">SID '.e($item->sid ?: '-').' &middot; NIK '.e($item->nik ?: '-').'</span>',
                'perusahaan' => $item->perusahaan ?: '-',
                'site_dedicated' => $item->site_dedicated ?: '-',
                'alasan_pelanggaran' => e($item->alasan_pelanggaran ?? '-').$this->badgeAturan($item),
                'tanggal_pelanggaran' => $item->tanggal_pelanggaran ? $item->tanggal_pelanggaran->format('d/m/Y') : '-',
                'status_banned' => '<span class="badge bg-'.$bannedColor.'-focus text-'.$bannedColor.'-main">'.e($bannedLabel).'</span>',
                'status_log' => $logHtml,
                'created_by' => e($item->created_by ?: '-')
                    .($item->created_at ? '<div class="text-xs text-secondary-light mt-2">'.e($item->created_at->format('d/m/Y H:i')).'</div>' : ''),
                'aksi' => $aksi,
            ];
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Download Excel daftar karyawan banned sesuai pencarian + filter status
     * yang sedang aktif di tabel.
     */
    public function export(Request $request)
    {
        $items = $this->listQuery($request, trim((string) $request->input('search', '')))
            ->orderBy('m.nama')
            ->get();

        $headers = [
            'No', 'NIK', 'SID', 'Nama', 'Perusahaan', 'Site Dedicated', 'Alasan Pelanggaran', 'Aturan', 'Lama Banned (hari)', 'Tanggal Pelanggaran',
            'Status Banned', 'Status Log Banned', 'Step Terakhir', 'Pesan Error', 'Waktu Log',
            'Dibuat Oleh', 'Tanggal Dibuat',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Roster Banned');
        $sheet->fromArray($headers, null, 'A1');

        $rows = [];
        foreach ($items as $idx => $item) {
            $rows[] = [
                $idx + 1,
                (string) $item->nik,
                (string) $item->sid,
                (string) $item->nama,
                (string) $item->perusahaan,
                (string) $item->site_dedicated,
                (string) $item->alasan_pelanggaran,
                SidRosterBannedMaster::LABEL_ATURAN[(string) ($item->aturan_pelanggaran ?? '')] ?? 'Manual / tidak diketahui',
                $item->lama_banned_hari === null ? '' : (string) $item->lama_banned_hari,
                $item->tanggal_pelanggaran ? $item->tanggal_pelanggaran->format('d/m/Y') : '',
                self::STATUS_BANNED[$this->statusBanned($item)][0],
                (self::STATUS_LOG[$item->ban_status ?? self::LOG_NONE] ?? [(string) $item->ban_status])[0],
                (string) $item->ban_step,
                (string) $item->ban_error,
                $item->ban_logged_at ? $this->formatWaktu($item->ban_completed_at ?? $item->ban_logged_at) : '',
                (string) $item->created_by,
                $item->created_at ? $item->created_at->format('d/m/Y H:i') : '',
            ];
        }
        // Sel ditulis eksplisit sebagai teks supaya NIK panjang / SID seperti "0E123" tidak diubah Excel jadi angka.
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValueExplicit([$c + 1, $r + 2], $value, is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);
        $sheet->setAutoFilter('A1:'.$lastCol.(count($rows) + 1));
        $sheet->freezePane('A2');
        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
        $sheet->getColumnDimension('G')->setAutoSize(false)->setWidth(50);
        $sheet->getColumnDimension('L')->setAutoSize(false)->setWidth(60);

        $filename = 'roster_banned_'.date('Y-m-d_His').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Master + log banned TERAKHIR per master_id (retry bisa bikin beberapa
     * baris log) + penanda sudah di-unban (definisi DmsRosterBannedAktif).
     * Dipakai bersama oleh tabel dan export supaya isinya selalu sama.
     */
    private function listQuery(Request $request, string $search): EloquentBuilder
    {
        $logTerakhir = DB::table('sid_roster_banned_log')
            ->selectRaw('master_id, MAX(id) AS last_id')
            ->groupBy('master_id');

        $selesai = DmsRosterBannedAktif::statusSelesai();
        $unban = static fn ($q) => $q->from('sid_roster_unban_log as u')
            ->whereColumn('u.master_id', 'm.id')
            ->whereIn('u.automation_status', $selesai);

        $query = SidRosterBannedMaster::query()
            ->from('sid_roster_banned_master as m')
            ->leftJoinSub($logTerakhir, 'll', 'll.master_id', '=', 'm.id')
            ->leftJoin('sid_roster_banned_log as bl', 'bl.id', '=', 'll.last_id')
            ->select('m.*')
            ->addSelect([
                'bl.automation_status as ban_status',
                'bl.automation_step as ban_step',
                'bl.error_message as ban_error',
                'bl.completed_at as ban_completed_at',
                'bl.created_at as ban_logged_at',
            ])
            ->selectRaw(
                'EXISTS (SELECT 1 FROM sid_roster_unban_log u WHERE u.master_id = m.id AND u.automation_status IN ('
                .implode(',', array_fill(0, count($selesai), '?')).')) AS is_unbanned',
                $selesai
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('m.nik', 'like', '%'.$search.'%')
                    ->orWhere('m.sid', 'like', '%'.$search.'%')
                    ->orWhere('m.nama', 'like', '%'.$search.'%')
                    ->orWhere('m.perusahaan', 'like', '%'.$search.'%')
                    ->orWhere('m.site_dedicated', 'like', '%'.$search.'%')
                    ->orWhere('m.alasan_pelanggaran', 'like', '%'.$search.'%')
                    ->orWhere('m.created_by', 'like', '%'.$search.'%');
            });
        }

        $statusBanned = (string) $request->input('status_banned', '');
        if ($statusBanned === self::BANNED_UNBANNED) {
            $query->whereExists($unban);
        } elseif ($statusBanned === self::BANNED_SUDAH) {
            $query->whereNotExists($unban)->where('bl.automation_status', SidRosterBannedLog::STATUS_SUCCESS);
        } elseif ($statusBanned === self::BANNED_BELUM) {
            $query->whereNotExists($unban)->where(function ($q): void {
                $q->whereNull('bl.automation_status')
                    ->orWhere('bl.automation_status', '<>', SidRosterBannedLog::STATUS_SUCCESS);
            });
        }

        // Pembeda aturan: ONSITE_70 / BERUNTUN_13 / MANUAL (kosong).
        $aturan = (string) $request->input('aturan', '');
        if (SidRosterBannedMaster::adaKolomAturan()) {
            if (array_key_exists($aturan, SidRosterBannedMaster::LABEL_ATURAN)) {
                $query->where('m.aturan_pelanggaran', $aturan);
            } elseif ($aturan === 'MANUAL') {
                $query->whereNull('m.aturan_pelanggaran');
            }
        }

        $statusLog = (string) $request->input('status_log', '');
        if ($statusLog === self::LOG_NONE) {
            $query->whereNull('bl.id');
        } elseif (array_key_exists($statusLog, self::STATUS_LOG)) {
            $query->where('bl.automation_status', $statusLog);
        }

        return $query;
    }

    /**
     * Sudah Unbanned menang atas status log banned: kasusnya sudah ditutup.
     * Hanya log SUCCESS yang berarti SID benar-benar ter-banned — FAILED,
     * SKIPPED ("BAN SID sudah penuh"), PROCESSING, atau belum ada log = belum.
     */
    private function statusBanned(SidRosterBannedMaster $item): string
    {
        if ((int) $item->is_unbanned === 1) {
            return self::BANNED_UNBANNED;
        }

        return $item->ban_status === SidRosterBannedLog::STATUS_SUCCESS ? self::BANNED_SUDAH : self::BANNED_BELUM;
    }

    private function formatWaktu(mixed $value): string
    {
        return Carbon::parse($value)->format('d/m/Y H:i');
    }

    public function create(): View
    {
        return view('pra-operasi.roster-banned.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        SidRosterBannedMaster::create(array_merge($validated, [
            'created_by' => Auth::user()?->name ?? 'system',
        ]));

        return redirect()
            ->route('pra-operasi.roster-banned.index')
            ->with('success', 'Data berhasil ditambah.');
    }

    public function edit(int $id): View
    {
        $item = SidRosterBannedMaster::findOrFail($id);

        return view('pra-operasi.roster-banned.edit', compact('item'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = SidRosterBannedMaster::findOrFail($id);
        $validated = $this->validatePayload($request);

        $item->update($validated);

        return redirect()
            ->route('pra-operasi.roster-banned.index')
            ->with('success', 'Data berhasil diubah.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $item = SidRosterBannedMaster::findOrFail($id);
        $item->delete();

        return redirect()
            ->route('pra-operasi.roster-banned.index')
            ->with('success', 'Data berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    /** Badge aturan (> 70 hari / > 13 hari beruntun) di bawah alasan; kosong untuk input manual. */
    private function badgeAturan(SidRosterBannedMaster $item): string
    {
        $kode = (string) ($item->aturan_pelanggaran ?? '');
        if (! isset(SidRosterBannedMaster::LABEL_ATURAN[$kode])) {
            return '';
        }
        $warna = $kode === SidRosterBannedMaster::ATURAN_ONSITE ? 'danger' : 'warning';

        return '<div class="mt-4"><span class="badge bg-'.$warna.'-focus text-'.$warna.'-main">'
            .e(SidRosterBannedMaster::LABEL_ATURAN[$kode])
            .($item->lama_banned_hari ? ' · banned '.(int) $item->lama_banned_hari.' hari' : '').'</span></div>';
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'nik' => ['required', 'string', 'max:64'],
            'sid' => ['nullable', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:255'],
            'perusahaan' => ['nullable', 'string', 'max:255'],
            'site_dedicated' => ['nullable', 'string', 'max:50'],
            'alasan_pelanggaran' => ['required', 'string', 'max:500'],
            'tanggal_pelanggaran' => ['nullable', 'date'],
        ] + (SidRosterBannedMaster::adaKolomAturan() ? [
            'aturan_pelanggaran' => ['nullable', 'in:'.implode(',', array_keys(SidRosterBannedMaster::LABEL_ATURAN))],
            'lama_banned_hari' => ['nullable', 'integer', 'min:1', 'max:365'],
        ] : []), [
            'nik.required' => 'NIK wajib diisi.',
            'nama.required' => 'Nama wajib diisi.',
            'alasan_pelanggaran.required' => 'Alasan pelanggaran wajib diisi.',
        ]);
    }

    public function importForm(): View
    {
        return view('pra-operasi.roster-banned.import');
    }

    /**
     * Download template Excel (header + 1 baris contoh).
     */
    public function downloadTemplate()
    {
        $headers = ['NIK', 'SID', 'Nama', 'Perusahaan', 'Site Dedicated', 'Alasan Pelanggaran', 'Tanggal Pelanggaran'];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $col++;
        }
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);

        $sheet->setCellValue('A2', '61091015');
        $sheet->setCellValue('B2', '6H2DF');
        $sheet->setCellValue('C2', 'AGUS CAHYONO');
        $sheet->setCellValue('D2', 'PT Pamapersada Nusantara');
        $sheet->setCellValue('E2', 'GMO');
        $sheet->setCellValue('F2', 'Melanggar SOP RFID saat pre-operasi');
        $sheet->setCellValue('G2', '2026-09-28');

        foreach (range('A', $lastCol) as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $filename = 'template_sid_roster_banned_master_'.date('Y-m-d').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Import data dari Excel. Setiap baris selalu jadi record baru (tidak
     * upsert) — riwayat pelanggaran yang sama untuk NIK yang sama tetap
     * dicatat masing-masing sebagai baris terpisah.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [
            'excel_file.required' => 'File Excel wajib diupload.',
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();
            $dataRows = array_slice($rows, 1);

            $createdBy = Auth::user()?->name ?? 'system';
            $imported = 0;
            $errors = [];
            $rowNum = 2;

            DB::beginTransaction();

            foreach ($dataRows as $row) {
                if (array_filter($row) === []) {
                    $rowNum++;

                    continue;
                }

                $nik = isset($row[0]) ? trim((string) $row[0]) : '';
                $sid = isset($row[1]) ? trim((string) $row[1]) : '';
                $nama = isset($row[2]) ? trim((string) $row[2]) : '';
                $perusahaan = isset($row[3]) ? trim((string) $row[3]) : '';
                $site = isset($row[4]) ? trim((string) $row[4]) : '';
                $alasan = isset($row[5]) ? trim((string) $row[5]) : '';
                $tanggalRaw = isset($row[6]) ? trim((string) $row[6]) : '';

                if ($nik === '' || $nama === '' || $alasan === '') {
                    $errors[] = "Baris {$rowNum}: NIK, Nama, dan Alasan Pelanggaran wajib diisi.";
                    $rowNum++;

                    continue;
                }

                $tanggal = null;
                if ($tanggalRaw !== '') {
                    try {
                        $tanggal = is_numeric($tanggalRaw)
                            ? ExcelDate::excelToDateTimeObject((float) $tanggalRaw)->format('Y-m-d')
                            : Carbon::parse($tanggalRaw)->format('Y-m-d');
                    } catch (Exception) {
                        $errors[] = "Baris {$rowNum}: format Tanggal Pelanggaran tidak valid, dilewati.";
                        $rowNum++;

                        continue;
                    }
                }

                SidRosterBannedMaster::create([
                    'nik' => $nik,
                    'sid' => $sid ?: null,
                    'nama' => $nama,
                    'perusahaan' => $perusahaan ?: null,
                    'site_dedicated' => $site ?: null,
                    'alasan_pelanggaran' => $alasan,
                    'tanggal_pelanggaran' => $tanggal,
                    'created_by' => $createdBy,
                ]);
                $imported++;
                $rowNum++;
            }

            DB::commit();

            $message = "Import selesai: {$imported} baris ditambahkan.";
            if ($errors !== []) {
                $message .= ' '.count($errors).' baris dilewati.';
            }

            if ($errors !== []) {
                $request->session()->flash('import_errors', array_slice($errors, 0, 50));
            }

            return redirect()
                ->route('pra-operasi.roster-banned.index')
                ->with('success', $message);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('SidRosterBannedMaster import: '.$e->getMessage());

            return redirect()
                ->route('pra-operasi.roster-banned.import-form')
                ->with('error', 'Gagal import: '.$e->getMessage());
        }
    }
}
