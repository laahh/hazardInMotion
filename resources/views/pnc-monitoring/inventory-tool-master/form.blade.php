@extends('pnc-monitoring.layouts.app')

@section('title', $mode === 'create' ? 'Tambah Jenis Alat' : 'Edit Jenis Alat')

@section('content')
<div class="card shadow-none border mb-24">
  <div class="card-header">
    <h6 class="mb-0">{{ $mode === 'create' ? 'Tambah Jenis Alat' : 'Kelola Jenis Alat: '.$row->standard_name }}</h6>
    <p class="text-secondary-light text-xs mb-0">Dokumentasi jenis alat (fungsi, metode inspeksi, fitur keselamatan, standar, checklist, aturan pakai, atribut teknis) — dipakai bersama oleh semua unit fisik jenis ini.</p>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ $mode === 'create' ? route('pnc-monitoring.inventory-tool-master.store') : route('pnc-monitoring.inventory-tool-master.update', $row) }}">
      @csrf
      @if ($mode === 'edit')
        @method('PUT')
      @endif

      <h6 class="text-primary-600 mb-16">Data Utama</h6>
      <div class="row g-3 mb-24">
        <div class="col-md-3">
          <label class="form-label" for="category_id">Kategori *</label>
          <select name="category_id" id="category_id" class="form-select" required>
            <option value="">Pilih...</option>
            @foreach ($categories as $cat)
              <option value="{{ $cat->category_id }}" @selected(old('category_id', $row->category_id) == $cat->category_id)>{{ $cat->code }} — {{ $cat->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-9">
          <label class="form-label" for="standard_name">Nama Alat (Standard Name) *</label>
          <input type="text" name="standard_name" id="standard_name" class="form-control" value="{{ old('standard_name', $row->standard_name) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="sub_category">Sub Kategori</label>
          <input type="text" name="sub_category" id="sub_category" class="form-control" value="{{ old('sub_category', $row->sub_category) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="criticality">Criticality</label>
          <select name="criticality" id="criticality" class="form-select">
            <option value="">-</option>
            @foreach (['Critical', 'Major', 'Minor'] as $opt)
              <option value="{{ $opt }}" @selected(old('criticality', $row->criticality) === $opt)>{{ $opt }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="risk_class">Risk Class</label>
          <select name="risk_class" id="risk_class" class="form-select">
            <option value="">-</option>
            @foreach (['Low', 'Medium', 'High'] as $opt)
              <option value="{{ $opt }}" @selected(old('risk_class', $row->risk_class) === $opt)>{{ $opt }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label" for="main_function">Fungsi Utama (ringkas)</label>
          <textarea name="main_function" id="main_function" class="form-control" rows="2">{{ old('main_function', $row->main_function) }}</textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="image_url">URL Gambar</label>
          <input type="text" name="image_url" id="image_url" class="form-control" value="{{ old('image_url', $row->image_url) }}">
          <div class="form-check mt-8">
            <input type="checkbox" name="is_regulated" id="is_regulated" class="form-check-input" value="1" @checked(old('is_regulated', $row->is_regulated))>
            <label class="form-check-label" for="is_regulated">Wajib regulasi (mis. PUBT/PUIL)</label>
          </div>
        </div>
      </div>

      @php
        $repeaters = [
          [
            'key' => 'functions', 'title' => 'Fungsi Detail (Daftar)', 'rows' => $row->relationLoaded('functions') ? $row->functions : collect(),
            'fields' => [['description', 'Deskripsi Fungsi', 'text']],
          ],
          [
            'key' => 'inspection_methods', 'title' => 'Metode Inspeksi', 'rows' => $row->relationLoaded('inspectionMethods') ? $row->inspectionMethods : collect(),
            'fields' => [['method_name', 'Nama Metode', 'text']],
          ],
          [
            'key' => 'safety_features', 'title' => 'Fitur Keselamatan', 'rows' => $row->relationLoaded('safetyFeatures') ? $row->safetyFeatures : collect(),
            'fields' => [['feature_name', 'Nama Fitur', 'text'], ['description', 'Deskripsi', 'text']],
          ],
          [
            'key' => 'standards', 'title' => 'Standar Acuan', 'rows' => $row->relationLoaded('standards') ? $row->standards : collect(),
            'fields' => [['standard_name', 'Nama Standar', 'text']],
          ],
        ];
      @endphp

      @foreach ($repeaters as $rep)
        <div class="mt-24">
          <div class="d-flex align-items-center justify-content-between mb-8">
            <h6 class="text-primary-600 mb-0">{{ $rep['title'] }}</h6>
            <button type="button" class="btn btn-outline-primary-600 btn-sm" data-repeat-add="{{ $rep['key'] }}">+ Tambah Baris</button>
          </div>
          <div id="rows-{{ $rep['key'] }}">
            @foreach ($rep['rows'] as $item)
              <div class="row g-2 align-items-center mb-2 repeat-row">
                @foreach ($rep['fields'] as [$field, $label, $type])
                  <div class="col">
                    <input type="{{ $type }}" name="{{ $rep['key'] }}[][{{ $field }}]" class="form-control form-control-sm" placeholder="{{ $label }}" value="{{ $item->{$field} }}">
                  </div>
                @endforeach
                <div class="col-auto">
                  <button type="button" class="btn btn-outline-danger-600 btn-sm" data-repeat-remove>&times;</button>
                </div>
              </div>
            @endforeach
          </div>
          <template id="tpl-{{ $rep['key'] }}">
            <div class="row g-2 align-items-center mb-2 repeat-row">
              @foreach ($rep['fields'] as [$field, $label, $type])
                <div class="col">
                  <input type="{{ $type }}" name="{{ $rep['key'] }}[][{{ $field }}]" class="form-control form-control-sm" placeholder="{{ $label }}">
                </div>
              @endforeach
              <div class="col-auto">
                <button type="button" class="btn btn-outline-danger-600 btn-sm" data-repeat-remove>&times;</button>
              </div>
            </div>
          </template>
        </div>
      @endforeach

      <div class="mt-24">
        <div class="d-flex align-items-center justify-content-between mb-8">
          <h6 class="text-primary-600 mb-0">Checklist Pemeriksaan</h6>
          <div class="d-flex gap-2">
            @if ($mode === 'edit')
              <a href="{{ route('pnc-monitoring.inventory-tool-master.checklist-export', $row) }}" class="btn btn-outline-success-600 btn-sm">Export Excel</a>
            @endif
            <button type="button" class="btn btn-outline-primary-600 btn-sm" data-repeat-add="checklist_items">+ Tambah Baris</button>
          </div>
        </div>
        @if ($mode === 'edit')
          <form method="POST" action="{{ route('pnc-monitoring.inventory-tool-master.checklist-import', $row) }}" enctype="multipart/form-data" class="row g-2 align-items-end mb-12">
            @csrf
            <div class="col-md-6">
              <label class="form-label text-xs mb-1">Import Excel checklist (2 kolom: Komponen Diperiksa, Kriteria Pemeriksaan) — menggantikan seluruh checklist saat ini</label>
              <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
            </div>
          </form>
        @endif
        <div id="rows-checklist_items">
          @foreach ($row->relationLoaded('checklistItems') ? $row->checklistItems : collect() as $item)
            <div class="row g-2 align-items-center mb-2 repeat-row">
              <div class="col-6">
                <input type="text" name="checklist_items[][komponen_diperiksa]" class="form-control form-control-sm" placeholder="Komponen Diperiksa" value="{{ $item->komponen_diperiksa }}">
              </div>
              <div class="col-5">
                <input type="text" name="checklist_items[][kriteria_pemeriksaan]" class="form-control form-control-sm" placeholder="Kriteria Pemeriksaan" value="{{ $item->kriteria_pemeriksaan }}">
              </div>
              <div class="col-auto">
                <button type="button" class="btn btn-outline-danger-600 btn-sm" data-repeat-remove>&times;</button>
              </div>
            </div>
          @endforeach
        </div>
        <template id="tpl-checklist_items">
          <div class="row g-2 align-items-center mb-2 repeat-row">
            <div class="col-6">
              <input type="text" name="checklist_items[][komponen_diperiksa]" class="form-control form-control-sm" placeholder="Komponen Diperiksa">
            </div>
            <div class="col-5">
              <input type="text" name="checklist_items[][kriteria_pemeriksaan]" class="form-control form-control-sm" placeholder="Kriteria Pemeriksaan">
            </div>
            <div class="col-auto">
              <button type="button" class="btn btn-outline-danger-600 btn-sm" data-repeat-remove>&times;</button>
            </div>
          </div>
        </template>
      </div>

      <div class="mt-24">
        <div class="d-flex align-items-center justify-content-between mb-8">
          <h6 class="text-primary-600 mb-0">Aturan Penggunaan (Do / Don't)</h6>
          <button type="button" class="btn btn-outline-primary-600 btn-sm" data-repeat-add="usage_rules">+ Tambah Baris</button>
        </div>
        <div id="rows-usage_rules">
          @foreach ($row->relationLoaded('usageRules') ? $row->usageRules : collect() as $item)
            <div class="row g-2 align-items-center mb-2 repeat-row">
              <div class="col-2">
                <select name="usage_rules[][rule_type]" class="form-select form-select-sm">
                  <option value="do" @selected($item->rule_type === 'do')>Do</option>
                  <option value="dont" @selected($item->rule_type === 'dont')>Don't</option>
                </select>
              </div>
              <div class="col-9">
                <input type="text" name="usage_rules[][description]" class="form-control form-control-sm" placeholder="Deskripsi" value="{{ $item->description }}">
              </div>
              <div class="col-auto">
                <button type="button" class="btn btn-outline-danger-600 btn-sm" data-repeat-remove>&times;</button>
              </div>
            </div>
          @endforeach
        </div>
        <template id="tpl-usage_rules">
          <div class="row g-2 align-items-center mb-2 repeat-row">
            <div class="col-2">
              <select name="usage_rules[][rule_type]" class="form-select form-select-sm">
                <option value="do">Do</option>
                <option value="dont">Don't</option>
              </select>
            </div>
            <div class="col-9">
              <input type="text" name="usage_rules[][description]" class="form-control form-control-sm" placeholder="Deskripsi">
            </div>
            <div class="col-auto">
              <button type="button" class="btn btn-outline-danger-600 btn-sm" data-repeat-remove>&times;</button>
            </div>
          </div>
        </template>
      </div>

      <div class="mt-24">
        <div class="d-flex align-items-center justify-content-between mb-8">
          <h6 class="text-primary-600 mb-0">Atribut Teknis (Spesifikasi bebas, mis. WLL, Kapasitas, Hazard Class)</h6>
          <button type="button" class="btn btn-outline-primary-600 btn-sm" data-repeat-add="attributes">+ Tambah Baris</button>
        </div>
        <div id="rows-attributes">
          @foreach ($row->relationLoaded('attributes') ? $row->attributes : collect() as $item)
            <div class="row g-2 align-items-center mb-2 repeat-row">
              <div class="col-5">
                <input type="text" name="attributes[][attribute_name]" class="form-control form-control-sm" placeholder="Nama Atribut" value="{{ $item->attribute_name }}">
              </div>
              <div class="col-6">
                <input type="text" name="attributes[][attribute_value]" class="form-control form-control-sm" placeholder="Nilai" value="{{ $item->attribute_value }}">
              </div>
              <div class="col-auto">
                <button type="button" class="btn btn-outline-danger-600 btn-sm" data-repeat-remove>&times;</button>
              </div>
            </div>
          @endforeach
        </div>
        <template id="tpl-attributes">
          <div class="row g-2 align-items-center mb-2 repeat-row">
            <div class="col-5">
              <input type="text" name="attributes[][attribute_name]" class="form-control form-control-sm" placeholder="Nama Atribut">
            </div>
            <div class="col-6">
              <input type="text" name="attributes[][attribute_value]" class="form-control form-control-sm" placeholder="Nilai">
            </div>
            <div class="col-auto">
              <button type="button" class="btn btn-outline-danger-600 btn-sm" data-repeat-remove>&times;</button>
            </div>
          </div>
        </template>
      </div>

      <div class="mt-32 d-flex gap-2">
        <button type="submit" class="btn btn-primary-600">Simpan</button>
        <a href="{{ route('pnc-monitoring.inventory-tool-master.index') }}" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
  document.addEventListener('click', (e) => {
    const addBtn = e.target.closest('[data-repeat-add]');
    if (addBtn) {
      const key = addBtn.getAttribute('data-repeat-add');
      const tpl = document.getElementById('tpl-' + key);
      const container = document.getElementById('rows-' + key);
      if (tpl && container) {
        container.appendChild(tpl.content.cloneNode(true));
      }
      return;
    }
    const removeBtn = e.target.closest('[data-repeat-remove]');
    if (removeBtn) {
      removeBtn.closest('.repeat-row')?.remove();
    }
  });
})();
</script>
@endsection
