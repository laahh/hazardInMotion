# Prompting Stitch — Tool Inspection Monitoring Web App

Basis: modul nyata `/pnc-monitoring/inventory-inspection` (Laravel + Blade + Tailwind CDN,
mobile-first, lebar konten maksimum 480px, bottom nav 3 tab).

Cara pakai:

1. Buka https://stitch.withgoogle.com → **New project** → pilih mode **Web**.
2. Tempel **PROMPT 0** dulu, generate. Ini yang mengunci design system-nya.
3. Lanjut **PROMPT 1 → 6** satu per satu sebagai screen baru di project yang sama,
   selalu diakhiri baris `Keep the exact same design system as defined.`
4. Kalau hasilnya melenceng, pakai **PROMPT REFINEMENT** di bagian akhir — satu perubahan
   per prompt, karena Stitch lebih akurat kalau instruksinya sempit.

Prompt ditulis dalam bahasa Inggris karena Stitch paling akurat di bahasa Inggris, tapi
**semua teks yang tampil di UI tetap bahasa Indonesia** — sudah ditulis literal di dalam
prompt, jangan diterjemahkan.

---

## PROMPT 0 — Design system & app shell

```
Design a mobile-first web app called "Inspeksi Alat" — a field tool inspection monitoring
app for mining safety technicians (PNC Monitoring System). All UI text is in Indonesian.

DESIGN SYSTEM (apply to every screen, never deviate):
- Canvas: mobile web, 480px max content width, centered on desktop with the app surface
  color filling the viewport. Safe-area aware top and bottom.
- Palette (industrial safety green, light theme only):
  primary #0E8A4F, primary-dark #0A6B3E, primary-light #E3F5EA,
  app background (surface) #EFFAF4, card white #FFFFFF,
  surface-low #F4FBF7, surface #EAF6EF, surface-high #DFF0E6, surface-highest #D2E8DA,
  text primary #132A1D, text secondary #5C6F63, hairline border #D3E8DA,
  accent / danger (tertiary) #B3441F with tint #FDECE6,
  warning amber #B45309 with tint #FFFBEB.
- Typography: headings "Plus Jakarta Sans" ExtraBold (700-800), tight tracking.
  Body "Inter" 400-600. Section eyebrows: 10-11px, bold, UPPERCASE, wide letter-spacing.
- Icons: Google Material Symbols Outlined, weight 500, 18-24px. Active nav icons filled.
- Shape language: very rounded. Cards 24-32px radius, buttons and inputs 16px radius,
  chips and pills fully rounded. Soft subtle shadows only, no heavy drop shadows.
  1px hairline borders at about 40-60% opacity of the border color.
- Motion: press states scale down slightly (0.95), 150-200ms ease-out transitions.

APP SHELL (identical on every screen):
- Sticky top header on the app background color with a bottom hairline border. Left side:
  either a 36px two-tone green mountain/chevron logo mark, or a circular white back button
  with an "arrow_back" icon on detail screens. Next to it, two stacked lines: a tiny
  uppercase "PNC MONITORING" in secondary text, and the screen title in 18px ExtraBold
  below it. Right side: a 40px circular white button with a "notifications" icon and a
  small orange unread dot; on detail screens it becomes a rounded-square "apps" button.
- Fixed bottom navigation bar, white, top hairline border, 3 items, icon over a 10px bold
  label: "home / Beranda", "qr_code_scanner / Scan", "history / Riwayat". The active item
  sits in a primary-light rounded pill with a filled primary icon and primary label;
  inactive items are muted secondary text.
- Content scrolls between header and bottom nav with 20px side padding and enough bottom
  padding to clear the nav bar.

Generate the app shell plus the home screen described next.
```

---

## PROMPT 1 — Beranda (katalog alat)

