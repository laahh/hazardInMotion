<?php

namespace App\Http\Controllers;

use App\Jobs\ImportInsidenTabelJob;
use App\Models\InsidenTabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InsidenTabelController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $query = InsidenTabel::query()->latest();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('no_kecelakaan', 'like', '%' . $search . '%')
                    ->orWhere('kategori', 'like', '%' . $search . '%')
                    ->orWhere('site', 'like', '%' . $search . '%');
            });
        }

        $insidens = $query->paginate($perPage)->withQueryString();
        $groupedInsidens = $insidens->getCollection()->groupBy('no_kecelakaan');

        return view('insiden-tabel.index', compact('insidens', 'perPage', 'search', 'groupedInsidens'));
    }

    public function create(): View
    {
        $insiden = new InsidenTabel();

        return view('insiden-tabel.create', compact('insiden'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        InsidenTabel::create($data);

        return redirect()->route('insiden-tabel.index')->with('success', 'Data insiden berhasil disimpan.');
    }

    public function edit(InsidenTabel $insidenTabel): View
    {
        return view('insiden-tabel.edit', ['insiden' => $insidenTabel]);
    }

    public function update(Request $request, InsidenTabel $insidenTabel): RedirectResponse
    {
        $data = $this->validatedData($request);
        $insidenTabel->update($data);

        return redirect()->route('insiden-tabel.index')->with('success', 'Data insiden berhasil diperbarui.');
    }

    public function destroy(InsidenTabel $insidenTabel): RedirectResponse
    {
        $insidenTabel->delete();

        return redirect()->route('insiden-tabel.index')->with('success', 'Data insiden berhasil dihapus.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $file = $request->file('excel_file');
        $name = uniqid('insiden_', true) . '.' . $file->getClientOriginalExtension();
        $storedPath = $file->storeAs('insiden-imports', $name);

        ImportInsidenTabelJob::dispatch($storedPath);

        return redirect()->route('insiden-tabel.index')->with('success', 'File berhasil diunggah dan sedang diproses di background.');
    }

    protected function validatedData(Request $request): array
    {
        return $request->validate([
            'no_kecelakaan' => ['required', 'string', 'max:255'],
            'kode_be_investigasi' => ['nullable', 'string', 'max:255'],
            'status_lpi' => ['nullable', 'string', 'max:255'],
            'target_penyelesaian_lpi' => ['nullable', 'date'],
            'actual_penyelesaian_lpi' => ['nullable', 'date'],
            'ketepatan_waktu_lpi' => ['nullable', 'string', 'max:255'],
            'tanggal' => ['nullable', 'date'],
            'bulan' => ['nullable', 'integer'],
            'tahun' => ['nullable', 'integer'],
            'minggu_ke' => ['nullable', 'integer'],
            'hari' => ['nullable', 'string', 'max:255'],
            'jam' => ['nullable', 'integer'],
            'menit' => ['nullable', 'integer'],
            'shift' => ['nullable', 'string', 'max:255'],
            'perusahaan' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'departemen' => ['nullable', 'string', 'max:255'],
            'site' => ['nullable', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'sublokasi' => ['nullable', 'string', 'max:255'],
            'lokasi_spesifik' => ['nullable', 'string', 'max:255'],
            'lokasi_validasi_hsecm' => ['nullable', 'string', 'max:255'],
            'pja' => ['nullable', 'string', 'max:255'],
            'insiden_dalam_site_mining' => ['nullable', 'string', 'max:255'],
            'kategori' => ['nullable', 'string', 'max:255'],
            'injury_status' => ['nullable', 'string', 'max:255'],
            'kronologis' => ['nullable', 'string'],
            'high_potential' => ['nullable', 'string', 'max:255'],
            'alat_terlibat' => ['nullable', 'string', 'max:255'],
            'nama' => ['nullable', 'string', 'max:255'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'shift_kerja_ke' => ['nullable', 'integer'],
            'hari_kerja_ke' => ['nullable', 'integer'],
            'npk' => ['nullable', 'string', 'max:255'],
            'umur' => ['nullable', 'integer'],
            'range_umur' => ['nullable', 'string', 'max:255'],
            'masa_kerja_perusahaan_tahun' => ['nullable', 'integer'],
            'masa_kerja_perusahaan_bulan' => ['nullable', 'integer'],
            'range_masa_kerja_perusahaan' => ['nullable', 'string', 'max:255'],
            'masa_kerja_bc_tahun' => ['nullable', 'integer'],
            'masa_kerja_bc_bulan' => ['nullable', 'integer'],
            'range_masa_kerja_bc' => ['nullable', 'string', 'max:255'],
            'bagian_luka' => ['nullable', 'string', 'max:255'],
            'loss_cost' => ['nullable', 'numeric'],
            'saksi_langsung' => ['nullable', 'string', 'max:255'],
            'atasan_langsung' => ['nullable', 'string', 'max:255'],
            'jabatan_atasan_langsung' => ['nullable', 'string', 'max:255'],
            'kontak' => ['nullable', 'string', 'max:255'],
            'detail_kontak' => ['nullable', 'string'],
            'sumber_kecelakaan' => ['nullable', 'string', 'max:255'],
            'layer' => ['nullable', 'string', 'max:255'],
            'jenis_item_ipls' => ['nullable', 'string', 'max:255'],
            'detail_layer' => ['nullable', 'string', 'max:255'],
            'klasifikasi_layer' => ['nullable', 'string', 'max:255'],
            'keterangan_layer' => ['nullable', 'string'],
            'id_lokasi_insiden' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

