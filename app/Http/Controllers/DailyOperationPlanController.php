<?php

namespace App\Http\Controllers;

use App\Models\DailyOperationPlan;
use App\Models\DopPicBerauCoal;
use App\Models\DopPengawasMitraKerja;
use App\Models\CctvData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Support\SpreadsheetExporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyOperationPlanController extends Controller
{
    /**
     * Display a listing of the DOP entries.
     */
    public function index(Request $request): View
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $dops = DailyOperationPlan::with(['picBerauCoal', 'pengawasMitraKerja', 'cctvs'])
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('daily-operation-plan.index', compact('dops', 'perPage'));
    }

    /**
     * Show the form for creating a new DOP.
     */
    public function create(): View
    {
        $cctvs = CctvData::select('id', 'nama_cctv', 'no_cctv', 'lokasi_pemasangan')
            ->orderBy('nama_cctv')
            ->get();
        
        return view('daily-operation-plan.create', compact('cctvs'));
    }

    /**
     * Store a newly created DOP in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pekerjaan' => ['required', 'string', 'max:255'],
            'foto_pekerjaan' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'], // Max 5MB
            'unit_id' => ['required', 'string', 'max:255'],
            'lokasi' => ['required', 'string', 'max:255'],
            'detail_lokasi' => ['nullable', 'string'],
            'cctv_ids' => ['nullable', 'array'],
            'cctv_ids.*' => ['exists:cctv_data_bmo2,id'],
            'potensi_resiko' => ['nullable', 'string'],
            'pengendalian_bahaya' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'tanggal' => ['required', 'date'],
            // PIC Berau Coal
            'pic_berau_coal' => ['nullable', 'array'],
            'pic_berau_coal.*.shift' => ['required_with:pic_berau_coal', 'string', 'max:255'],
            'pic_berau_coal.*.nama_pic' => ['required_with:pic_berau_coal', 'string', 'max:255'],
            // Pengawas Mitra Kerja
            'pengawas_mitra_kerja' => ['nullable', 'array'],
            'pengawas_mitra_kerja.*.shift' => ['required_with:pengawas_mitra_kerja', 'string', 'max:255'],
            'pengawas_mitra_kerja.*.nama_pengawas' => ['required_with:pengawas_mitra_kerja', 'string', 'max:255'],
        ]);

        // Handle file upload
        $fotoPath = null;
        if ($request->hasFile('foto_pekerjaan')) {
            $fotoFile = $request->file('foto_pekerjaan');
            $fotoPath = $fotoFile->store('dop/foto-pekerjaan', 'public');
        }

        // Create DOP
        $dop = DailyOperationPlan::create([
            'pekerjaan' => $validated['pekerjaan'],
            'foto_pekerjaan' => $fotoPath,
            'unit_id' => $validated['unit_id'],
            'lokasi' => $validated['lokasi'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'detail_lokasi' => $validated['detail_lokasi'] ?? null,
            'potensi_resiko' => $validated['potensi_resiko'] ?? null,
            'pengendalian_bahaya' => $validated['pengendalian_bahaya'] ?? null,
            'catatan' => $validated['catatan'] ?? null,
            'tanggal' => $validated['tanggal'],
        ]);

        // Sync CCTV
        if (!empty($validated['cctv_ids'])) {
            $dop->cctvs()->sync($validated['cctv_ids']);
        }

        // Create PIC Berau Coal entries
        if (!empty($validated['pic_berau_coal'])) {
            foreach ($validated['pic_berau_coal'] as $picData) {
                if (!empty($picData['nama_pic'])) {
                    DopPicBerauCoal::create([
                        'dop_id' => $dop->id,
                        'shift' => $picData['shift'],
                        'nama_pic' => $picData['nama_pic'],
                        'layer' => $picData['layer'] ?? null,
                    ]);
                }
            }
        }

        // Create Pengawas Mitra Kerja entries
        if (!empty($validated['pengawas_mitra_kerja'])) {
            foreach ($validated['pengawas_mitra_kerja'] as $pengawasData) {
                if (!empty($pengawasData['nama_pengawas'])) {
                    DopPengawasMitraKerja::create([
                        'dop_id' => $dop->id,
                        'shift' => $pengawasData['shift'],
                        'nama_pengawas' => $pengawasData['nama_pengawas'],
                        'layer' => $pengawasData['layer'] ?? null,
                    ]);
                }
            }
        }

        return redirect()
            ->route('daily-operation-plan.index')
            ->with('success', 'DOP berhasil disimpan.');
    }

    /**
     * Display the specified DOP.
     */
    public function show($id): View
    {
        $dop = DailyOperationPlan::with(['picBerauCoal', 'pengawasMitraKerja', 'cctvs'])->findOrFail($id);
        return view('daily-operation-plan.show', compact('dop'));
    }

    /**
     * Show the form for editing the specified DOP.
     */
    public function edit($id): View
    {
        $dop = DailyOperationPlan::with(['picBerauCoal', 'pengawasMitraKerja', 'cctvs'])->findOrFail($id);
        $cctvs = CctvData::select('id', 'nama_cctv', 'no_cctv', 'lokasi_pemasangan')
            ->orderBy('nama_cctv')
            ->get();
        
        return view('daily-operation-plan.edit', compact('dop', 'cctvs'));
    }

    /**
     * Update the specified DOP in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $dop = DailyOperationPlan::findOrFail($id);

        $validated = $request->validate([
            'pekerjaan' => ['required', 'string', 'max:255'],
            'foto_pekerjaan' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'], // Max 5MB
            'unit_id' => ['required', 'string', 'max:255'],
            'lokasi' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'detail_lokasi' => ['nullable', 'string'],
            'cctv_ids' => ['nullable', 'array'],
            'cctv_ids.*' => ['exists:cctv_data_bmo2,id'],
            'potensi_resiko' => ['nullable', 'string'],
            'pengendalian_bahaya' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'tanggal' => ['required', 'date'],
            // PIC Berau Coal
            'pic_berau_coal' => ['nullable', 'array'],
            'pic_berau_coal.*.shift' => ['required_with:pic_berau_coal', 'string', 'max:255'],
            'pic_berau_coal.*.nama_pic' => ['required_with:pic_berau_coal', 'string', 'max:255'],
            // Pengawas Mitra Kerja
            'pengawas_mitra_kerja' => ['nullable', 'array'],
            'pengawas_mitra_kerja.*.shift' => ['required_with:pengawas_mitra_kerja', 'string', 'max:255'],
            'pengawas_mitra_kerja.*.nama_pengawas' => ['required_with:pengawas_mitra_kerja', 'string', 'max:255'],
        ]);

        // Handle file upload
        if ($request->hasFile('foto_pekerjaan')) {
            // Delete old file if exists
            if ($dop->foto_pekerjaan) {
                Storage::disk('public')->delete($dop->foto_pekerjaan);
            }
            
            $fotoFile = $request->file('foto_pekerjaan');
            $fotoPath = $fotoFile->store('dop/foto-pekerjaan', 'public');
            // Add foto_pekerjaan path to validated data
            $validated['foto_pekerjaan'] = $fotoPath;
        }
        // If no file uploaded, foto_pekerjaan is not in validated array, so existing value will be preserved

        // Update DOP with validated data
        $dop->fill($validated);
        $dop->save();

        // Sync CCTV
        if (isset($validated['cctv_ids'])) {
            $dop->cctvs()->sync($validated['cctv_ids']);
        } else {
            $dop->cctvs()->sync([]);
        }

        // Delete existing PIC and Pengawas entries
        $dop->picBerauCoal()->delete();
        $dop->pengawasMitraKerja()->delete();

        // Create new PIC Berau Coal entries
        if (!empty($validated['pic_berau_coal'])) {
            foreach ($validated['pic_berau_coal'] as $picData) {
                if (!empty($picData['nama_pic'])) {
                    DopPicBerauCoal::create([
                        'dop_id' => $dop->id,
                        'shift' => $picData['shift'],
                        'nama_pic' => $picData['nama_pic'],
                        'layer' => $picData['layer'] ?? null,
                    ]);
                }
            }
        }

        // Create new Pengawas Mitra Kerja entries
        if (!empty($validated['pengawas_mitra_kerja'])) {
            foreach ($validated['pengawas_mitra_kerja'] as $pengawasData) {
                if (!empty($pengawasData['nama_pengawas'])) {
                    DopPengawasMitraKerja::create([
                        'dop_id' => $dop->id,
                        'shift' => $pengawasData['shift'],
                        'nama_pengawas' => $pengawasData['nama_pengawas'],
                        'layer' => $pengawasData['layer'] ?? null,
                    ]);
                }
            }
        }

        return redirect()
            ->route('daily-operation-plan.index')
            ->with('success', 'DOP berhasil diperbarui.');
    }

    /**
     * Remove the specified DOP from storage.
     */
    public function destroy($id): RedirectResponse
    {
        $dop = DailyOperationPlan::findOrFail($id);

        // Delete photo if exists
        if ($dop->foto_pekerjaan) {
            Storage::disk('public')->delete($dop->foto_pekerjaan);
        }

        // Delete related entries (cascade should handle this, but being explicit)
        $dop->picBerauCoal()->delete();
        $dop->pengawasMitraKerja()->delete();

        // Delete DOP
        $dop->delete();

        return redirect()
            ->route('daily-operation-plan.index')
            ->with('success', 'DOP berhasil dihapus.');
    }

    /**
     * Toggle the status of a DOP (on/off).
     */
    public function toggleStatus($id)
    {
        $dop = DailyOperationPlan::findOrFail($id);
        $dop->status = !$dop->status;
        $dop->save();

        return response()->json([
            'success' => true,
            'status' => $dop->status,
            'message' => $dop->status ? 'DOP diaktifkan' : 'DOP dinonaktifkan',
        ]);
    }

    /**
     * Download Excel template for DOP import
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set header
        $headers = [
            'A1' => 'Tanggal',
            'B1' => 'Pekerjaan',
            'C1' => 'Unit ID',
            'D1' => 'Lokasi',
            'E1' => 'Latitude',
            'F1' => 'Longitude',
            'G1' => 'Detail Lokasi',
            'H1' => 'Potensi Risiko',
            'I1' => 'Pengendalian Bahaya',
            'J1' => 'Catatan',
            'K1' => 'CCTV IDs (pisahkan dengan koma)',
            'L1' => 'PIC Berau Coal - Shift',
            'M1' => 'PIC Berau Coal - Nama PIC',
            'N1' => 'PIC Berau Coal - Layer',
            'O1' => 'Pengawas Mitra Kerja - Shift',
            'P1' => 'Pengawas Mitra Kerja - Nama Pengawas',
            'Q1' => 'Pengawas Mitra Kerja - Layer',
        ];

        // Set header values and style
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ]);
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(30);
        $sheet->getColumnDimension('H')->setWidth(30);
        $sheet->getColumnDimension('I')->setWidth(30);
        $sheet->getColumnDimension('J')->setWidth(30);
        $sheet->getColumnDimension('K')->setWidth(25);
        $sheet->getColumnDimension('L')->setWidth(20);
        $sheet->getColumnDimension('M')->setWidth(25);
        $sheet->getColumnDimension('N')->setWidth(15);
        $sheet->getColumnDimension('O')->setWidth(20);
        $sheet->getColumnDimension('P')->setWidth(25);
        $sheet->getColumnDimension('Q')->setWidth(15);

        // Add example row
        $exampleRow = 2;
        $sheet->setCellValue('A' . $exampleRow, '2026-01-15');
        $sheet->setCellValue('B' . $exampleRow, 'Pemeliharaan Unit Excavator');
        $sheet->setCellValue('C' . $exampleRow, 'EX-001');
        $sheet->setCellValue('D' . $exampleRow, '(B8) Area Kritis Blok 8');
        $sheet->setCellValue('E' . $exampleRow, '-2.186253');
        $sheet->setCellValue('F' . $exampleRow, '117.4539035');
        $sheet->setCellValue('G' . $exampleRow, '(B8) Running Dragflow WMP 47');
        $sheet->setCellValue('H' . $exampleRow, 'Tenggelam, Terbalik');
        $sheet->setCellValue('I' . $exampleRow, 'Assessment, JSA, SOP');
        $sheet->setCellValue('J' . $exampleRow, 'Pekerjaan dilakukan pada shift pagi');
        $sheet->setCellValue('K' . $exampleRow, '1,2,3');
        $sheet->setCellValue('L' . $exampleRow, 'Shift 1 s/d 2');
        $sheet->setCellValue('M' . $exampleRow, 'John Doe');
        $sheet->setCellValue('N' . $exampleRow, 'Layer 1');
        $sheet->setCellValue('O' . $exampleRow, 'Shift 1 s/d 2');
        $sheet->setCellValue('P' . $exampleRow, 'Jane Smith');
        $sheet->setCellValue('Q' . $exampleRow, 'Layer 2');

        // Add data validation notes
        $noteRow = 4;
        $sheet->setCellValue('A' . $noteRow, 'CATATAN:');
        $sheet->getStyle('A' . $noteRow)->getFont()->setBold(true);
        
        $notes = [
            'A5' => '1. Tanggal: Format YYYY-MM-DD (contoh: 2026-01-15)',
            'A6' => '2. CCTV IDs: Pisahkan dengan koma jika lebih dari satu (contoh: 1,2,3)',
            'A7' => '3. Shift: Gunakan "Shift 1 s/d 2" atau "Shift 2 s/d 1"',
            'A8' => '4. Potensi Risiko: Pisahkan dengan koma jika lebih dari satu',
            'A9' => '5. Pengendalian Bahaya: Pisahkan dengan koma jika lebih dari satu',
            'A10' => '6. Untuk PIC/Pengawas multiple, buat baris baru dengan data yang sama kecuali PIC/Pengawas',
        ];

        foreach ($notes as $cell => $note) {
            $sheet->setCellValue($cell, $note);
            $sheet->getStyle($cell)->getFont()->setItalic(true);
            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('666666');
        }

        // Freeze first row
        $sheet->freezePane('A2');

        // Create temporary file
        $filename = 'template_daily_operation_plan_' . date('Y-m-d') . '.xlsx';
        $tempPath = storage_path('app/temp/' . $filename);
        
        // Ensure temp directory exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Import DOP from Excel file
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'], // Max 10MB
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Skip header row (row 1)
            $dataRows = array_slice($rows, 1);
            
            $imported = 0;
            $errors = [];
            $rowNumber = 2; // Start from row 2 (after header)
            $dopCache = []; // Cache untuk DOP yang sudah dibuat (untuk menggabungkan PIC/Pengawas)

            DB::beginTransaction();

            foreach ($dataRows as $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    $rowNumber++;
                    continue;
                }

                try {
                    // Validate required fields
                    if (empty($row[0]) || empty($row[1]) || empty($row[2]) || empty($row[3])) {
                        $errors[] = "Baris {$rowNumber}: Tanggal, Pekerjaan, Unit ID, dan Lokasi wajib diisi";
                        $rowNumber++;
                        continue;
                    }

                    // Parse tanggal
                    $tanggal = $row[0];
                    if (is_numeric($tanggal)) {
                        // Excel date serial number
                        $tanggal = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tanggal)->format('Y-m-d');
                    } else {
                        // Try to parse as date string
                        $parsedDate = date_create($tanggal);
                        if (!$parsedDate) {
                            throw new \Exception("Format tanggal tidak valid: {$tanggal}");
                        }
                        $tanggal = $parsedDate->format('Y-m-d');
                    }

                    // Create unique key untuk DOP (untuk mendeteksi DOP yang sama)
                    $dopKey = md5($tanggal . '|' . ($row[1] ?? '') . '|' . ($row[2] ?? '') . '|' . ($row[3] ?? ''));

                    // Check if DOP already exists in this import batch
                    if (isset($dopCache[$dopKey])) {
                        $dop = $dopCache[$dopKey];
                    } else {
                        // Check if DOP already exists in database
                        $dop = DailyOperationPlan::where('tanggal', $tanggal)
                            ->where('pekerjaan', $row[1] ?? '')
                            ->where('unit_id', $row[2] ?? '')
                            ->where('lokasi', $row[3] ?? '')
                            ->first();

                        if (!$dop) {
                            // Parse latitude and longitude
                            $latitude = !empty($row[4]) ? (is_numeric($row[4]) ? (float)$row[4] : null) : null;
                            $longitude = !empty($row[5]) ? (is_numeric($row[5]) ? (float)$row[5] : null) : null;
                            
                            // Validate latitude and longitude ranges
                            if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
                                $latitude = null;
                            }
                            if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
                                $longitude = null;
                            }
                            
                            // Create new DOP
                            $dop = DailyOperationPlan::create([
                                'tanggal' => $tanggal,
                                'pekerjaan' => $row[1] ?? '',
                                'unit_id' => $row[2] ?? '',
                                'lokasi' => $row[3] ?? '',
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'detail_lokasi' => $row[6] ?? null,
                                'potensi_resiko' => $row[7] ?? null,
                                'pengendalian_bahaya' => $row[8] ?? null,
                                'catatan' => $row[9] ?? null,
                                'foto_pekerjaan' => null, // Foto tidak bisa diupload via Excel
                            ]);
                            $imported++;
                        }

                        // Cache DOP
                        $dopCache[$dopKey] = $dop;

                        // Handle CCTV IDs (only on first occurrence)
                        if (!empty($row[10])) {
                            $cctvIds = array_map('trim', explode(',', $row[10]));
                            $cctvIds = array_filter($cctvIds, function($id) {
                                return is_numeric($id) && CctvData::where('id', $id)->exists();
                            });
                            if (!empty($cctvIds)) {
                                // Merge with existing CCTV IDs
                                $existingIds = $dop->cctvs()->pluck('id')->toArray();
                                $allIds = array_unique(array_merge($existingIds, $cctvIds));
                                $dop->cctvs()->sync($allIds);
                            }
                        }
                    }

                    // Handle PIC Berau Coal (always add, even if DOP exists)
                    if (!empty($row[11]) && !empty($row[12])) {
                        // Check if PIC already exists
                        $picExists = DopPicBerauCoal::where('dop_id', $dop->id)
                            ->where('shift', $row[11] ?? null)
                            ->where('nama_pic', $row[12] ?? '')
                            ->exists();
                        
                        if (!$picExists) {
                            DopPicBerauCoal::create([
                                'dop_id' => $dop->id,
                                'shift' => $row[11] ?? null,
                                'nama_pic' => $row[12] ?? '',
                                'layer' => $row[13] ?? null,
                            ]);
                        }
                    }

                    // Handle Pengawas Mitra Kerja (always add, even if DOP exists)
                    if (!empty($row[14]) && !empty($row[15])) {
                        // Check if Pengawas already exists
                        $pengawasExists = DopPengawasMitraKerja::where('dop_id', $dop->id)
                            ->where('shift', $row[14] ?? null)
                            ->where('nama_pengawas', $row[15] ?? '')
                            ->exists();
                        
                        if (!$pengawasExists) {
                            DopPengawasMitraKerja::create([
                                'dop_id' => $dop->id,
                                'shift' => $row[14] ?? null,
                                'nama_pengawas' => $row[15] ?? '',
                                'layer' => $row[16] ?? null,
                            ]);
                        }
                    }

                } catch (\Exception $e) {
                    $errors[] = "Baris {$rowNumber}: " . $e->getMessage();
                    Log::error("DOP Import Error - Row {$rowNumber}: " . $e->getMessage());
                }

                $rowNumber++;
            }

            DB::commit();

            $message = "Berhasil mengimpor {$imported} data DOP.";
            if (!empty($errors)) {
                $message .= " Terjadi " . count($errors) . " error. " . implode(' | ', array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $message .= " (dan " . (count($errors) - 5) . " error lainnya)";
                }
            }

            return redirect()
                ->route('daily-operation-plan.index')
                ->with('success', $message)
                ->with('import_errors', $errors);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DOP Import Error: ' . $e->getMessage());
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Gagal mengimpor file Excel: ' . $e->getMessage());
        }
    }

    /**
     * Export semua data DOP ke Excel.
     */
    public function exportExcel()
    {
        try {
            $headers = [
                'No', 'Tanggal', 'Pekerjaan', 'Aktivitas', 'Unit ID', 'Perusahaan', 'Lokasi', 'Site',
                'Latitude', 'Longitude', 'Detail Lokasi', 'CCTV', 'PIC Berau Coal', 'Pengawas Mitra Kerja',
                'Potensi Risiko', 'Pengendalian Bahaya', 'Catatan', 'Status', 'Created At', 'Updated At',
            ];

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders($headers);
            $sheet = $spreadsheet->getActiveSheet();

            $dops = DailyOperationPlan::with(['picBerauCoal', 'pengawasMitraKerja', 'cctvs'])
                ->orderByDesc('tanggal')
                ->orderByDesc('created_at')
                ->get();

            $rowNum = 2;
            foreach ($dops as $index => $dop) {
                $cctvNames = $dop->cctvs->map(function ($cctv) {
                    $name = $cctv->nama_cctv ?? ('CCTV ' . $cctv->id);
                    if ($cctv->no_cctv) {
                        $name .= ' (' . $cctv->no_cctv . ')';
                    }

                    return $name;
                })->implode(', ');

                $picBerauCoal = $dop->picBerauCoal->map(function ($pic) {
                    return 'Shift: ' . ($pic->shift ?? '-') . ' - Nama: ' . ($pic->nama_pic ?? '-');
                })->implode('; ');

                $pengawasMitra = $dop->pengawasMitraKerja->map(function ($p) {
                    return 'Shift: ' . ($p->shift ?? '-') . ' - Nama: ' . ($p->nama_pengawas ?? '-');
                })->implode('; ');

                $sheet->fromArray([
                    $index + 1,
                    $dop->tanggal ? $dop->tanggal->format('Y-m-d') : '',
                    $dop->pekerjaan ?? '',
                    $dop->aktivitas ?? '',
                    $dop->unit_id ?? '',
                    $dop->perusahaan ?? '',
                    $dop->lokasi ?? '',
                    $dop->site ?? '',
                    $dop->latitude ?? '',
                    $dop->longitude ?? '',
                    $dop->detail_lokasi ?? '',
                    $cctvNames,
                    $picBerauCoal,
                    $pengawasMitra,
                    $dop->potensi_resiko ?? '',
                    $dop->pengendalian_bahaya ?? '',
                    $dop->catatan ?? '',
                    $dop->status ? 'ON' : 'OFF',
                    $dop->created_at ? $dop->created_at->format('Y-m-d H:i:s') : '',
                    $dop->updated_at ? $dop->updated_at->format('Y-m-d H:i:s') : '',
                ], null, 'A' . $rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download($spreadsheet, 'daily_operation_plan_' . date('Y-m-d_His') . '.xlsx');
        } catch (\Exception $e) {
            Log::error('DailyOperationPlan export error: ' . $e->getMessage());

            return redirect()
                ->route('daily-operation-plan.index')
                ->with('error', 'Gagal mengexport data Excel: ' . $e->getMessage());
        }
    }
}

