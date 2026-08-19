<div class="table-responsive scroll-sm">
  <table class="table bordered-table mb-0">
    <thead>
      <tr>
        <th scope="col">Task Name </th>
        <th scope="col">Assigned To </th>
        <th scope="col">Due Date</th>
        <th scope="col">Status</th>
        <th scope="col">Action</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $row)
      <tr>
        <td>
          <div>
            <span class="text-md d-block line-height-1 fw-medium text-primary-light text-w-200-px">{{ $row['nama_pelanggaran'] }}</span>
            <span class="text-sm d-block fw-normal text-secondary-light">#{{ \Illuminate\Support\Str::limit($row['id_alert'], 8, '') }}</span>
          </div>
        </td>
        <td>{{ $row['nama'] }}</td>
        <td>{{ $row['waktu'] }}</td>
        <td> <span class="{{ $row['status_class'] }} px-24 py-4 rounded-pill fw-medium text-sm">{{ $row['status_label'] }}</span> </td>
        <td class="text-center text-neutral-700 text-xl">
          <div class="dropdown">
            <button type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <iconify-icon icon="ph:dots-three-outline-vertical-fill" class="icon"></iconify-icon>
            </button>
            <ul class="dropdown-menu p-12 border bg-base shadow">
              <li><a class="dropdown-item px-16 py-8 rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900" href="javascript:void(0)">Action</a></li>
              <li><a class="dropdown-item px-16 py-8 rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900" href="javascript:void(0)">Another action</a></li>
              <li><a class="dropdown-item px-16 py-8 rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900" href="javascript:void(0)">Something else here</a></li>
            </ul>
          </div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="5" class="text-center text-secondary-light">No data</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>
