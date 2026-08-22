@extends('OhsDashboard.layouts.app')

@section('content')
<section class="ohs-page" data-ohs-page="admin">
    <div class="ohs-page-head">
        <div>
            <h1>Admin Scheduler</h1>
            <p class="lead">Atur digest email, overdue reminder, dan sinkronisasi roster HSE. Status last run tampil di kartu monitoring.</p>
        </div>
        <button type="button" class="btn-ghost" id="admin-refresh">Refresh Settings</button>
    </div>
    <div id="admin-status" class="ohs-kpis"></div>
    <article class="ohs-card">
        <h3>Email &amp; automation</h3>
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
                <label class="chk"><input type="checkbox" name="IncludeLeaveSummary"> Include Leave Summary</label>
                <label class="chk"><input type="checkbox" name="IncludeTrackerSummary"> Include Tracker Summary</label>
                <label class="chk"><input type="checkbox" name="IncludeLeaderboard"> Include Leaderboard</label>
            </div>
            <div class="ohs-actions">
                <button type="submit" class="btn-primary">Save</button>
                <button type="button" class="btn-ghost" id="admin-send">Send Now</button>
                <button type="button" class="btn-ghost" id="admin-test">Test Email</button>
                <button type="button" class="btn-ghost" id="admin-overdue">Overdue Reminder</button>
                <button type="button" class="btn-ghost" id="admin-hse">Sync HSE Now</button>
            </div>
            <p id="admin-note" class="ohs-muted"></p>
        </form>
    </article>
</section>
@endsection
