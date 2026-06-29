<?php

namespace App\Http\Controllers;

use App\Models\CctvP2hChecklist;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CctvP2hChecklistController extends Controller
{
    /**
     * Display a listing of P2H Checklists.
     */
    public function index(Request $request): View
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $query = CctvP2hChecklist::query();

        // Filter by control room
        if ($request->filled('control_room')) {
            $query->where('control_room', $request->control_room);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('tanggal_pemeriksaan', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('tanggal_pemeriksaan', '<=', $request->end_date);
        }

        // Filter by shift
        if ($request->filled('shift')) {
            $query->where('shift', $request->shift);
        }

        $checklists = $query->orderByDesc('tanggal_pemeriksaan')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        // Get unique control rooms for filter dropdown
        $controlRooms = CctvP2hChecklist::select('control_room')
            ->distinct()
            ->orderBy('control_room')
            ->pluck('control_room');

        return view('cctv-p2h-checklist.index', compact('checklists', 'perPage', 'controlRooms'));
    }

    /**
     * Display the specified P2H Checklist.
     */
    public function show($id): View
    {
        $checklist = CctvP2hChecklist::findOrFail($id);
        return view('cctv-p2h-checklist.show', compact('checklist'));
    }

    /**
     * Remove the specified P2H Checklist from storage.
     */
    public function destroy($id)
    {
        $checklist = CctvP2hChecklist::findOrFail($id);
        $checklist->delete();

        return redirect()
            ->route('cctv-p2h-checklist.index')
            ->with('success', 'Data P2H Checklist berhasil dihapus.');
    }

    /**
     * Export data P2H Checklist ke Excel (mendukung filter yang sama dengan index).
     */
    public function exportExcel(Request $request)
    {
        try {
            $headers = [
                'No', 'Control Room', 'Tanggal Pemeriksaan', 'Shift', 'Nama Pengawas', 'Jenis CCTV',
                'Pemeriksaan Fisik', 'Pemeriksaan Fungsi', 'Detail CCTV', 'Catatan Lain', 'Status',
                'Created At', 'Updated At',
            ];

            $query = CctvP2hChecklist::query();

            if ($request->filled('control_room')) {
                $query->where('control_room', $request->control_room);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('start_date')) {
                $query->whereDate('tanggal_pemeriksaan', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('tanggal_pemeriksaan', '<=', $request->end_date);
            }
            if ($request->filled('shift')) {
                $query->where('shift', $request->shift);
            }

            $checklists = $query->orderByDesc('tanggal_pemeriksaan')
                ->orderByDesc('created_at')
                ->get();

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders($headers);
            $sheet = $spreadsheet->getActiveSheet();

            $rowNum = 2;
            foreach ($checklists as $index => $checklist) {
                $sheet->fromArray([
                    $index + 1,
                    $checklist->control_room ?? '',
                    $checklist->tanggal_pemeriksaan ? $checklist->tanggal_pemeriksaan->format('Y-m-d') : '',
                    $checklist->shift ?? '',
                    $checklist->nama_pengawas ?? '',
                    is_array($checklist->jenis_cctv) ? implode(', ', $checklist->jenis_cctv) : '',
                    is_array($checklist->pemeriksaan_fisik) ? json_encode($checklist->pemeriksaan_fisik, JSON_UNESCAPED_UNICODE) : '',
                    is_array($checklist->pemeriksaan_fungsi) ? json_encode($checklist->pemeriksaan_fungsi, JSON_UNESCAPED_UNICODE) : '',
                    is_array($checklist->detail_cctv) ? json_encode($checklist->detail_cctv, JSON_UNESCAPED_UNICODE) : '',
                    $checklist->catatan_lain ?? '',
                    $checklist->status ?? '',
                    $checklist->created_at ? $checklist->created_at->format('Y-m-d H:i:s') : '',
                    $checklist->updated_at ? $checklist->updated_at->format('Y-m-d H:i:s') : '',
                ], null, 'A' . $rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download($spreadsheet, 'cctv_p2h_checklist_' . date('Y-m-d_His') . '.xlsx');
        } catch (\Throwable $e) {
            Log::error('CctvP2hChecklistController exportExcel: ' . $e->getMessage());

            return redirect()
                ->route('cctv-p2h-checklist.index', $request->query())
                ->with('error', 'Gagal mengexport data Excel: ' . $e->getMessage());
        }
    }
}
