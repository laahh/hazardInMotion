@extends('EmergencyResponse.layouts.app')

@section('page-title', $template->exists ? 'Edit Template Checklist' : 'Tambah Template Checklist')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header">
            <h6 class="mb-0">{{ $template->exists ? 'Edit' : 'Tambah' }} Template Checklist</h6>
        </div>
        <div class="card-body">
            <form action="{{ $template->exists ? route('emergency-response.master-data.checklist-templates.update', $template) : route('emergency-response.master-data.checklist-templates.store') }}" method="POST">
                @csrf
                @if ($template->exists)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" name="code" class="form-control" value="{{ old('code', $template->code) }}" required maxlength="50">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" required maxlength="255">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Berlaku Untuk</label>
                        <select name="applies_to" id="applies_to" class="form-control" required>
                            @foreach ($appliesToOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('applies_to', $template->applies_to) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3" id="wrap-equipment-category">
                        <label class="form-label">Kategori Equipment</label>
                        <select name="equipment_category_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($equipmentCategories as $category)
                                <option value="{{ $category->id }}" @selected(old('equipment_category_id', $template->equipment_category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3" id="wrap-safety-device-type">
                        <label class="form-label">Jenis Safety Device</label>
                        <select name="safety_device_type_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($safetyDeviceTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('safety_device_type_id', $template->safety_device_type_id) === $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $template->description) }}</textarea>
                </div>
                <div class="form-check form-switch mb-24">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="ct-active" @checked(old('is_active', $template->is_active ?? true))>
                    <label class="form-check-label" for="ct-active">Aktif</label>
                </div>

                <hr>
                <div class="d-flex align-items-center justify-content-between mb-16">
                    <h6 class="mb-0">Item Checklist</h6>
                    <button type="button" id="add-item" class="btn btn-sm btn-outline-primary-600"><i class="ri-add-line"></i> Tambah Item</button>
                </div>

                <div class="table-responsive">
                    <table class="table bordered-table mb-0" id="items-table">
                        <thead>
                            <tr>
                                <th style="width: 50%">Pertanyaan / Item</th>
                                <th style="width: 25%">Tipe Jawaban</th>
                                <th style="width: 10%">Wajib</th>
                                <th style="width: 15%"></th>
                            </tr>
                        </thead>
                        <tbody id="items-body">
                            @php $existingItems = old('items', $template->items->map(fn ($i) => ['item_text' => $i->item_text, 'answer_type' => $i->answer_type, 'is_required' => $i->is_required])->toArray()); @endphp
                            @forelse ($existingItems as $i => $item)
                                <tr class="item-row">
                                    <td><input type="text" name="items[{{ $i }}][item_text]" class="form-control" value="{{ $item['item_text'] }}" required></td>
                                    <td>
                                        <select name="items[{{ $i }}][answer_type]" class="form-control">
                                            @foreach ($answerTypeOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(($item['answer_type'] ?? 'compliance') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <input type="hidden" name="items[{{ $i }}][is_required]" value="0">
                                        <input type="checkbox" name="items[{{ $i }}][is_required]" value="1" class="form-check-input" @checked(!empty($item['is_required']))>
                                    </td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="ri-delete-bin-line"></i></button></td>
                                </tr>
                            @empty
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 mt-24">
                    <button type="submit" class="btn btn-primary-600">Simpan</button>
                    <a href="{{ route('emergency-response.master-data.checklist-templates.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <template id="item-row-template">
        <tr class="item-row">
            <td><input type="text" name="items[__INDEX__][item_text]" class="form-control" required></td>
            <td>
                <select name="items[__INDEX__][answer_type]" class="form-control">
                    @foreach ($answerTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </td>
            <td class="text-center">
                <input type="hidden" name="items[__INDEX__][is_required]" value="0">
                <input type="checkbox" name="items[__INDEX__][is_required]" value="1" class="form-check-input" checked>
            </td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="ri-delete-bin-line"></i></button></td>
        </tr>
    </template>
@endsection

@push('scripts')
    <script>
        (function () {
            var appliesTo = document.getElementById('applies_to');
            var wrapEquipment = document.getElementById('wrap-equipment-category');
            var wrapSafetyDevice = document.getElementById('wrap-safety-device-type');

            function toggleAppliesTo() {
                var isEquipment = appliesTo.value === 'emergency_equipment';
                wrapEquipment.style.display = isEquipment ? '' : 'none';
                wrapSafetyDevice.style.display = isEquipment ? 'none' : '';
            }
            appliesTo.addEventListener('change', toggleAppliesTo);
            toggleAppliesTo();

            var itemsBody = document.getElementById('items-body');
            var rowTemplate = document.getElementById('item-row-template');
            var nextIndex = itemsBody.querySelectorAll('.item-row').length;

            document.getElementById('add-item').addEventListener('click', function () {
                var html = rowTemplate.innerHTML.replaceAll('__INDEX__', nextIndex);
                var wrapper = document.createElement('tbody');
                wrapper.innerHTML = html;
                itemsBody.appendChild(wrapper.firstElementChild);
                nextIndex++;
            });

            itemsBody.addEventListener('click', function (e) {
                var btn = e.target.closest('.remove-item');
                if (btn) {
                    btn.closest('.item-row').remove();
                }
            });
        })();
    </script>
@endpush
