@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Progress Penyelesaian Rekayasa — Overall Perusahaan')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@endpush

@section('content')
@php
   $pctClass = static fn (int $progress): string => match (true) {
      $progress >= 100 => 'crm-pct--green',
      $progress >= 70 => 'crm-pct--amber',
      $progress >= 40 => 'crm-pct--orange',
      default => 'crm-pct--red',
   };
   $barClass = static fn (int $progress): string => match (true) {
      $progress >= 100 => 'mse-prog-bar--green',
      $progress >= 70 => 'mse-prog-bar--amber',
      $progress >= 40 => 'mse-prog-bar--orange',
      default => 'mse-prog-bar--red',
   };
   $dateFromDisplay = ($filters['date_from'] ?? '') !== '' ? date('d/m/Y', strtotime($filters['date_from'])) : '';
   $dateToDisplay = ($filters['date_to'] ?? '') !== '' ? date('d/m/Y', strtotime($filters['date_to'])) : '';
@endphp

<form method="GET" action="{{ route('monitoring-safety-engineering.company-overview') }}" class="crm-filter-bar">
   <div class="crm-filter-field crm-filter-field--bar">
      <label class="crm-filter-label" for="mse-ov-filter-bar">Site</label>
      <select id="mse-ov-filter-bar" name="bar" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['bars'] ?? [] as $key => $label)
         <option value="{{ $key }}" @selected(($filters['bar'] ?? '') === (string) $key)>{{ $label }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--company">
      <label class="crm-filter-label" for="mse-ov-filter-company">Perusahaan</label>
      <select id="mse-ov-filter-company" name="company" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['companies'] ?? [] as $key => $label)
         <option value="{{ $key }}" @selected(($filters['company'] ?? '') === (string) $key)>{{ $label }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--dates">
      <label class="crm-filter-label" for="mse-ov-filter-date-from">Periode YTD</label>
      <div class="crm-filter-dates">
         <label class="crm-filter-date-box" for="mse-ov-filter-date-from">
            <span class="crm-filter-date-display" id="mse-ov-date-from-display">{{ $dateFromDisplay }}</span>
            <input
               type="date"
               id="mse-ov-filter-date-from"
               name="date_from"
               class="crm-filter-date-input"
               value="{{ $filters['date_from'] ?? '' }}"
               onchange="this.form.submit()"
            >
         </label>
         <span class="crm-filter-date-sep">–</span>
         <label class="crm-filter-date-box" for="mse-ov-filter-date-to">
            <span class="crm-filter-date-display" id="mse-ov-date-to-display">{{ $dateToDisplay }}</span>
            <input
               type="date"
               id="mse-ov-filter-date-to"
               name="date_to"
               class="crm-filter-date-input"
               value="{{ $filters['date_to'] ?? '' }}"
               onchange="this.form.submit()"
            >
         </label>
      </div>
   </div>
</form>

<div class="crm-card">
   <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
      <div>
         <p class="crm-card-title mb-0">Overall Progress per Perusahaan</p>
         <p class="text-xs text-crm-muted mt-1">
            Data progres sama dengan Dashboard Komitmen (Replikasi, Safety Engineering, Additional Safety) · OP = On Progress · OV = Overdue · OK = Selesai
         </p>
      </div>
      <p class="text-xs text-crm-muted">
         {{ number_format(count($companyRows)) }} perusahaan
      </p>
   </div>

   <div class="crm-data-table-wrap overflow-x-auto">
      <table class="crm-data-table mse-ov-table mse-ov-company-table">
         <thead>
            <tr>
               <th rowspan="2" class="w-10 text-center">No</th>
               <th rowspan="2" class="mse-ov-col-company">Perusahaan</th>
               <th colspan="5" class="text-center mse-ov-group mse-ov-group--total">Total Komitmen</th>
               <th colspan="5" class="text-center mse-ov-group mse-ov-group--replikasi">Replikasi</th>
               <th colspan="5" class="text-center mse-ov-group mse-ov-group--safety">Safety Engineering</th>
               <th colspan="5" class="text-center mse-ov-group mse-ov-group--additional">Additional Safety</th>
            </tr>
            <tr>
               @foreach(['total', 'replikasi', 'safety', 'additional'] as $group)
               <th class="text-center">Item</th>
               <th class="text-center">OP</th>
               <th class="text-center">OV</th>
               <th class="text-center">OK</th>
               <th class="text-center" style="min-width:6.5rem">Progress</th>
               @endforeach
            </tr>
         </thead>
         <tbody>
            @forelse($companyRows as $index => $row)
            @php
               $groups = [
                  $row['total'],
                  $row['replikasi'],
                  $row['safety_engineering'],
                  $row['additional_safety_engineering'],
               ];
            @endphp
            <tr>
               <td class="text-center text-crm-muted">{{ $index + 1 }}</td>
               <td class="font-semibold text-[#1E293B]">{{ $row['perusahaan'] }}</td>
               @foreach($groups as $stat)
               @php $progress = (int) ($stat['progress'] ?? 0); @endphp
               <td class="text-center">{{ number_format((int) ($stat['count'] ?? 0)) }}</td>
               <td class="text-center">
                  <span class="crm-trend-chip crm-trend-chip--info">{{ (int) ($stat['onprogress'] ?? 0) }}</span>
               </td>
               <td class="text-center">
                  <span class="crm-trend-chip crm-trend-chip--danger">{{ (int) ($stat['overdue'] ?? 0) }}</span>
               </td>
               <td class="text-center">
                  <span class="crm-trend-chip crm-trend-chip--success">{{ (int) ($stat['selesai'] ?? 0) }}</span>
               </td>
               <td>
                  <div class="mse-prog">
                     <div class="mse-prog-track">
                        <div class="mse-prog-bar {{ $barClass($progress) }}" style="width: {{ min(100, max(0, $progress)) }}%"></div>
                     </div>
                     <span class="mse-prog-pct {{ $pctClass($progress) }}">{{ $progress }}%</span>
                  </div>
               </td>
               @endforeach
            </tr>
            @empty
            <tr>
               <td colspan="22" class="text-center text-crm-muted py-8">
                  Belum ada data komitmen untuk filter yang dipilih.
               </td>
            </tr>
            @endforelse

            @if(count($companyRows) > 0)
            @php
               $totalGroups = [
                  $totals['total'],
                  $totals['replikasi'],
                  $totals['safety_engineering'],
                  $totals['additional_safety_engineering'],
               ];
            @endphp
            <tr class="mse-ov-total-row">
               <td></td>
               <td class="font-bold text-[#1E3A8A]">TOTAL</td>
               @foreach($totalGroups as $stat)
               @php $progress = (int) ($stat['progress'] ?? 0); @endphp
               <td class="text-center font-bold">{{ number_format((int) ($stat['count'] ?? 0)) }}</td>
               <td class="text-center font-bold">{{ (int) ($stat['onprogress'] ?? 0) }}</td>
               <td class="text-center font-bold">{{ (int) ($stat['overdue'] ?? 0) }}</td>
               <td class="text-center font-bold">{{ (int) ($stat['selesai'] ?? 0) }}</td>
               <td class="font-bold text-[#1E3A8A]">{{ $progress }}%</td>
               @endforeach
            </tr>
            @endif
         </tbody>
      </table>
   </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
   function bindDateDisplay(inputId, displayId) {
      var input = document.getElementById(inputId);
      var display = document.getElementById(displayId);
      if (!input || !display) return;

      function sync() {
         if (!input.value) {
            display.textContent = '';
            return;
         }
         var parts = input.value.split('-');
         if (parts.length === 3) {
            display.textContent = parts[2] + '/' + parts[1] + '/' + parts[0];
         }
      }

      input.addEventListener('change', sync);
      sync();
   }

   bindDateDisplay('mse-ov-filter-date-from', 'mse-ov-date-from-display');
   bindDateDisplay('mse-ov-filter-date-to', 'mse-ov-date-to-display');
});
</script>
@endpush
