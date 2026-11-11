<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>BeSentry Sudah Pindah</title>
  <link rel="icon" href="{{ URL::asset('build/images/favicon.ico') }}">
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 16px;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      color: #1f2937;
      background: #1f2a37;
    }
    .latar {
      position: fixed;
      inset: 0;
      background: url("{{ URL::asset('build/images/login-background.png') }}") center / cover no-repeat;
      filter: blur(12px) brightness(0.75);
      transform: scale(1.05);
    }
    .kartu {
      position: relative;
      width: 100%;
      max-width: 520px;
      background: #fff;
      border-radius: 20px;
      padding: 40px 32px 32px;
      text-align: center;
      box-shadow: 0 25px 70px rgba(0, 0, 0, .35);
    }
    .logo { display: block; height: 56px; margin: 0 auto 20px; }
    .label {
      display: inline-block;
      font-size: 13px;
      font-weight: 600;
      color: #b45309;
      background: #fef3c7;
      border-radius: 999px;
      padding: 4px 12px;
      margin-bottom: 16px;
    }
    h1 { font-size: 24px; margin: 0 0 12px; line-height: 1.3; }
    p { margin: 0 0 24px; line-height: 1.6; color: #4b5563; }
    .alamat {
      display: block;
      font-family: ui-monospace, Consolas, monospace;
      font-size: 15px;
      background: #f3f4f6;
      border-radius: 10px;
      padding: 12px;
      margin-bottom: 24px;
      word-break: break-all;
      color: #111827;
    }
    .tombol {
      display: block;
      background: #0f766e;
      color: #fff;
      text-decoration: none;
      font-weight: 600;
      font-size: 16px;
      padding: 14px 20px;
      border-radius: 12px;
    }
    .tombol:hover { background: #115e59; }
    .tombol:focus-visible { outline: 3px solid #fbbf24; outline-offset: 2px; }
    .catatan { margin: 20px 0 0; font-size: 13px; color: #6b7280; }
  </style>
</head>
<body>
  <div class="latar" aria-hidden="true"></div>
  <main class="kartu">
    <img class="logo" src="{{ URL::asset('build/images/logo-removebg.png') }}" alt="Berau Coal"
         onerror="this.src='{{ URL::asset('build/images/logo.jpg') }}'">
    <span class="label">Pemberitahuan</span>
    <h1>BeSentry sudah pindah alamat</h1>
    <p>Mulai sekarang aplikasi BeSentry diakses melalui alamat baru di bawah ini.
       Silakan login di sana dan perbarui bookmark Anda.</p>
    <span class="alamat">{{ $tujuan }}</span>
    <a class="tombol" href="{{ $tujuan }}">Buka BeSentry yang baru</a>
    <p class="catatan">Akun dan password tetap sama.</p>
  </main>
</body>
</html>