```
Screen: "Beranda" — the tool catalog home. Header title "Inspeksi Alat".

Top of content:
- Greeting block: 24px ExtraBold "Halo, Teknisi! 👋", then a 14px secondary-text
  sub-line: "128 jenis alat terdaftar di katalog. Pilih alat untuk lihat panduan,
  checklist, dan mulai inspeksi."
- Search row: a full-width white rounded search input (16px radius, hairline border,
  soft shadow) with a leading "search" icon and placeholder "Cari nama alat...";
  to its right a 48x48 solid primary rounded-square button with a "tune" filter icon.
- Horizontally scrollable category filter chips, no visible scrollbar, bleeding off the
  right edge. The first chip "Semua" with a "grid_view" icon is active: solid primary,
  white bold text. The rest are white with a hairline border and secondary text, each
  with a "build" icon: "APD", "Alat Ukur", "Alat Angkat", "Perkakas Tangan",
  "Alat Listrik".

Tool list section:
- Section header row: a small vertical primary bar (6px wide, rounded) followed by 16px
  ExtraBold "Daftar Alat"; on the right a 12px bold primary link "Lihat Semua" with a
  trailing small "arrow_forward" icon.
- A 2-column grid of tool cards, 12px gap. Each card: white, 24px radius, hairline
  border, 10px padding. Inside, a 96px-tall image area with 16px radius filled with a
  soft primary-light to surface-high diagonal gradient holding a large muted
  "construction" glyph — this is the photo placeholder. Below it the tool name in 14px
  bold over up to 2 lines, with a small "chevron_right" icon aligned to the top right,
  and under it an 11px secondary-text category name on one truncated line.
- Show 6 cards with realistic Indonesian mining-tool names: "Helm Safety",
  "Sarung Tangan Kulit", "Tang Ampere", "Kunci Momen", "Sling Baja 2 Ton",
  "Gerinda Tangan 4 inci".

Also design the empty result state for this screen: centered, a large 48px muted
"search_off" icon with 14px secondary text "Tidak ada alat yang cocok." below it.

Keep the exact same design system and app shell as defined.
```

---

## PROMPT 2 — Scan QR / barcode

```
Screen: "Scan Alat" — QR/barcode scanning. Header title "Scan Alat".

- Scanner viewport: full-width 1:1 square, 32px radius, near-black #18181B background
  with a subtle vertical dark gradient overlay. Centered inside it, a reticle at 72%
  width: four bright emerald #34D399 corner brackets with thick 4px strokes and rounded
  outer corners, plus a thin horizontal emerald scan line across the middle with a soft
  glow. A very faint oversized "photo_camera" glyph sits behind the reticle at about 8%
  opacity. Top-right inside the viewport: a 44px circular translucent white glass button
  with a "flash_on" icon.
- Under the viewport, centered 14px secondary text: "Arahkan kamera ke QR / barcode pada
  label alat", and below it a smaller, dimmer 12px line: "(Fitur kamera akan diaktifkan
  setelah tahap UI/UX ini disetujui)".
- A divider row: a hairline on each side of a tiny bold uppercase wide-tracked "ATAU".
- Manual search: a taller white rounded input (16px radius, hairline border) with a
  leading "search" icon, placeholder "Cari manual nama alat...", and a solid primary
  rounded "Cari" button inset on the right edge of the field.
- Bottom section "Baru ditambahkan" as a small bold uppercase secondary-text heading,
  then a horizontally scrollable row of 5 compact tool cards, 140px wide each, on the
  surface-low background with 24px radius: an 80px image placeholder tile on top
  (surface-high with a muted "construction" glyph), then a 12px bold truncated tool name,
  then a 10px secondary-text category.

Keep the exact same design system and app shell as defined.
```

---

## PROMPT 3 — Detail Alat (panduan / checklist / riwayat)

