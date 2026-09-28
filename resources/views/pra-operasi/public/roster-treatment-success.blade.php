<!DOCTYPE html>
<html lang="id">
<head>
   <meta charset="utf-8"/>
   <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
   <title>Berhasil Dikirim — Bukti Treatment</title>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"/>
   <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
   <style>
      body { margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', system-ui, sans-serif; background: linear-gradient(160deg, #ecfdf5 0%, #f8fafc 50%, #eef2ff 100%); padding: 1.25rem; }
      .card { max-width: 460px; width: 100%; background: #fff; border-radius: 24px; box-shadow: 0 20px 50px -20px rgba(15,23,42,.15); overflow: hidden; text-align: center; }
      .top { height: 6px; background: linear-gradient(90deg, #059669, #3952bc); }
      .body { padding: 2rem 1.5rem 1.75rem; }
      .icon { width: 4.5rem; height: 4.5rem; margin: 0 auto 1rem; border-radius: 999px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; }
      .material-symbols-outlined { font-size: 2.4rem; }
      h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #0f172a; }
      p { color: #64748b; line-height: 1.55; font-size: .93rem; margin: .75rem 0 0; }
      .meta { margin-top: 1.25rem; padding: 1rem; border-radius: 14px; background: #f8fafc; text-align: left; font-size: .86rem; }
      .meta div { display: flex; justify-content: space-between; gap: 1rem; padding: .35rem 0; }
      .meta span { color: #64748b; }
      .meta strong { color: #0f172a; font-weight: 700; }
      .btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; margin-top: 1.35rem; padding: .85rem 1.25rem; border-radius: 12px; border: 0; background: linear-gradient(135deg, #3952bc, #2b45af); color: #fff; font-weight: 700; text-decoration: none; font-size: .9rem; }
   </style>
</head>
<body>
   <div class="card">
      <div class="top"></div>
      <div class="body">
         <div class="icon"><span class="material-symbols-outlined">check_circle</span></div>
         <h1>Bukti Treatment Terkirim!</h1>
         <p>Terima kasih. Tim admin akan meninjau pengajuan Anda. Simpan informasi di bawah sebagai referensi.</p>
         <div class="meta">
            @if ($nama !== '')
            <div><span>Nama Pengaju</span><strong>{{ $nama }}</strong></div>
            @endif
            @if ($submittedAt !== '')
            <div><span>Waktu kirim</span><strong>{{ $submittedAt }}</strong></div>
            @endif
            <div><span>Status</span><strong>Menunggu review</strong></div>
         </div>
         <a href="{{ route('roster-treatment.public.form') }}" class="btn">
            <span class="material-symbols-outlined" style="font-size:18px">add</span>
            Kirim pengajuan lain
         </a>
      </div>
   </div>
</body>
</html>
