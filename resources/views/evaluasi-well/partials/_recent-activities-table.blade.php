<div class="table-responsive scroll-sm">
  <table class="table bordered-table mb-0">
    <thead>
      <tr>
        <th scope="col">Aktivitas</th>
        <th scope="col">Karyawan</th>
        <th scope="col">Waktu</th>
        <th scope="col">Tipe</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($items as $item)
      <tr>
        <td>
          <div>
            <span class="text-md d-block line-height-1 fw-medium text-primary-light text-w-200-px text-truncate" title="{{ $item['title'] }}">{{ $item['title'] }}</span>
            <span class="text-sm d-block fw-normal text-secondary-light">{{ $item['subtitle'] }}</span>
          </div>
        </td>
        <td>
          <a href="{{ route('evaluasi-well.employees.show', $item['user_id']) }}" class="text-primary-light hover-text-primary">
            {{ $item['user_name'] }}
          </a>
        </td>
        <td>{{ $item['at'] }}</td>
        <td>
          <span class="{{ $item['badge_class'] }} px-24 py-4 rounded-pill fw-medium text-sm">{{ $item['type'] }}</span>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="4" class="text-secondary-light text-sm">Belum ada aktivitas.</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>