```
Screen: "Detail Alat" — the tool guide. This is the richest screen. The header shows the
back button on the left and the truncated tool name as the title.

1. Hero: full-width 224px-tall block, 32px radius, filled with a soft diagonal gradient
   from primary-light through surface to surface-high, plus a faint dark-green angular
   mountain silhouette anchored to the bottom at about 15% opacity. A large muted
   "construction" glyph is centered as the photo placeholder.
2. Title block: a small pill badge with primary text, primary-light fill and a thin
   primary border reading "Alat Ukur"; below it the tool name in 24px ExtraBold
   "Tang Ampere Digital", and under it a 14px secondary-text sub-category
   "Clamp Meter AC/DC".
3. A 3-column row of attribute tiles: white, 16px radius, hairline border, each stacked
   vertically as a 20px primary icon over an 11px bold secondary-text label —
   "shield / Aman", "settings / Efisien", "eco / Andal".
4. One large white container card, 32px radius, hairline border, 16px padding, holding
   everything below:
   a. A header row: 16px ExtraBold tool name, a 12px secondary-text category beneath,
      then a 14px secondary-text paragraph "Mengukur arus listrik tanpa memutus
      rangkaian, serta tegangan dan tahanan.", with a "chevron_right" icon aligned to
      the top right. A hairline separator below it.
   b. Two stat pills side by side on the surface fill, fully rounded, 12px bold secondary
      text with leading icons: "inventory_2 · 12 unit terdaftar" and
      "star · 18 poin inspeksi".
   c. A segmented tab bar: 3 equal segments inside a 16px-radius surface-colored track
      with 6px inner padding. The active segment is a solid primary pill with white bold
      text and a soft shadow; inactive segments are plain muted text. Tabs:
      "menu_book / Panduan" (active), "checklist / Checklist", "history / Riwayat".
   d. PANDUAN panel content, visible by default:
      - Sub-heading "Deskripsi Singkat" prefixed by a small vertical primary bar, with a
        14px secondary-text paragraph under it.
      - Numbered guide sections. Each section header is a 28px primary circle with a
        white bold number, followed by a 14px bold heading:
        1 "Fungsi Detail" — a surface-low 16px-radius panel holding a 128px gradient
          image placeholder, then a list of rows, each a small primary "task_alt" icon
          beside a 14px line of text.
        2 "Metode Inspeksi" — stacked surface-low rows, 16px radius, each with a 36px
          white rounded-square icon tile in primary ("event_available" / "sell"), a 14px
          semibold label filling the row, and a trailing muted "chevron_right".
        3 "Fitur Keselamatan" — the same row pattern with "health_and_safety" /
          "settings" icons, a bold title plus a truncated 12px secondary description.
        4 "Aturan Penggunaan" — the same row pattern, but DO rules use a primary
          "check_circle" icon and DON'T rules use an orange-red "cancel" icon.
      - A hairline-separated "INFORMASI TAMBAHAN" footer block (tiny bold uppercase
        wide-tracked eyebrow) containing "Standar Acuan" as a wrapped set of surface-low
        bordered pills ("SNI 0225:2011", "OSHA 1910.333", "IEC 61010"), and
        "Atribut Teknis" as a 2-column grid of small surface-low 16px-radius tiles, each
        a 10px uppercase secondary label over a 14px bold value ("RENTANG UKUR / 0-600 A",
        "AKURASI / ±2%", "BERAT / 320 g", "KELAS / CAT III").
   e. Also show the two alternate tab states as separate variants:
      - CHECKLIST panel: a stacked list of surface-low 16px-radius rows, each a 28px
        primary circle with the sequence number, a 14px semibold component name
        ("Rahang penjepit"), and a 12px secondary-text criterion line
        ("Tidak retak, permukaan kontak bersih").
      - RIWAYAT panel: compact surface-low cards, each with a 12px secondary-text asset ID
        ("PNC-TA-0007") over a 14px semibold date ("12 Sep 2026"), and on the right a
        fully rounded status pill: "Pass" in primary-light/primary with a "check_circle"
        icon, "Conditional" in amber tint with "warning", "Fail" in orange-red tint
        with "cancel".
5. A floating primary action docked above the bottom nav: full-width 56px button, 16px
   radius, left-to-right primary to primary-dark gradient, white 16px bold label
   "Mulai Inspeksi" with a leading "assignment_turned_in" icon and a soft green glow
   shadow beneath it.

Keep the exact same design system and app shell as defined.
```

---

## PROMPT 4 — Form Inspeksi

