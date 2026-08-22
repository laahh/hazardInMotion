@extends('OhsDashboard.layouts.app')

@section('content')
<section class="ohs-page" data-ohs-page="admin">
    <div class="ohs-page-head">
        <div>
            <h1>Admin Email Scheduler</h1>
            <p class="lead">Atur email otomatis untuk reminder event, previous event, leave, serta Project &amp; Issue aktif. Termasuk overdue reminder dan sinkronisasi roster HSE.</p>
        </div>
        <button type="button" class="btn-ghost" id="admin-refresh">Refresh Settings</button>
    </div>
    <div id="admin-status" class="ohs-kpis"></div>
    <article class="ohs-card">
        <div class="card-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
            <div>
                <h3>Scheduler Configuration</h3>
                <p class="hint">Pengaturan hari dan jam pengiriman digest email portal.</p>
            </div>
            <span id="admin-trigger-badge" class="badge">Trigger: -</span>
        </div>
        <form id="admin-form" class="ohs-form">
            <label class="chk full"><input type="checkbox" name="Enabled"> Enable Auto Email</label>
            <div class="ohs-form-grid">
                <fieldset class="full">
                    <legend>Hari kirim</legend>
                    <label class="chk"><input type="checkbox" name="days" value="MON"> Sen</label>
                    <label class="chk"><input type="checkbox" name="days" value="TUE"> Sel</label>
                    <label class="chk"><input type="checkbox" name="days" value="WED"> Rab</label>
                    <label class="chk"><input type="checkbox" name="days" value="THU"> Kam</label>
                    <label class="chk"><input type="checkbox" name="days" value="FRI"> Jum</label>
                    <label class="chk"><input type="checkbox" name="days" value="SAT"> Sab</label>
                    <label class="chk"><input type="checkbox" name="days" value="SUN"> Min</label>
                </fieldset>
                <label>Jam
                    <select name="SendHour"></select>
                </label>
                <label>Menit
                    <select name="SendMinute">
                        <option value="0">00</option>
                        <option value="15">15</option>
                        <option value="30">30</option>
                        <option value="45">45</option>
                    </select>
                </label>
                <label class="full">Recipients<textarea name="Recipients" rows="3" placeholder="satu email per baris atau dipisah koma"></textarea></label>
                <label>CC<textarea name="Cc" rows="2"></textarea></label>
                <label>BCC<textarea name="Bcc" rows="2"></textarea></label>
                <label class="full">Web Portal URL <input name="PortalUrl" type="url" placeholder="https://"></label>
                <label>Overview Team <select name="OverviewTeam" id="admin-team"><option>All Teams</option></select></label>
                <label>Overview Site <select name="OverviewSite" id="admin-site"><option>All Sites</option></select></label>
                <label class="full">Subject prefix <input name="SubjectPrefix"></label>
                <label class="chk"><input type="checkbox" name="IncludeLeaveSummary"> Include Leave Status</label>
                <label class="chk"><input type="checkbox" name="IncludeTrackerSummary"> Include Active Project &amp; Issue</label>
                <label class="chk"><input type="checkbox" name="IncludeLeaderboard"> Include Leaderboard On Leave YTD</label>
            </div>
            <div class="ohs-actions">
                <button type="submit" class="btn-primary">Save Scheduler Settings</button>
                <button type="button" class="btn-ghost" id="admin-send">Run &amp; Send Now</button>
                <button type="button" class="btn-ghost" id="admin-test">Send Test Email</button>
                <button type="button" class="btn-ghost" id="admin-overdue">Send Overdue Reminder Now</button>
                <button type="button" class="btn-ghost" id="admin-hse">Sync HSE Now</button>
            </div>
            <p id="admin-note" class="ohs-muted"></p>
        </form>
    </article>
</section>
@endsection
