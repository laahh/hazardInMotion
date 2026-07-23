@php
  /** @var array<string, mixed> $section */
  $available = (bool) ($section['available'] ?? false);
  $columns = $section['columns'] ?? [];
  $rows = $section['rows'] ?? [];
  $total = (int) ($section['total'] ?? 0);
  $truncated = (bool) ($section['truncated'] ?? false);
  $action = trim((string) ($section['action'] ?? ''));
@endphp

<div style="margin:0 0 16px;">
  <p style="margin:0 0 8px;font-size:13px;line-height:1.5;color:#0f172a;">
    <strong>{{ $number }}. {{ $section['title'] }}:</strong>
    <span style="color:#0f766e;font-weight:800;">{{ $section['value'] ?? '—' }}</span>
    @if($action !== '')
      <span style="color:#334155;"> — {{ $action }}</span>
    @endif
  </p>

  @if(! $available)
    <div style="border:1px dashed #cbd5e1;border-radius:10px;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;">
      {{ $section['note'] ?? 'Data belum tersedia.' }}
    </div>
  @elseif($rows === [])
    <div style="border:1px solid #dcfce7;border-radius:10px;padding:12px 14px;background:#f0fdf4;color:#166534;font-size:12px;">
      Tidak ada data pada scope ini.
    </div>
  @else
    <div class="scroll-box">
      <table role="presentation" class="detail-table" width="100%" cellpadding="0" cellspacing="0">
        <thead>
          <tr>
            @foreach($columns as $column)
              <th>{{ $column['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $row)
            <tr>
              @foreach($columns as $column)
                <td>{{ $row[$column['key']] ?? '—' }}</td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @if($truncated && ! empty($section['detail_url']))
      <p style="margin:6px 0 0;font-size:11px;color:#64748b;">
        Menampilkan preview {{ count($rows) }} dari {{ number_format($total) }} baris.
        <a href="{{ $section['detail_url'] }}" style="color:#0f766e;font-weight:700;" target="_blank" rel="noopener">Lihat semua di dashboard</a>
      </p>
    @endif
  @endif
</div>