```
Screen: "Form Inspeksi" — the inspection entry form. Header title "Form Inspeksi" with a
back button.

- Context card at the top: white, 32px radius, hairline border, a 48px rounded-square
  image placeholder tile on the left (surface-high with a muted "construction" glyph),
  then a 14px bold tool name over a 12px secondary-text category.
- Every step heading is a small bold UPPERCASE secondary-text label with wide tracking.

Step "1. PILIH UNIT ASET": a wrapped row of selectable chips, 16px radius, 2px border.
  Unselected: hairline border, semibold secondary text. Selected: primary border,
  primary-light fill, primary text. Labels are asset IDs: "PNC-TA-0007", "PNC-TA-0008",
  "PNC-TA-0011". Also show the alternate empty state: an amber-tinted 16px-radius panel
  with an amber "warning" icon, a bold line "Belum ada unit aset terdaftar" and a 12px
  explanatory line "Daftarkan unit fisik jenis alat ini dulu di Master Data » Unit Aset
  sebelum inspeksi bisa disimpan."

Step "2. CHECKLIST PEMERIKSAAN", with a right-aligned 12px semibold count "18 poin".
  Then a stack of white checklist cards, 16px radius, hairline border, soft shadow.
  Each card: a 28px surface-high circle with the sequence number, beside a 14px semibold
  component name and a 12px secondary-text criterion. Below that a 3-column segmented
  choice row — each option is a bordered 12px-radius tile with an 18px icon over a 10px
  bold label, stacked vertically:
    "check_circle / OK" selects to emerald border + emerald tint + emerald text,
    "cancel / Tidak OK" selects to red border + red tint + red text,
    "remove_circle / N/A" selects to neutral grey border + grey tint + grey text.
  Show 3 cards: the first with "OK" selected, the second with "Tidak OK" selected, the
  third unselected.

Step "3. HASIL KESELURUHAN": a 3-column row of large bordered choice tiles, 16px radius,
  2px border, centered 14px bold labels: "Lulus" (selects emerald), "Bersyarat" (selects
  amber), "Tidak Lulus" (selects red). Under it a 3-row textarea on the surface-low fill,
  16px radius, hairline border, placeholder "Catatan tambahan (opsional)...".

Attachment: a full-width dashed-border button, 16px radius, muted semibold text, an
  "add_a_photo" leading icon, label "Lampirkan Foto (opsional)".

Docked above the bottom nav: a full-width 56px primary-to-primary-dark gradient button,
  white bold label "Simpan Inspeksi" with a leading "save" icon and a soft green glow
  shadow.

Keep the exact same design system and app shell as defined.
```

---

## PROMPT 5 — Riwayat Inspeksi

```
Screen: "Riwayat Inspeksi" — the inspection history list. Header title "Riwayat Inspeksi".

- Page intro: 20px ExtraBold "Riwayat Inspeksi" with a 14px secondary-text line
  "Semua hasil inspeksi unit aset, dari yang terbaru."
- A vertical stack of white record cards, 16px radius, hairline border, soft shadow, 16px
  padding, 10px gap. Each card has two rows split by a hairline:
  Top row — left: a 16px bold truncated tool name ("Tang Ampere Digital") over a 12px
  secondary-text meta line combining asset ID and date separated by a middle dot
  ("PNC-TA-0007 · 12 Sep 2026"). Right: a fully rounded 11px bold status pill with a 14px
  leading icon — "Pass" in primary-light/primary with "check_circle", "Conditional" in
  amber tint with "warning", "Fail" in orange-red tint with "cancel".
  Bottom row — a 12px secondary-text "Inspektor: Budi Santoso" on the left and a 12px
  semibold "Jatuh tempo: 12 Des 2026" on the right.
  Show 5 cards mixing all three statuses.

Also design the empty state as a separate variant: centered, a 160px circle filled with a
primary-light to surface-high gradient holding a large 76px filled primary "fact_check"
glyph, with two small floating badge circles overlapping it — a white-on-primary "check"
rotated slightly clockwise at the top right and a primary-on-white "eco" rotated
counter-clockwise at the bottom left, both with soft shadows. Below: 16px ExtraBold
"Belum ada riwayat inspeksi", a 14px secondary-text paragraph constrained to about 260px
"Riwayat akan muncul di sini setelah inspeksi pertama disimpan dari halaman detail alat.",
and at the bottom a left-aligned amber-tinted 16px-radius tip panel with an amber
"lightbulb" icon and 12px text "Mulai inspeksi alat sekarang untuk menyimpan riwayat
di sini."

Keep the exact same design system and app shell as defined.
```

---

## PROMPT 6 — Sukses & filter sheet (screen tambahan, belum ada di app)

