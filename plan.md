Panel Evaluasi & Monitoring Karyawan (Laravel)

Panel admin terpisah (Laravel) untuk memantau & mengevaluasi seluruh karyawan dari semua fitur aplikasi health. Membaca DB MySQL health_app secara read-only; data MCU diambil lewat endpoint API app Node.

Prinsip Arsitektur





Laravel = konsumen read-only DB app. TIDAK menulis ke tabel milik app. Migrasi app tetap manual/terkonfirmasi (sesuai aturan: tidak ada migrate otomatis).



Koneksi DB: gunakan read replica MySQL bila tersedia; fallback ke primary read-only user.



MCU (Postgres via SSH tunnel) diakses lewat endpoint baru di app Node, bukan koneksi langsung, agar logika tunnel & mapping SID tidak diduplikasi. Lihat server/src/repositories/mcuPg.repository.js dan server/src/services/mcu.service.js.



Tabel milik Laravel sendiri (koneksi DB terpisah): admin_users, roles/permissions, admin_action_audit, report_configs, dan tabel summary agregat.



Dashboard membaca tabel summary (diisi job terjadwal), bukan query berat langsung ke tabel transaksi — kritikal untuk skala 20k user.

Peran & Akses (RBAC)





Super Admin, HR/Admin Pusat, Manajer/Atasan (scoped per divisi/nama_perusahaan/site), Auditor/Viewer.



Scoping memakai kolom di employee_profiles (lihat EMPLOYEE_COLUMNS di server/src/repositories/employeeProfile.repository.js).



Setiap akses data sensitif (MCU/risiko) dicatat ke admin_action_audit.

Pemetaan Sumber Data





Karyawan & login: employee_profiles, login_audit (lihat mysql/migrations/018_login_audit.sql)



Olahraga: workout_analyses, workout log, strava_connections, strava_activities (+_laps/_splits/_streams/_photos), exercises dkk



Nutrisi: food_catalog, food_analyses, food_analysis_components, food log



Kesehatan/MCU: Postgres MCU via API Node, daily_health_scores, health alerts



Goal: user_goals, goal_types, goal_daily_targets, goal_milestones, recommendation_logs, goal_adjustment_logs



Kognitif: cognitive_pvt_results, cognitive_memory_results, cognitive_test_sessions



Komunitas: communities, community_members, community_events, community_posts, community_*, badges, competitions



Main Bareng: open_play_events, open_play_participants, open_play_messages



Referensi query siap pakai: mysql/reports/usage_queries.sql

Arsitektur Data (ringkas)

flowchart LR
  subgraph laravel [Panel Laravel]
    UI[Dashboard & Modul]
    Jobs[Scheduler/Queue Agregasi]
    OwnDB[(DB Panel: admin, audit, summary)]
  end
  UI --> OwnDB
  Jobs --> OwnDB
  Jobs -->|read-only| AppDB[(MySQL health_app / replica)]
  UI -->|read-only| AppDB
  UI -->|HTTP| NodeAPI[App Node API]
  NodeAPI -->|SSH tunnel| MCU[(Postgres MCU)]

Modul & Fitur





A. Dashboard ringkasan: KPI global, DAU/WAU/MAU, tren login, distribusi platform, filter perusahaan/site/divisi/tanggal.



B. Adopsi & keaktifan: belum pernah login, login terakhir + login_count, tren, leaderboard adopsi divisi.



C. Direktori karyawan + Profil 360 (gabungan semua modul per karyawan).



D. Evaluasi Olahraga: frekuensi/durasi/jenis, data manual + Strava, status koneksi Strava, leaderboard olahraga.



E. Evaluasi Nutrisi: kalori & makro vs target, konsistensi log.



F. Kesehatan & Risiko: ringkasan MCU (via API Node), distribusi risiko, daftar prioritas tindak lanjut (akses ketat + audit).



G. Goal & Progress: goal aktif, pencapaian, milestone, penyesuaian.



