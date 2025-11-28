@php
    $fields = [
        ['name' => 'no_kecelakaan', 'label' => 'No Kecelakaan', 'type' => 'text', 'col' => 6, 'required' => true],
        ['name' => 'kode_be_investigasi', 'label' => 'Kode BeInvestigasi'],
        ['name' => 'status_lpi', 'label' => 'Status LPI'],
        ['name' => 'target_penyelesaian_lpi', 'label' => 'Target Penyelesaian LPI', 'type' => 'date'],
        ['name' => 'actual_penyelesaian_lpi', 'label' => 'Actual Penyelesaian LPI', 'type' => 'date'],
        ['name' => 'ketepatan_waktu_lpi', 'label' => 'Ketepatan Waktu LPI'],
        ['name' => 'tanggal', 'label' => 'Tanggal', 'type' => 'date'],
        ['name' => 'bulan', 'label' => 'Bulan', 'type' => 'number', 'step' => '1'],
        ['name' => 'tahun', 'label' => 'Tahun', 'type' => 'number', 'step' => '1'],
        ['name' => 'minggu_ke', 'label' => 'Minggu Ke', 'type' => 'number', 'step' => '1'],
        ['name' => 'hari', 'label' => 'Hari'],
        ['name' => 'jam', 'label' => 'Jam', 'type' => 'number', 'step' => '1'],
        ['name' => 'menit', 'label' => 'Menit', 'type' => 'number', 'step' => '1'],
        ['name' => 'shift', 'label' => 'Shift'],
        ['name' => 'perusahaan', 'label' => 'Perusahaan'],
        ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'step' => '0.0000001'],
        ['name' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'step' => '0.0000001'],
        ['name' => 'departemen', 'label' => 'Departemen'],
        ['name' => 'site', 'label' => 'Site'],
        ['name' => 'lokasi', 'label' => 'Lokasi'],
        ['name' => 'sublokasi', 'label' => 'Sublokasi'],
        ['name' => 'lokasi_spesifik', 'label' => 'Lokasi Spesifik'],
        ['name' => 'lokasi_validasi_hsecm', 'label' => 'Lokasi (Validasi HSECM)'],
        ['name' => 'pja', 'label' => 'PJA'],
        ['name' => 'insiden_dalam_site_mining', 'label' => 'Insiden Terjadi Dalam Site Mining'],
        ['name' => 'kategori', 'label' => 'Kategori'],
        ['name' => 'injury_status', 'label' => 'Injury/Non Injury'],
        ['name' => 'kronologis', 'label' => 'Kronologis', 'type' => 'textarea', 'col' => 12, 'rows' => 4],
        ['name' => 'high_potential', 'label' => 'High Potential'],
        ['name' => 'alat_terlibat', 'label' => 'Alat Terlibat'],
        ['name' => 'nama', 'label' => 'Nama'],
        ['name' => 'jabatan', 'label' => 'Jabatan'],
        ['name' => 'shift_kerja_ke', 'label' => 'Shift Kerja Ke', 'type' => 'number', 'step' => '1'],
        ['name' => 'hari_kerja_ke', 'label' => 'Hari Kerja Ke', 'type' => 'number', 'step' => '1'],
        ['name' => 'npk', 'label' => 'NPK'],
        ['name' => 'umur', 'label' => 'Umur', 'type' => 'number', 'step' => '1'],
        ['name' => 'range_umur', 'label' => 'Range Umur (Tahun)'],
        ['name' => 'masa_kerja_perusahaan_tahun', 'label' => 'Masa Kerja Perusahaan (Tahun)', 'type' => 'number', 'step' => '1'],
        ['name' => 'masa_kerja_perusahaan_bulan', 'label' => 'Masa Kerja Perusahaan (Bulan)', 'type' => 'number', 'step' => '1'],
        ['name' => 'range_masa_kerja_perusahaan', 'label' => 'Range Masa Kerja Perusahaan'],
        ['name' => 'masa_kerja_bc_tahun', 'label' => 'Masa Kerja di BC (Tahun)', 'type' => 'number', 'step' => '1'],
        ['name' => 'masa_kerja_bc_bulan', 'label' => 'Masa Kerja di BC (Bulan)', 'type' => 'number', 'step' => '1'],
        ['name' => 'range_masa_kerja_bc', 'label' => 'Range Masa Kerja BC (Tahun)'],
        ['name' => 'bagian_luka', 'label' => 'Bagian Luka'],
        ['name' => 'loss_cost', 'label' => 'Loss Cost (Rp)', 'type' => 'number', 'step' => '0.01'],
        ['name' => 'saksi_langsung', 'label' => 'Saksi Langsung'],
        ['name' => 'atasan_langsung', 'label' => 'Atasan Langsung'],
        ['name' => 'jabatan_atasan_langsung', 'label' => 'Jabatan Struktural - Atasan Langsung'],
        ['name' => 'kontak', 'label' => 'Kontak'],
        ['name' => 'detail_kontak', 'label' => 'Detail Kontak', 'type' => 'textarea', 'col' => 12, 'rows' => 3],
        ['name' => 'sumber_kecelakaan', 'label' => 'Sumber Kecelakaan'],
        ['name' => 'layer', 'label' => 'Layer'],
        ['name' => 'jenis_item_ipls', 'label' => 'Jenis Item IPLS'],
        ['name' => 'detail_layer', 'label' => 'Detail Layer'],
        ['name' => 'klasifikasi_layer', 'label' => 'Klasifikasi Layer'],
        ['name' => 'keterangan_layer', 'label' => 'Keterangan Layer', 'type' => 'textarea', 'col' => 12, 'rows' => 4],
        ['name' => 'id_lokasi_insiden', 'label' => 'ID Lokasi Insiden'],
    ];
@endphp

<div class="row g-3">
    @foreach ($fields as $field)
        @php
            $type = $field['type'] ?? 'text';
            $col = $field['col'] ?? 6;
            $value = old($field['name'], $insiden->{$field['name']} ?? null);
        @endphp
        <div class="col-12 col-md-{{ $col }}">
            <label for="{{ $field['name'] }}" class="form-label">{{ $field['label'] }}</label>
            @if ($type === 'textarea')
                <textarea name="{{ $field['name'] }}" id="{{ $field['name'] }}" rows="{{ $field['rows'] ?? 3 }}"
                    class="form-control" {{ !empty($field['required']) ? 'required' : '' }}>{{ $value }}</textarea>
            @else
                <input type="{{ $type }}" name="{{ $field['name'] }}" id="{{ $field['name'] }}"
                    value="{{ $value }}" class="form-control" {{ !empty($field['required']) ? 'required' : '' }}
                    @if (isset($field['step'])) step="{{ $field['step'] }}" @endif>
            @endif
        </div>
    @endforeach
</div>