```
Two more screens in the same app, same design system and app shell.

Screen A — "Inspeksi Tersimpan" success confirmation: a centered celebratory layout.
A 160px circle with a primary-light to surface-high gradient holding a large filled
primary "task_alt" glyph. Below it 24px ExtraBold "Inspeksi Tersimpan", a 14px
secondary-text line "Hasil inspeksi PNC-TA-0007 sudah masuk ke riwayat.", then a white
summary card, 24px radius, hairline border, listing 4 label/value rows separated by
hairlines — "Unit Aset / PNC-TA-0007", "Hasil / Lulus" with the value as a primary status
pill, "Poin Tidak OK / 1", "Jatuh tempo berikutnya / 12 Des 2026". At the bottom a
full-width primary gradient button "Lihat Riwayat" and beneath it a plain text-only
secondary button "Inspeksi Alat Lain" in bold primary text.

Screen B — filter bottom sheet over the Beranda screen: the background content dimmed
behind a scrim. The sheet is white with 32px top corners and a small grey grab handle
centered at the top. Title row: 18px ExtraBold "Filter Alat" on the left and a circular
light-surface close button with a "close" icon on the right. Sections, each with a small
bold uppercase secondary-text heading: "KATEGORI" as wrapped selectable chips,
"KRITIKALITAS" as 3 chips "Tinggi / Sedang / Rendah", "STATUS INSPEKSI" as 3 chips
"Semua / Jatuh tempo / Terlambat", and a toggle row with a 14px semibold label
"Hanya alat yang diregulasi" and a primary-colored switch on the right. Footer: two
buttons side by side — a bordered "Reset" and a wider solid primary "Terapkan Filter".
```

---

## PROMPT REFINEMENT (dipakai saat hasil Stitch belum pas)

Satu instruksi per prompt, jangan digabung:

```
Make the whole layout tighter: reduce card padding to 16px, the grid gap to 12px, and keep
all body text between 12px and 14px. Do not change colors or radii.
```

```
Replace all photographic images with the gradient placeholder tile from the design system:
a primary-light to surface-high diagonal gradient behind a single muted Material Symbols
"construction" glyph.
```

```
All status colors must come only from the palette: Pass uses #0E8A4F on #E3F5EA,
Conditional uses amber #B45309 on #FFFBEB, Fail uses #B3441F on #FDECE6. Remove every
other accent color.
```

```
Increase the corner radius of every card to 24px and every button and input to 16px.
Chips and status pills stay fully rounded.
```

```
The bottom navigation must stay fixed and visible while the content scrolls, and the
primary action button must float directly above it with 20px side margins.
```

```
Keep the content column at a 480px maximum width and center it. On wide screens the area
outside the column is filled with the app background color #EFFAF4.
```

---

## Token untuk handoff balik ke Blade

Hasil Stitch di-export sebagai HTML/CSS. Supaya langsung nyambung ke kode yang sudah ada,
petakan kelasnya ke token Tailwind di
[layout.blade.php](../resources/views/pnc-monitoring/inspection-app/layout.blade.php):

| Token Tailwind | Hex | Dipakai untuk |
|---|---|---|
| `primary` | `#0E8A4F` | tombol utama, chip aktif, ikon aksen |
| `primary-dark` | `#0A6B3E` | ujung gradient tombol |
| `primary-light` | `#E3F5EA` | fill badge/pill aktif, status Pass |
| `surface` | `#EFFAF4` | background aplikasi + header |
| `surface-container-lowest` | `#FFFFFF` | kartu, input, tombol ikon |
| `surface-container-low` | `#F4FBF7` | panel di dalam kartu |
| `surface-container` | `#EAF6EF` | track tab bar, stat pill |
| `surface-container-high` | `#DFF0E6` | tile placeholder gambar |
| `surface-container-highest` | `#D2E8DA` | ujung gradient placeholder |
| `on-surface` | `#132A1D` | teks utama |
| `on-surface-variant` | `#5C6F63` | teks sekunder |
| `outline-variant` | `#D3E8DA` | border hairline, dipakai di 40-60% opacity |
| `tertiary` | `#B3441F` | status Fail, aturan "jangan" |
| `tertiary-light` | `#FDECE6` | fill status Fail |

Font: heading `Plus Jakarta Sans` (700-800), body `Inter` (400-600), ikon
`Material Symbols Outlined` wght 500.

Struktur screen ↔ file Blade yang sudah ada:

| Screen Stitch | Route | File |
|---|---|---|
| Beranda | `pnc-monitoring.inventory-inspection.home` | `inspection-app/home.blade.php` |
| Scan | `...inventory-inspection.scan` | `inspection-app/scan.blade.php` |
| Detail Alat | `...inventory-inspection.tools.show` | `inspection-app/tool-show.blade.php` |
| Form Inspeksi | `...inventory-inspection.tools.inspect` | `inspection-app/tool-inspect.blade.php` |
| Riwayat | `...inventory-inspection.history` | `inspection-app/history.blade.php` |
| Sukses / Filter sheet | belum ada | perlu dibuat baru |
