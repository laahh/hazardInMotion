@extends('OhsDashboard.layouts.checkin')

@section('content')
<div class="wrap">
    <div id="loadingScreen" class="state-screen">
        <div class="spinner"></div>
        <p>Memuat data event...</p>
    </div>

    <div id="errorScreen" class="state-screen hide">
        <div class="state-icon error">&#9888;&#65039;</div>
        <h2>Tidak Bisa Memuat</h2>
        <p id="errorText">Terjadi kesalahan.</p>
    </div>

    <div id="successScreen" class="state-screen hide">
        <div id="successIcon" class="state-icon success">&#9989;</div>
        <h2 id="successTitle">Absensi Tercatat</h2>
        <p id="successText"></p>
    </div>

    <div id="formScreen" class="hide" style="display:flex;flex-direction:column;flex:1;">
        <div class="header">
            <div class="header-top">
                <div class="header-icon">&#128203;</div>
                <div class="badge">Absensi Online</div>
            </div>
            <h1 id="eventName"></h1>
            <div class="meta" id="eventMeta"></div>
        </div>
        <div class="body">
            <div class="field-label">Cari &amp; Pilih Nama Anda</div>
            <div class="search-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input id="search" type="text" placeholder="Ketik nama Anda..." autocomplete="off">
            </div>
            <div id="empList"></div>

            <div id="selectedBox" class="selected-box">
                <div class="emp-avatar" id="selectedAvatar"></div>
                <div>
                    <div class="label">Nama Terpilih</div>
                    <div class="value" id="selectedName"></div>
                </div>
            </div>

            <div id="formMsg"></div>

            <button id="submitBtn" class="btn" disabled>
                <span class="btn-spinner"></span>
                <span class="btn-label">Absen Sekarang</span>
            </button>
        </div>
        <div class="footer-note">OHS Portal &middot; Absensi Digital</div>
    </div>
</div>
@endsection