H. Kognitif: tren PVT/memory, karyawan skor menurun.



I. Komunitas & engagement: partisipasi event, komunitas aktif, sparring/kompetisi/badge, Main Bareng.



J. Keamanan & audit: login gagal per SID/IP (usage_queries.sql #4/#4b), anomali, audit aksi admin.



K. Laporan & ekspor: laporan periodik per divisi/perusahaan, ekspor Excel/PDF, terjadwal via email.



L. Alerting: risiko tinggi, adopsi rendah, lonjakan login gagal, tidak aktif > X hari.

Referensi Skema Kolom (yang dipakai panel)

Semua tabel di DB health_app (MySQL). Kunci relasi utama: *.user_id = employee_profiles.id (BIGINT). Timestamp umumnya DATETIME(3); timezone user ada di user_profiles.timezone (default Asia/Jakarta) sehingga grouping per hari lokal sebaiknya pakai CONVERT_TZ(col,'+00:00','+07:00') bila server UTC.

Identitas & sesi:





employee_profiles: id, nik, nama, foto, avatar_url, site, usia, divisi, departement, dept_dic, kategori, kode_sid, masa_kerja, id_perusahaan, nama_perusahaan, level_jabatan, status_karyawan, kategori_karyawan, jabatan_fungsional, jabatan_struktural, membership_tier; + kolom dari 018: last_login_at, login_count, last_login_ip, last_platform. CATATAN: kolom password_hash JANGAN pernah dibaca panel.



login_audit (dari mysql/migrations/018_login_audit.sql): id, user_id (null saat gagal), kode_sid, event (login_success|login_failed), ip, user_agent, platform (web|android|ios), created_at.



user_history (feed CRUD generik, proxy keaktifan): user_id, item_id, payload (JSON), created_at, updated_at.

Olahraga (lihat mysql/migrations/005_workout_analyses.sql, mysql/migrations/011_strava.sql, mysql/migrations/017_strava_detail.sql):





workout_analyses (log manual/foto; ditulis oleh server/src/repositories/workoutAnalysis.repository.js): id, user_id, client_item_id, activity_type, calories_kcal (DECIMAL), avg_heart_rate, distance, workout_time, summary_text, created_at.



strava_connections (status koneksi): user_id (PK), athlete_id, athlete_firstname, athlete_lastname, connected_at, last_synced_at.



strava_activities: id, user_id, name, sport_type, type, distance_m (DOUBLE, meter), moving_time_s, elapsed_time_s, total_elevation_gain, calories, start_date, synced_at; + dari 017: average_heartrate, max_heartrate, average_speed, suffer_score, dll.



Katalog (referensi, jarang untuk evaluasi individu): exercises, muscles, body_parts, equipments, exercise_target_muscles, exercise_secondary_muscles, exercise_body_parts, exercise_equipments, exercise_instructions.

Nutrisi (lihat mysql/migrations/002_food_analyses.sql, mysql/migrations/012_food_log.sql; dibaca oleh server/src/repositories/foodLog.repository.js):





food_analyses (entri log makanan): id, user_id, client_item_id, food_name, meal_type (breakfast|lunch|dinner|snack), source_type (manual|photo|barcode), barcode, serving_label, total_calories, protein_g, fats_g, carbs_g, fiber_g, water_ml, created_at.



food_analysis_components: id, analysis_id, sort_order, component_name, component_detail.



food_catalog: id, name, brand, calories, protein_g, fats_g, carbs_g, serving_label, is_popular, sort_order.

Goal & skor harian (lihat mysql/migrations/006_goal_planner.sql):





user_goals: id, user_id, goal_type_id, goal_name, start_date, target_date, start_weight_kg, target_weight_kg, intensity_level, status (draft|active|paused|completed|cancelled), active_marker (1 saat aktif), created_at.



goal_types: id, code, name, is_active.



goal_daily_targets: user_goal_id, user_id, target_date, calorie_target, protein_target_g, carb_target_g, fat_target_g, step_target, exercise_duration_target_min.



daily_health_scores: user_goal_id, user_id, score_date, total_score, category (excellent|good|need_improvement|poor), calorie_score, protein_score, macro_score, exercise_score, consistency_score, habit_score, calorie_actual, protein_actual_g, carb_actual_g, fat_actual_g, exercise_actual_min, steps_actual, water_actual_ml.



goal_milestones, recommendation_logs, goal_adjustment_logs (kolom lihat migrasi 006).

Kognitif (lihat mysql/migrations/004_cognitive_tests.sql):





cognitive_pvt_results: user_id, mean_rt_ms, median_rt_ms, lapses, false_starts, passed, evaluation_label, tested_at.



cognitive_memory_results: user_id, max_span, score, passed, tested_at.



cognitive_test_sessions: user_id, session_id, overall_level (layak|waspada|tidak_layak), tested_at.

Komunitas & Main Bareng (lihat mysql/migrations/007_community.sql, mysql/migrations/008_main_bareng.sql):





communities (id, name, sport_key, city, member_count, is_popular), community_members (community_id, user_id, role, joined_at).



community_events (id, community_id, event_type, title, sport_key, starts_at, status), community_event_rsvps (event_id, user_id, status).



community_posts (author_user_id, like_count, comment_count, created_at), community_post_comments, community_post_likes.



community_player_stats (user_id, matches, wins, goals, assists, level_points), community_user_badges (user_id, badge_id), community_badges.



community_competitions, community_competition_entries (user_id/community_id, points, rank_no).



open_play_events (id, host_user_id, sport_key, starts_at, capacity, status), open_play_participants (event_id, user_id, status), open_play_messages.

Query per Modul (copy-paste, MySQL)

Login/adopsi dasar sudah tersedia di mysql/reports/usage_queries.sql (#1-#6). Berikut tambahan per modul (ganti interval sesuai kebutuhan; tambahkan filter scope AND e.divisi = ?/AND e.nama_perusahaan = ? untuk peran Manajer).

Keaktifan via CRUD (proxy tanpa perlu login ulang):

SELECT e.kode_sid, e.nama, e.divisi,
       MAX(h.created_at) AS aktivitas_terakhir,
       COUNT(*) AS total_aktivitas_30h
FROM employee_profiles e
JOIN user_history h ON h.user_id = e.id
WHERE h.created_at >= NOW() - INTERVAL 30 DAY
GROUP BY e.id, e.kode_sid, e.nama, e.divisi
ORDER BY aktivitas_terakhir DESC;

Olahraga - status koneksi Strava:

SELECT e.kode_sid, e.nama, e.divisi,
       CASE WHEN c.user_id IS NULL THEN 'belum' ELSE 'terhubung' END AS strava,
       c.last_synced_at
FROM employee_profiles e
LEFT JOIN strava_connections c ON c.user_id = e.id
ORDER BY strava, e.nama;

Olahraga - rekap Strava per user (30 hari):

SELECT e.kode_sid, e.nama, e.divisi,
       COUNT(a.id) AS sesi,
       ROUND(SUM(a.distance_m)/1000, 1) AS total_km,
       ROUND(SUM(a.moving_time_s)/60) AS total_menit,
       ROUND(SUM(a.calories)) AS total_kalori,
       ROUND(AVG(a.average_heartrate)) AS avg_hr
FROM employee_profiles e
LEFT JOIN strava_activities a
       ON a.user_id = e.id AND a.start_date >= NOW() - INTERVAL 30 DAY
GROUP BY e.id, e.kode_sid, e.nama, e.divisi
ORDER BY total_km DESC;

Olahraga - rekap manual (workout_analyses, 30 hari):

SELECT e.kode_sid, e.nama,
       COUNT(w.id) AS sesi_manual,
       ROUND(SUM(w.calories_kcal)) AS kalori_manual
FROM employee_profiles e
LEFT JOIN workout_analyses w
       ON w.user_id = e.id AND w.created_at >= NOW() - INTERVAL 30 DAY
GROUP BY e.id, e.kode_sid, e.nama
ORDER BY sesi_manual DESC;

Olahraga - distribusi jenis & leaderboard divisi:

SELECT COALESCE(sport_type, type, 'lainnya') AS jenis, COUNT(*) AS sesi
FROM strava_activities
WHERE start_date >= NOW() - INTERVAL 30 DAY
GROUP BY jenis ORDER BY sesi DESC;

SELECT e.divisi, COUNT(a.id) AS sesi, ROUND(SUM(a.distance_m)/1000,1) AS km
FROM employee_profiles e
JOIN strava_activities a ON a.user_id = e.id AND a.start_date >= NOW() - INTERVAL 30 DAY
GROUP BY e.divisi ORDER BY km DESC;

Nutrisi - konsistensi log & total makro per user (7 hari):

SELECT e.kode_sid, e.nama,
       COUNT(*) AS entri,
       COUNT(DISTINCT DATE(f.created_at)) AS hari_tercatat,
       ROUND(SUM(f.total_calories)) AS total_kalori,
       ROUND(SUM(f.protein_g)) AS total_protein_g,
       ROUND(SUM(f.carbs_g)) AS total_karbo_g,
       ROUND(SUM(f.fats_g)) AS total_lemak_g
FROM employee_profiles e
JOIN food_analyses f ON f.user_id = e.id AND f.created_at >= NOW() - INTERVAL 7 DAY
GROUP BY e.id, e.kode_sid, e.nama
ORDER BY hari_tercatat DESC;

Nutrisi - kalori aktual vs target (via goal):

SELECT e.kode_sid, e.nama, s.score_date,
       s.calorie_actual, t.calorie_target,
       ROUND(s.calorie_actual - t.calorie_target) AS selisih
FROM daily_health_scores s
JOIN employee_profiles e ON e.id = s.user_id
JOIN goal_daily_targets t ON t.user_id = s.user_id AND t.target_date = s.score_date
WHERE s.score_date >= NOW() - INTERVAL 7 DAY
ORDER BY s.score_date DESC;

Goal - goal aktif & adopsi:

SELECT e.kode_sid, e.nama, gt.name AS goal, g.status,
       g.start_date, g.target_date, g.start_weight_kg, g.target_weight_kg
FROM user_goals g
JOIN employee_profiles e ON e.id = g.user_id
JOIN goal_types gt ON gt.id = g.goal_type_id
WHERE g.status = 'active'
ORDER BY g.target_date ASC;

SELECT
  (SELECT COUNT(DISTINCT user_id) FROM user_goals WHERE status='active') AS punya_goal_aktif,
  (SELECT COUNT(*) FROM employee_profiles) AS total_karyawan;

Goal - rata-rata health score 7 hari (naik-turun):

SELECT e.kode_sid, e.nama, ROUND(AVG(s.total_score),1) AS avg_score,
       MAX(s.score_date) AS terakhir
FROM daily_health_scores s
JOIN employee_profiles e ON e.id = s.user_id
WHERE s.score_date >= NOW() - INTERVAL 7 DAY
GROUP BY e.id, e.kode_sid, e.nama
ORDER BY avg_score ASC;

Kognitif - level kelayakan terbaru & yang perlu perhatian:

SELECT e.kode_sid, e.nama, cs.overall_level, cs.tested_at
FROM cognitive_test_sessions cs
JOIN employee_profiles e ON e.id = cs.user_id
WHERE cs.overall_level IN ('waspada','tidak_layak')
  AND cs.tested_at >= NOW() - INTERVAL 7 DAY
ORDER BY cs.tested_at DESC;

SELECT user_id, DATE(tested_at) AS d,
       ROUND(AVG(mean_rt_ms)) AS avg_rt_ms, SUM(lapses) AS total_lapses
FROM cognitive_pvt_results
WHERE tested_at >= NOW() - INTERVAL 30 DAY
GROUP BY user_id, DATE(tested_at)
ORDER BY d DESC;

Komunitas & Main Bareng - partisipasi:

SELECT e.kode_sid, e.nama, COUNT(r.id) AS event_diikuti
FROM community_event_rsvps r
JOIN employee_profiles e ON e.id = r.user_id
WHERE r.status = 'joined' AND r.created_at >= NOW() - INTERVAL 30 DAY
GROUP BY e.id, e.kode_sid, e.nama
ORDER BY event_diikuti DESC;

SELECT e.kode_sid, e.nama, COUNT(*) AS ikut_main_bareng
FROM open_play_participants op
JOIN employee_profiles e ON e.id = op.user_id
WHERE op.status = 'approved'
GROUP BY e.id, e.kode_sid, e.nama
ORDER BY ikut_main_bareng DESC;

Profil 360 (satu karyawan :userId) - jalankan tiap blok per tab:

-- identitas + sesi
SELECT id, kode_sid, nama, divisi, departement, nama_perusahaan, level_jabatan,
       last_login_at, login_count, last_platform
FROM employee_profiles WHERE id = :userId;
-- olahraga 30h (manual + strava), nutrisi 7h, goal aktif, kognitif terakhir:
SELECT COUNT(*) sesi_strava, ROUND(SUM(distance_m)/1000,1) km
FROM strava_activities WHERE user_id = :userId AND start_date >= NOW()-INTERVAL 30 DAY;
SELECT COUNT(*) entri, ROUND(SUM(total_calories)) kalori
FROM food_analyses WHERE user_id = :userId AND created_at >= NOW()-INTERVAL 7 DAY;
SELECT gt.name, g.status, g.target_date FROM user_goals g
JOIN goal_types gt ON gt.id=g.goal_type_id WHERE g.user_id = :userId AND g.status='active';
SELECT overall_level, tested_at FROM cognitive_test_sessions
WHERE user_id = :userId ORDER BY tested_at DESC LIMIT 1;

Tabel Summary Agregat (milik DB panel, diisi job nightly)

Untuk skala 20k user, dashboard membaca tabel ini, bukan query di atas secara langsung:





daily_user_activity_summary: user_id, date, login_count, crud_events, workout_sessions, strava_km, food_entries, food_kcal, health_score, cognitive_level, is_active.



daily_org_summary: date, nama_perusahaan, divisi, dau, wau, mau, avg_workout_per_user, avg_health_score, pct_has_goal, alert_count.
Job Laravel (scheduler) menjalankan query per modul di atas dengan GROUP BY user_id/divisi/tanggal lalu upsert ke tabel summary.

Non-Fungsional





Read replica + job agregasi harian ke tabel summary; caching Redis untuk query berat.



RBAC + scoping per divisi/perusahaan/site; audit akses data sensitif.



Read-only ke DB app; lapisan repository/mapping di Laravel agar perubahan skema app tidak langsung merusak panel.



Kepatuhan privasi data kesehatan (minimalkan data per peran).

Fase Implementasi





Fase 1: Scaffolding Laravel, koneksi read-only DB, RBAC, Dashboard ringkasan, Adopsi/login, Direktori karyawan.



Fase 2: Profil 360, Evaluasi Olahraga (+Strava), Evaluasi Nutrisi.



Fase 3: Endpoint MCU di Node + modul Kesehatan/Risiko, Goal, Kognitif.



Fase 4: Komunitas/engagement, Laporan & ekspor, Alerting, job agregasi + caching.

Risiko & Catatan





Endpoint MCU di app Node perlu auth service-to-service (token internal) + rate limit.



Tanpa last_active_at, "sedang aktif" diproksikan dari aktivitas CRUD; tambah last-seen bila butuh presisi.



Skema app dapat berubah; isolasi lewat repository mapping.



Libatkan kebijakan privasi internal sebelum menampilkan MCU/risiko ke atasan.

