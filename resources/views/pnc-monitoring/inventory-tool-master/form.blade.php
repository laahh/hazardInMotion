@extends('pnc-monitoring.layouts.app')

@section('title', $mode === 'create' ? 'Tambah Jenis Alat' : 'Edit Jenis Alat')

@section('css')
<style>
  .repeat-section .card-header { background-color: var(--bs-tertiary-bg, #F8F9FA); }
  .repeat-row-box {
    border: 1px solid var(--neutral-200, #E9EAEB);
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 8px;
    background-color: #fff;
  }
  .repeat-row-box:hover { border-color: var(--primary-200, #C2D6FE); }
  .repeat-empty-hint {
    border: 1px dashed var(--neutral-300, #D5D7DA);
    border-radius: 8px;
    padding: 14px;
    text-align: center;
  }
</style>
@endsection

@section('content')
<div class="card shadow-none border mb-24">
  <div class="card-header">
    <h6 class="mb-0">{{ $mode === 'create' ? 'Tambah Jenis Alat' : 'Kelola Jenis Alat: '.$row->standard_name }}</h6>
    <p class="text-secondary-light text-xs mb-0">Dokumentasi jenis alat (fungsi, metode inspeksi, fitur keselamatan, standar, checklist, aturan pakai, atribut teknis) — dipakai bersama oleh semua unit fisik jenis ini.</p>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ $mode === 'create' ? route('pnc-monitoring.inventory-tool-master.store') : route('pnc-monitoring.inventory-tool-master.update', $row) }}" enctype="multipart/form-data">
      @csrf
      @if ($mode === 'edit')
        @method('PUT')
      @endif

      <div class="card border shadow-none repeat-section">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ri-information-line text-primary-600"></i>
          <h6 class="mb-0">Data Utama</h6>
        </div>
        <div class="card-body">
          <div class="row g-3">
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
              <div class="form-check mt-32">
                <input type="checkbox" name="is_regulated" id="is_regulated" class="form-check-input" value="1" @checked(old('is_regulated', $row->is_regulated))>
                <label class="form-check-label" for="is_regulated">Wajib regulasi (mis. PUBT/PUIL)</label>
              </div>
            </div>

            <div class="col-md-12">
              <hr class="my-8">
              <label class="form-label mb-8">Gambar Alat</label>
              <div class="d-flex flex-wrap align-items-start gap-16">
                @if ($row->image_url)
                  <div id="current-image-wrap">
                    <img src="{{ $row->imageDisplayUrl() }}" alt="Gambar {{ $row->standard_name }}" class="rounded border" style="max-height: 120px; max-width: 160px; object-fit: cover;">
                    <div class="form-check mt-4">
                      <input type="checkbox" name="remove_image" id="remove_image" class="form-check-input" value="1">
                      <label class="form-check-label text-danger-600 text-sm" for="remove_image">Hapus gambar ini</label>
                    </div>
                  </div>
                @endif
                <div class="flex-grow-1" style="min-width: 240px;">
                  <input type="file" name="image" id="image" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp">
                  <p class="text-secondary-light text-xs mb-0 mt-4">Unggah file gambar (JPG/PNG/WEBP, maks 4 MB) — akan menggantikan gambar saat ini. Bisa juga isi URL manual di bawah.</p>
                  <img id="image-preview" class="rounded border mt-8 d-none" style="max-height: 120px; max-width: 160px; object-fit: cover;">
                </div>
                <div class="flex-grow-1" style="min-width: 240px;">
                  <label class="form-label text-xs mb-1" for="image_url">atau URL Gambar (link eksternal)</label>
                  <input type="text" name="image_url" id="image_url" class="form-control form-control-sm" value="{{ old('image_url', $row->image_url) }}">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      @php
        $repeaters = [
          [
            'key' => 'functions', 'icon' => 'ri-list-check-2', 'title' => 'Fungsi Detail (Daftar)',
            'hint' => 'Rincian fungsi alat, satu baris per poin.',
            'rows' => $row->relationLoaded('functions') ? $row->functions : collect(),
            'fields' => [['description', 'Deskripsi Fungsi', 'text', 'col']],
          ],
          [
            'key' => 'inspection_methods', 'icon' => 'ri-search-eye-line', 'title' => 'Metode Inspeksi',
            'hint' => 'Cara memeriksa alat ini sebelum/selama pemakaian.',
            'rows' => $row->relationLoaded('inspectionMethods') ? $row->inspectionMethods : collect(),
            'fields' => [['method_name', 'Nama Metode', 'text', 'col']],
          ],
          [
            'key' => 'safety_features', 'icon' => 'ri-shield-check-line', 'title' => 'Fitur Keselamatan',
            'hint' => 'Fitur pengaman bawaan alat (mis. safety latch, overload protection).',
            'rows' => $row->relationLoaded('safetyFeatures') ? $row->safetyFeatures : collect(),
            'fields' => [['feature_name', 'Nama Fitur', 'text', 'col-4'], ['description', 'Deskripsi', 'text', 'col']],
          ],
          [
            'key' => 'standards', 'icon' => 'ri-award-line', 'title' => 'Standar Acuan',
            'hint' => 'Standar/regulasi yang menjadi acuan (mis. SNI, ISO, PUBT).',
            'rows' => $row->relationLoaded('standards') ? $row->standards : collect(),
            'fields' => [['standard_name', 'Nama Standar', 'text', 'col']],
          ],
        ];
      @endphp

      @foreach ($repeaters as $rep)
        <div class="card border shadow-none repeat-section mt-24">
          <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
              <i class="{{ $rep['icon'] }} text-primary-600"></i>
              <div>
                <h6 class="mb-0">{{ $rep['title'] }}</h6>
                <p class="text-secondary-light text-xs mb-0">{{ $rep['hint'] }}</p>
              </div>
            </div>
            <div class="d-flex gap-2">
              @if ($mode === 'edit')
                <a href="{{ route('pnc-monitoring.inventory-tool-master.detail-export', [$row, $rep['key']]) }}" class="btn btn-outline-success-600 btn-sm">
                  <i class="ri-file-excel-2-line"></i> Export Excel
                </a>
              @endif
              <button type="button" class="btn btn-outline-primary-600 btn-sm" data-repeat-add="{{ $rep['key'] }}">
                <i class="ri-add-line"></i> Tambah Baris
              </button>
            </div>
          </div>
          <div class="card-body">
            @if ($mode === 'edit')
              <form method="POST" action="{{ route('pnc-monitoring.inventory-tool-master.detail-import', [$row, $rep['key']]) }}" enctype="multipart/form-data" class="row g-2 align-items-end mb-16 pb-16 border-bottom">
                @csrf
                <div class="col-md-8">
                  <label class="form-label text-xs mb-1">Import Excel ({{ collect($rep['fields'])->pluck(1)->implode(', ') }}) — menggantikan seluruh isi bagian ini</label>
                  <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
                </div>
                <div class="col-md-2">
                  <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
                </div>
              </form>
            @endif
            <div id="rows-{{ $rep['key'] }}">
              @foreach ($rep['rows'] as $item)
                <div class="row g-2 align-items-center repeat-row-box repeat-row">
                  @foreach ($rep['fields'] as [$field, $label, $type, $col])
                    <div class="{{ $col }}">
                      <input type="{{ $type }}" name="{{ $rep['key'] }}[][{{ $field }}]" class="form-control form-control-sm" placeholder="{{ $label }}" value="{{ $item->{$field} }}">
                    </div>
                  @endforeach
                  <div class="col-auto">
                    <button type="button" class="btn btn-outline-danger-600 btn-icon btn-sm" data-repeat-remove title="Hapus baris">
                      <i class="ri-delete-bin-6-line"></i>
                    </button>
                  </div>
                </div>
              @endforeach
            </div>
            <p class="repeat-empty-hint text-secondary-light text-sm mb-0" data-empty-for="{{ $rep['key'] }}" @style(['display: none' => $rep['rows']->isNotEmpty()])>
              Belum ada baris — klik "Tambah Baris" untuk menambahkan.
            </p>
          </div>
          <template id="tpl-{{ $rep['key'] }}">
            <div class="row g-2 align-items-center repeat-row-box repeat-row">
              @foreach ($rep['fields'] as [$field, $label, $type, $col])
                <div class="{{ $col }}">
                  <input type="{{ $type }}" name="{{ $rep['key'] }}[][{{ $field }}]" class="form-control form-control-sm" placeholder="{{ $label }}">
                </div>
              @endforeach
              <div class="col-auto">
                <button type="button" class="btn btn-outline-danger-600 btn-icon btn-sm" data-repeat-remove title="Hapus baris">
                  <i class="ri-delete-bin-6-line"></i>
                </button>
              </div>
            </div>
          </template>
        </div>
      @endforeach

      @php $checklistItems = $row->relationLoaded('checklistItems') ? $row->checklistItems : collect(); @endphp
      <div class="card border shadow-none repeat-section mt-24">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div class="d-flex align-items-center gap-2">
            <i class="ri-checkbox-multiple-line text-primary-600"></i>
            <div>
              <h6 class="mb-0">Checklist Pemeriksaan</h6>
              <p class="text-secondary-light text-xs mb-0">Komponen yang wajib diperiksa beserta kriteria lulus/tidaknya.</p>
            </div>
          </div>
          <div class="d-flex gap-2">
            @if ($mode === 'edit')
              <a href="{{ route('pnc-monitoring.inventory-tool-master.checklist-export', $row) }}" class="btn btn-outline-success-600 btn-sm">
                <i class="ri-file-excel-2-line"></i> Export Excel
              </a>
            @endif
            <button type="button" class="btn btn-outline-primary-600 btn-sm" data-repeat-add="checklist_items">
              <i class="ri-add-line"></i> Tambah Baris
            </button>
          </div>
        </div>
        <div class="card-body">
          @if ($mode === 'edit')
            <form method="POST" action="{{ route('pnc-monitoring.inventory-tool-master.checklist-import', $row) }}" enctype="multipart/form-data" class="row g-2 align-items-end mb-16 pb-16 border-bottom">
              @csrf
              <div class="col-md-8">
                <label class="form-label text-xs mb-1">Import Excel checklist (2 kolom: Komponen Diperiksa, Kriteria Pemeriksaan) — menggantikan seluruh checklist saat ini</label>
                <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
              </div>
            </form>
          @endif
          <div id="rows-checklist_items">
            @foreach ($checklistItems as $item)
              <div class="row g-2 align-items-center repeat-row-box repeat-row">
                <div class="col-6">
                  <input type="text" name="checklist_items[][komponen_diperiksa]" class="form-control form-control-sm" placeholder="Komponen Diperiksa" value="{{ $item->komponen_diperiksa }}">
                </div>
                <div class="col-5">
                  <input type="text" name="checklist_items[][kriteria_pemeriksaan]" class="form-control form-control-sm" placeholder="Kriteria Pemeriksaan" value="{{ $item->kriteria_pemeriksaan }}">
                </div>
                <div class="col-auto">
                  <button type="button" class="btn btn-outline-danger-600 btn-icon btn-sm" data-repeat-remove title="Hapus baris">
                    <i class="ri-delete-bin-6-line"></i>
                  </button>
                </div>
              </div>
            @endforeach
          </div>
          <p class="repeat-empty-hint text-secondary-light text-sm mb-0" data-empty-for="checklist_items" @style(['display: none' => $checklistItems->isNotEmpty()])>
            Belum ada checklist — klik "Tambah Baris" atau import dari Excel.
          </p>
        </div>
        <template id="tpl-checklist_items">
          <div class="row g-2 align-items-center repeat-row-box repeat-row">
            <div class="col-6">
              <input type="text" name="checklist_items[][komponen_diperiksa]" class="form-control form-control-sm" placeholder="Komponen Diperiksa">
            </div>
            <div class="col-5">
              <input type="text" name="checklist_items[][kriteria_pemeriksaan]" class="form-control form-control-sm" placeholder="Kriteria Pemeriksaan">
            </div>
            <div class="col-auto">
              <button type="button" class="btn btn-outline-danger-600 btn-icon btn-sm" data-repeat-remove title="Hapus baris">
                <i class="ri-delete-bin-6-line"></i>
              </button>
            </div>
          </div>
        </template>
      </div>

      @php $usageRules = $row->relationLoaded('usageRules') ? $row->usageRules : collect(); @endphp
      <div class="card border shadow-none repeat-section mt-24">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
          <div class="d-flex align-items-center gap-2">
            <i class="ri-guide-line text-primary-600"></i>
            <div>
              <h6 class="mb-0">Aturan Penggunaan (Do / Don't)</h6>
              <p class="text-secondary-light text-xs mb-0">Panduan singkat yang boleh dan tidak boleh dilakukan saat memakai alat ini.</p>
            </div>
          </div>
          <div class="d-flex gap-2">
            @if ($mode === 'edit')
              <a href="{{ route('pnc-monitoring.inventory-tool-master.detail-export', [$row, 'usage_rules']) }}" class="btn btn-outline-success-600 btn-sm">
                <i class="ri-file-excel-2-line"></i> Export Excel
              </a>
            @endif
            <button type="button" class="btn btn-outline-primary-600 btn-sm" data-repeat-add="usage_rules">
              <i class="ri-add-line"></i> Tambah Baris
            </button>
          </div>
        </div>
        <div class="card-body">
          @if ($mode === 'edit')
            <form method="POST" action="{{ route('pnc-monitoring.inventory-tool-master.detail-import', [$row, 'usage_rules']) }}" enctype="multipart/form-data" class="row g-2 align-items-end mb-16 pb-16 border-bottom">
              @csrf
              <div class="col-md-8">
                <label class="form-label text-xs mb-1">Import Excel (Tipe do/dont, Deskripsi) — menggantikan seluruh aturan saat ini</label>
                <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
              </div>
            </form>
          @endif
          <div id="rows-usage_rules">
            @foreach ($usageRules as $item)
              <div class="row g-2 align-items-center repeat-row-box repeat-row">
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
                  <button type="button" class="btn btn-outline-danger-600 btn-icon btn-sm" data-repeat-remove title="Hapus baris">
                    <i class="ri-delete-bin-6-line"></i>
                  </button>
                </div>
              </div>
            @endforeach
          </div>
          <p class="repeat-empty-hint text-secondary-light text-sm mb-0" data-empty-for="usage_rules" @style(['display: none' => $usageRules->isNotEmpty()])>
            Belum ada aturan — klik "Tambah Baris" untuk menambahkan.
          </p>
        </div>
        <template id="tpl-usage_rules">
          <div class="row g-2 align-items-center repeat-row-box repeat-row">
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
              <button type="button" class="btn btn-outline-danger-600 btn-icon btn-sm" data-repeat-remove title="Hapus baris">
                <i class="ri-delete-bin-6-line"></i>
              </button>
            </div>
          </div>
        </template>
      </div>

      @php $attributes = $row->relationLoaded('attributes') ? $row->attributes : collect(); @endphp
      <div class="card border shadow-none repeat-section mt-24">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
          <div class="d-flex align-items-center gap-2">
            <i class="ri-settings-4-line text-primary-600"></i>
            <div>
              <h6 class="mb-0">Atribut Teknis</h6>
              <p class="text-secondary-light text-xs mb-0">Spesifikasi bebas per jenis alat, mis. WLL, Kapasitas, Hazard Class.</p>
            </div>
          </div>
          <div class="d-flex gap-2">
            @if ($mode === 'edit')
              <a href="{{ route('pnc-monitoring.inventory-tool-master.detail-export', [$row, 'attributes']) }}" class="btn btn-outline-success-600 btn-sm">
                <i class="ri-file-excel-2-line"></i> Export Excel
              </a>
            @endif
            <button type="button" class="btn btn-outline-primary-600 btn-sm" data-repeat-add="attributes">
              <i class="ri-add-line"></i> Tambah Baris
            </button>
          </div>
        </div>
        <div class="card-body">
          @if ($mode === 'edit')
            <form method="POST" action="{{ route('pnc-monitoring.inventory-tool-master.detail-import', [$row, 'attributes']) }}" enctype="multipart/form-data" class="row g-2 align-items-end mb-16 pb-16 border-bottom">
              @csrf
              <div class="col-md-8">
                <label class="form-label text-xs mb-1">Import Excel (Nama Atribut, Nilai) — menggantikan seluruh atribut saat ini</label>
                <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
              </div>
            </form>
          @endif
          <div id="rows-attributes">
            @foreach ($attributes as $item)
              <div class="row g-2 align-items-center repeat-row-box repeat-row">
                <div class="col-5">
                  <input type="text" name="attributes[][attribute_name]" class="form-control form-control-sm" placeholder="Nama Atribut" value="{{ $item->attribute_name }}">
                </div>
                <div class="col-6">
                  <input type="text" name="attributes[][attribute_value]" class="form-control form-control-sm" placeholder="Nilai" value="{{ $item->attribute_value }}">
                </div>
                <div class="col-auto">
                  <button type="button" class="btn btn-outline-danger-600 btn-icon btn-sm" data-repeat-remove title="Hapus baris">
                    <i class="ri-delete-bin-6-line"></i>
                  </button>
                </div>
              </div>
            @endforeach
          </div>
          <p class="repeat-empty-hint text-secondary-light text-sm mb-0" data-empty-for="attributes" @style(['display: none' => $attributes->isNotEmpty()])>
            Belum ada atribut — klik "Tambah Baris" untuk menambahkan.
          </p>
        </div>
        <template id="tpl-attributes">
          <div class="row g-2 align-items-center repeat-row-box repeat-row">
            <div class="col-5">
              <input type="text" name="attributes[][attribute_name]" class="form-control form-control-sm" placeholder="Nama Atribut">
            </div>
            <div class="col-6">
              <input type="text" name="attributes[][attribute_value]" class="form-control form-control-sm" placeholder="Nilai">
            </div>
            <div class="col-auto">
              <button type="button" class="btn btn-outline-danger-600 btn-icon btn-sm" data-repeat-remove title="Hapus baris">
                <i class="ri-delete-bin-6-line"></i>
              </button>
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
  const imageInput = document.getElementById('image');
  const imagePreview = document.getElementById('image-preview');
  if (imageInput && imagePreview) {
    imageInput.addEventListener('change', () => {
      const file = imageInput.files[0];
      if (!file) {
        imagePreview.classList.add('d-none');
        imagePreview.removeAttribute('src');
        return;
      }
      imagePreview.src = URL.createObjectURL(file);
      imagePreview.classList.remove('d-none');
    });
  }

  function refreshEmptyHint(key) {
    const container = document.getElementById('rows-' + key);
    const hint = document.querySelector('[data-empty-for="' + key + '"]');
    if (container && hint) {
      hint.style.display = container.children.length === 0 ? '' : 'none';
    }
  }

  document.addEventListener('click', (e) => {
    const addBtn = e.target.closest('[data-repeat-add]');
    if (addBtn) {
      const key = addBtn.getAttribute('data-repeat-add');
      const tpl = document.getElementById('tpl-' + key);
      const container = document.getElementById('rows-' + key);
      if (tpl && container) {
        container.appendChild(tpl.content.cloneNode(true));
        refreshEmptyHint(key);
      }
      return;
    }
    const removeBtn = e.target.closest('[data-repeat-remove]');
    if (removeBtn) {
      const container = removeBtn.closest('[id^="rows-"]');
      removeBtn.closest('.repeat-row')?.remove();
      if (container) {
        refreshEmptyHint(container.id.replace('rows-', ''));
      }
    }
  });
})();
</script>
@endsection
