# Prompting Stitch — Konsep B: "Volt Industrial"

Alternatif desain untuk modul `/pnc-monitoring/inventory-inspection`. Fitur dan alurnya
sama persis dengan [konsep A](stitch-prompt-inventory-inspection.md), tapi bahasa visualnya
berbeda total — diturunkan dari dua referensi e-commerce (Trendora & Velora).

## Apa yang diambil dari referensi

| Pola di referensi | Jadi apa di app inspeksi |
|---|---|
| Header avatar + nama brand + ikon keranjang ber-badge | Avatar teknisi + "INSPEKSI ALAT" + ikon **antrian inspeksi** ber-badge |
| Greeting "Hello Tavorian" + tagline | "Halo, Budi" + status shift/lokasi kerja |
| Search + tombol Filter gelap | sama |
| Banner promo hijau "Shop Now" | Banner **alert jatuh tempo** + CTA gelap "Lihat Daftar" |
| Chip kategori berfoto + "See all" | Kategori alat berfoto + "Lihat semua" |
| Grid produk "New Arrival" + ikon hati | Grid alat + ikon **bookmark** (alat favorit teknisi) |
| Hero produk di panel warna + rail thumbnail vertikal | Hero alat + rail foto komponen |
| Baris toko + badge verified + pill "Following" | Baris **penanggung jawab alat** + badge terverifikasi + pill "Lihat Unit" |
| Swatch warna | **Pilihan unit aset** (PNC-TA-0007, dst.) |
| Chip ukuran S/M/L/XL | **Chip hasil checklist** OK / Tidak OK / N.A |
| Stepper qty | Jumlah unit yang diinspeksi dalam satu sesi |
| Bottom bar "Total price $120" + tombol pill | Bottom bar "Jatuh tempo 12 Des" + tombol "Mulai Inspeksi" |
| Bottom sheet "My Cart" + subtotal + Checkout | Bottom sheet **"Antrian Inspeksi"** + ringkasan + "Kirim Semua" |
| Nav bawah berbentuk pill melayang | sama |

**Tips**: Stitch menerima gambar referensi. Upload dua gambar referensi Anda bersama
PROMPT 0 — hasilnya jauh lebih dekat ke mood yang Anda mau.

Cara pakai sama seperti dokumen konsep A: PROMPT 0 dulu, lalu 1 → 7 berurutan di project
yang sama, tiap prompt ditutup `Keep the exact same design system and app shell as defined.`

---

## PROMPT 0 — Design system & app shell

```
Design a mobile-first web app called "Inspeksi Alat" — a field tool inspection app for
mining safety technicians. The visual language should feel like a premium modern shopping
app (clean neutral canvas, floating white cards, one electric accent color), but the
content is industrial: tools, checklists, inspection records. All UI text is in Indonesian.

DESIGN SYSTEM (apply to every screen, never deviate):
- Canvas: mobile web, 440px max content width, centered on desktop with the canvas color
  filling the viewport.
- Palette ("Volt Industrial"):
  canvas / app background #ECEDE7 (warm light grey-sage),
  card and sheet white #FFFFFF,
  subtle fill #F4F5F0, stronger fill #E2E4DC,
  ink / primary button #14171A (near-black), ink-soft #3A403D,
  text secondary #7A827C, hairline border #DCDED6,
  accent volt lime #C8F04E used as large fills and active states,
  accent tint #EEF9CF,
  warning amber #F2A63B with tint #FDF0DC,
  danger clay #D6552F with tint #FBE6DE.
- CONTRAST RULE: the volt lime is a light color. Text and icons placed on volt lime are
  always near-black #14171A, never white. White text is only used on the near-black ink.
- Typography: headings "Space Grotesk" Bold/SemiBold with tight tracking; body "DM Sans"
  400-500. Numbers and counts use "Space Grotesk" so they read technical.
  Section eyebrows: 11px, medium weight, UPPERCASE, wide letter-spacing, secondary text.
- Icons: Google Material Symbols Rounded, weight 400-500, 20-24px, active ones filled.
- Shape language: mixed on purpose. Outer cards and sheets 28px radius; inner tiles,
  inputs and image wells 16px radius; all buttons, chips and badges are FULL PILLS.
  Shadows are very soft and low — cards read as floating on the canvas, not embossed.
- Spacing: generous. 20px side padding, 16px between cards, 24px between sections.
- Motion: press scales to 0.96, 180ms ease-out.

APP SHELL (identical on every screen):
- Top header on the canvas color, no border:
  Left — on main screens, a 40px circular avatar photo of a technician; on detail screens,
  a 40px circular WHITE button with an "arrow_back" icon.
  Center — on main screens a small bold uppercase wordmark "INSPEKSI ALAT" with wide
  tracking; on detail screens the centered screen title in 16px SemiBold.
  Right — a 40px circular white button with a "inventory_2" icon and a near-black
  circular badge in the top-right corner showing a white count "3". This is the
  inspection queue, not a shopping cart.
- FLOATING BOTTOM NAVIGATION: a white pill-shaped island with a soft shadow, inset 20px
  from the screen edges and floating about 16px above the bottom, NOT a full-width bar.
  4 items: "home", "qr_code_scanner", "bookmark", "person". The active item expands into
  a volt-lime pill containing a filled near-black icon plus a near-black 13px SemiBold
  label ("Beranda"); the other three are icon-only in secondary grey.
- Screens that have a primary action use a docked bottom action bar sitting just above the
  floating nav: a white pill-shaped bar with soft shadow containing a two-line text block
  on the left (11px uppercase secondary label over a 15px Bold value) and a near-black
  pill button on the right.

Generate the app shell plus the home screen described next.
```

---

## PROMPT 1 — Beranda

```
Screen: "Beranda" — the tool catalog home.

- Greeting block: 12px secondary text "Selamat pagi," then 28px Bold "Halo, Budi" on the
  next line, and under it a small pill on the subtle fill with a 14px "location_on" icon
  and 12px text "Site Lati · Shift 1".
- Search row: a full-width white pill input with a leading "search" icon and placeholder
  "Cari alat, ID unit, atau kategori", and inset at its right edge a near-black pill
  button with a "tune" icon and white 13px SemiBold label "Filter".
- ALERT BANNER: a full-width card, 28px radius, filled volt lime #C8F04E, 20px padding.
  Inside: a small near-black pill badge with 11px uppercase white text "PERLU TINDAKAN",
  then a 20px Bold near-black headline over two lines "8 alat jatuh tempo minggu ini",
  then a near-black pill button with white 13px SemiBold "Lihat Daftar" and a trailing
  "arrow_outward" icon. On the right side of the banner, a large faded near-black
  "engineering" glyph bleeding off the card edge at low opacity as decoration.
  Below the banner, three small dots as a carousel indicator, the active one a wide
  near-black lozenge.
- "Kategori" section: a row heading in 17px Bold with a 12px secondary "Lihat semua" link
  on the right. Under it a horizontally scrollable row of category chips, each a white
  pill about 130px wide containing a 32px rounded-square image thumbnail on the left and a
  13px SemiBold label on two tight lines: "Alat Ukur", "Alat Angkat", "Perkakas Tangan",
  "Alat Listrik", "APD".
- "Alat Terdaftar" section with the same heading pattern and a "Lihat semua" link.
  A 2-column grid, 16px gap, of tool cards: white, 28px radius, 10px padding. Each card
  has a 16:14 image well with 16px radius filled with the subtle fill color, showing a
  large muted line-art tool illustration placeholder, and a 32px circular white
  "bookmark" button floating in the image well's top-right corner (filled near-black when
  saved). Under the image: a 11px secondary uppercase category, a 15px Bold tool name over
  up to two lines, and a bottom row splitting a 12px secondary "12 unit" on the left from
  a small status pill on the right — volt-lime tint with near-black text "Aman", amber
  tint "Jatuh tempo", or clay tint "Terlambat".
  Show 6 cards: "Tang Ampere Digital", "Kunci Momen 1/2 inci", "Sling Baja 2 Ton",
  "Helm Safety", "Gerinda Tangan 4 inci", "Detektor Gas Portabel".

Also design the empty result state: a centered 96px circle on the subtle fill holding a
muted "search_off" icon, a 16px Bold "Alat tidak ditemukan", and a 13px secondary line
"Coba kata kunci lain atau ubah filter kategori."

Keep the exact same design system and app shell as defined.
```

---

## PROMPT 2 — Scan Alat

```
Screen: "Scan Alat", header title "Scan Alat" with the back button.

- Scanner stage: a full-width 4:5 card with 28px radius, filled near-black #14171A. Inside
  it, centered, a scan reticle at 70% width made of four volt-lime #C8F04E corner brackets
  with 4px strokes and rounded outer corners, plus a horizontal volt-lime scan line with a
  soft glow across the middle. A very faint oversized "qr_code_2" glyph sits behind the
  reticle at about 6% white opacity.
  Floating controls inside the stage: top-right a 44px circular translucent white glass
  button with "flash_on"; bottom-center a row of two translucent glass pills with white
  13px labels — "QR Code" (active, filled volt lime with near-black text) and "Barcode".
- Under the stage, a centered 14px secondary line "Arahkan kamera ke label QR pada alat"
  and a smaller dimmer 12px line "Kamera diaktifkan setelah desain disetujui."
- A divider row: hairline, tiny uppercase wide-tracked "ATAU", hairline.
- Manual entry: a white pill input with a leading "keyboard" icon and placeholder
  "Ketik ID unit, contoh PNC-TA-0007", with a near-black circular 44px submit button
  holding an "arrow_forward" icon inset at the right edge.
- "Terakhir dipindai" section heading, then a vertical list of 3 white rows, 28px radius,
  each: a 48px rounded-square image well on the left, then a 15px SemiBold tool name over
  a 12px secondary "PNC-TA-0007 · 2 jam lalu", and a trailing muted "chevron_right".

Keep the exact same design system and app shell as defined.
```

---

## PROMPT 3 — Detail Alat

```
Screen: "Detail Alat" — the richest screen. Header: circular white back button on the
left, centered title "Detail Alat", and on the right a 40px circular white "bookmark"
button.

1. HERO: a full-width 4:3 block with 28px radius, filled with a soft volt-lime tint
   #EEF9CF, holding a large centered tool illustration placeholder. Along the hero's right
   inner edge, a vertical rail of 4 thumbnail tiles, 56px wide with 16px radius and thin
   white borders, one of them highlighted with a 2px near-black border; the last thumbnail
   is a near-black tile with white text "+6". Under the hero, three small carousel dots,
   the active one a wide near-black lozenge.
2. Title block: an 11px uppercase secondary line "ALAT UKUR", then a 26px Bold tool name
   "Tang Ampere Digital", and a row combining a volt-lime-tint pill with near-black text
   "Kondisi: Aman" and a 12px secondary "Terakhir diperiksa 12 Sep 2026".
3. OWNER ROW: a white card, 28px radius, 14px padding, holding a 40px rounded-square logo
   tile, then a 14px SemiBold "PT Kalimantan Prima" with a small volt-lime "verified"
   badge beside it and a 12px secondary "Penanggung jawab alat" beneath, and on the right
   a near-black pill button with white 13px "Lihat Unit".
4. UNIT PICKER: an 11px uppercase eyebrow "PILIH UNIT ASET" with a 12px secondary "12
   tersedia" on the right, then a wrapped row of pill chips showing asset IDs
   "PNC-TA-0007", "PNC-TA-0008", "PNC-TA-0011", "PNC-TA-0014". Unselected chips are white
   with a hairline border; the selected chip is filled near-black with white text.
   To the right of the row, a quantity stepper: a white pill holding a circular "−"
   button, a 15px Bold "01", and a circular volt-lime "+" button with a near-black icon.
5. A segmented tab bar: a full-width track on the subtle fill with 6px inner padding and a
   full pill shape. Three segments "Panduan", "Checklist", "Riwayat"; the active segment is
   a white pill with near-black SemiBold text and a soft shadow, the others are secondary
   grey text without a fill.
6. PANDUAN panel, visible by default, inside a white card with 28px radius and 20px
   padding:
   - "Deskripsi" eyebrow with a 14px paragraph "Mengukur arus listrik tanpa memutus
     rangkaian, serta tegangan dan tahanan."
   - "Spesifikasi" as a 2-column grid of tiles on the subtle fill with 16px radius, each an
     11px uppercase secondary label over a 15px Bold value: "RENTANG UKUR / 0-600 A",
     "AKURASI / ±2%", "BERAT / 320 g", "KELAS / CAT III".
   - "Metode Inspeksi" as a numbered list: each row has a 28px volt-lime circle holding a
     near-black Bold number, a 14px SemiBold title and a 12px secondary description.
   - "Aturan Penggunaan" as two stacked mini-cards: a volt-lime-tint card headed
     "check_circle · Lakukan" with 3 short bullet lines, and a clay-tint card headed
     "cancel · Jangan" with 3 short bullet lines.
   - "Standar Acuan" as a wrapped set of white bordered pills: "SNI 0225:2011",
     "OSHA 1910.333", "IEC 61010".
7. Show the two alternate tab panels as variants:
   - CHECKLIST: a numbered list of rows on the subtle fill with 16px radius, each a 28px
     white circle with the sequence number, a 14px SemiBold component name
     ("Rahang penjepit"), and a 12px secondary criterion ("Tidak retak, kontak bersih").
   - RIWAYAT: compact white rows, each a 12px secondary asset ID over a 14px SemiBold date,
     with a status pill on the right: "Lulus" in volt-lime tint with near-black text,
     "Bersyarat" in amber tint, "Tidak Lulus" in clay tint.
8. DOCKED ACTION BAR above the floating nav: a white pill bar with, on the left, an 11px
   uppercase secondary "JATUH TEMPO" over a 15px Bold "12 Des 2026", and on the right a
   near-black pill button with white 15px SemiBold "Mulai Inspeksi" and a leading
   "assignment_turned_in" icon.

Keep the exact same design system and app shell as defined.
```

---

## PROMPT 4 — Form Inspeksi

```
Screen: "Form Inspeksi", header title "Form Inspeksi" with the back button.

- PROGRESS HEADER: a white card, 28px radius, holding a 15px SemiBold "Tang Ampere
  Digital" over a 12px secondary "PNC-TA-0007", and on the right a 48px circular progress
  ring in volt lime on the subtle fill with a near-black 13px Bold "7/18" in its center.
  Under it a thin full-width track on the subtle fill with a volt-lime filled portion.
- Step eyebrows are 11px uppercase wide-tracked secondary labels.

"LANGKAH 1 · UNIT ASET": a wrapped row of asset-ID pills, unselected white with a hairline
  border, selected filled near-black with white text. Also design the empty variant: an
  amber-tint card, 28px radius, with an amber "warning" icon, a 14px SemiBold "Belum ada
  unit aset terdaftar" and a 12px line "Daftarkan unit fisik alat ini di Master Data »
  Unit Aset sebelum inspeksi bisa disimpan."

"LANGKAH 2 · CHECKLIST" with a right-aligned 12px secondary "18 poin". Then a stack of
  white checklist cards, 28px radius, 16px padding, 12px gap. Each card: a top row with a
  28px subtle-fill circle holding the sequence number, a 15px SemiBold component name, and
  a 12px secondary criterion line under it. Below, a 3-segment pill selector inside a
  subtle-fill track: "OK", "Tidak OK", "N/A", each with a small leading icon. The selected
  segment becomes a solid pill — "OK" turns volt lime with near-black text, "Tidak OK"
  turns clay with white text, "N/A" turns mid-grey with white text.
  When "Tidak OK" is selected, the card expands to reveal a clay-tint inner panel with a
  small text field placeholder "Jelaskan temuannya..." and a dashed-border pill button
  "add_a_photo · Foto temuan".
  Show 3 cards: the first with OK selected, the second with Tidak OK selected and expanded,
  the third untouched.

"LANGKAH 3 · KESIMPULAN": three large stacked option rows, each a full-width pill on white
  with a hairline border, a leading icon circle and a 15px SemiBold label — "Lulus",
  "Bersyarat", "Tidak Lulus". The selected row fills with its status color (volt lime /
  amber tint / clay tint) and gets a 2px near-black border. Under it a white textarea card
  with 28px radius and placeholder "Catatan inspektor (opsional)...", and a row of two
  dashed-border pill buttons "add_a_photo · Foto" and "draw · Tanda tangan".

DOCKED ACTION BAR: left side an 11px uppercase "TEMUAN" over a 15px Bold "1 tidak OK";
  right side a near-black pill button with white "Simpan Inspeksi" and a leading "check".

Keep the exact same design system and app shell as defined.
```

---

## PROMPT 5 — Riwayat Inspeksi

```
Screen: "Riwayat Inspeksi", header title "Riwayat".

- A 3-tile stat row: white cards, 28px radius, each a 22px Bold number over an 11px
  uppercase secondary label — "142 / TOTAL", "128 / LULUS", "14 / TEMUAN". The middle
  tile is filled volt lime with near-black text.
- A horizontally scrollable filter chip row: "Semua" active as a near-black pill with white
  text, then white bordered pills "Lulus", "Bersyarat", "Tidak Lulus", "30 hari terakhir".
- A date group heading in 11px uppercase secondary "HARI INI", then record cards: white,
  28px radius, 16px padding, 12px gap. Each card has a top row with a 44px rounded-square
  image well on the left, then a 15px SemiBold tool name over a 12px secondary
  "PNC-TA-0007 · 09:41", and on the right a status pill (volt-lime tint "Lulus", amber tint
  "Bersyarat", clay tint "Tidak Lulus"). A hairline splits off a bottom row holding a
  22px circular inspector avatar with a 12px secondary "Budi Santoso" and, on the right, a
  12px SemiBold "Jatuh tempo 12 Des" with a small "event" icon.
  Show 2 cards under "HARI INI" and 3 more under a second group heading "KEMARIN".

Also design the empty state: a centered 160px circle on the volt-lime tint holding a large
filled near-black "fact_check" glyph, with two small floating badge circles overlapping
it — a volt-lime "check" at the top right and a white "history" at the bottom left, both
with soft shadows. Below: 18px Bold "Belum ada riwayat", a 13px secondary paragraph
constrained to 260px "Riwayat muncul di sini setelah inspeksi pertama disimpan.", and a
near-black pill button "Mulai Inspeksi Pertama".

Keep the exact same design system and app shell as defined.
```

---

## PROMPT 6 — Antrian Inspeksi (bottom sheet) & Sukses

```
Two screens in the same app, same design system and app shell.

Screen A — "Antrian Inspeksi" bottom sheet over the Beranda screen. The background content
is dimmed behind a scrim. The sheet is white with 32px top corners and a small grey grab
handle centered at the top. Title row: 20px Bold "Antrian Inspeksi" on the left, and on the
right a white pill button with a hairline border, a small "send" icon and a 13px SemiBold
"Kirim Semua".
Then a list of 3 queued line items, each a subtle-fill card with 20px radius: a 44px
rounded-square image well, a 14px SemiBold tool name over a 12px secondary
"PNC-TA-0007 · Belum diisi", a small near-black pill on the right showing "7/18", and a
tiny circular "close" button at the item's top-right corner.
Below the list, a summary block with three label/value rows separated by hairlines —
"Total alat / 3", "Estimasi waktu / 25 menit", "Poin checklist / 46" — and a final
emphasised row in 17px Bold "Siap dikirim / 1 dari 3".
Footer: a full-width near-black pill button with white 16px SemiBold "Kirim 1 Inspeksi"
and, under it, a plain text-only button "Lanjut isi yang lain" in near-black SemiBold.

Screen B — "Inspeksi Terkirim" success screen. Centered: a 180px circle on the volt-lime
tint holding a large filled near-black "task_alt" glyph with a thin volt-lime ring around
it. Below, 28px Bold "Inspeksi Terkirim", then a 14px secondary line "Hasil inspeksi
PNC-TA-0007 sudah masuk ke riwayat." Then a white summary card, 28px radius, listing four
label/value rows separated by hairlines: "Unit Aset / PNC-TA-0007", "Hasil / Lulus" with
the value shown as a volt-lime-tint pill, "Poin tidak OK / 1", "Jatuh tempo berikutnya /
12 Des 2026". Footer: a full-width near-black pill button "Lihat Riwayat" and beneath it a
white pill button with a hairline border "Inspeksi Alat Lain".

Keep the exact same design system and app shell as defined.
```

---

## PROMPT 7 — Refinement (satu instruksi per prompt)

```
The volt lime #C8F04E must only ever carry near-black #14171A text and icons. Replace any
white text sitting on a lime fill with near-black.
```

```
Make the bottom navigation a floating white pill island inset 20px from the screen edges
with a soft shadow, not a full-width bar attached to the bottom edge. Only the active item
shows its text label, inside a lime pill.
```

```
Use volt lime sparingly: at most one large lime area per screen. Everything else stays
white, canvas grey, or near-black. Reduce any extra lime fills to the lime tint #EEF9CF.
```

```
Replace every photographic product image with a neutral image well on the subtle fill
#F4F5F0 holding a single muted line-art tool illustration. No photos of people or
lifestyle imagery anywhere.
```

```
All buttons, chips, badges and pills must be fully rounded. Cards stay at 28px radius and
inner tiles at 16px. Remove any sharp or 8px corners.
```

```
Keep the content column at 440px maximum width, centered, with the canvas color #ECEDE7
filling the area outside it on wide screens.
```

---

## Varian palet (ganti satu blok di PROMPT 0)

Kalau volt lime terasa terlalu ramai, tukar blok `Palette` di PROMPT 0 dengan salah satu
ini — sisa prompt tidak perlu diubah.

**Varian 1 — "Amber Workshop"** (paling dekat ke referensi Trendora):

```
- Palette ("Amber Workshop"):
  canvas #EAEBE2, card white #FFFFFF, subtle fill #F5F2EC, stronger fill #E6E0D4,
  ink #1B1712, ink-soft #40382F, text secondary #857B6E, hairline #DFD8CB,
  accent amber #F4A94B with tint #FDEBD3,
  success moss #4E7A46 with tint #E4EFE0,
  danger clay #C24A2C with tint #F9E2DA.
  Text on the amber accent is always near-black #1B1712.
```

**Varian 2 — "Slate Signal"** (paling formal, cocok untuk audiens korporat):

```
- Palette ("Slate Signal"):
  canvas #EDEFF2, card white #FFFFFF, subtle fill #F5F7F9, stronger fill #E1E6EB,
  ink #101418, ink-soft #38414A, text secondary #6B7682, hairline #D8DEE5,
  accent signal blue #2563EB with tint #E3EBFD,
  success #17795E with tint #E0F2EC,
  warning #B45309 with tint #FDF3E3,
  danger #C0392B with tint #FAE4E1.
  Text on the signal blue accent is white.
```

---

## Perbandingan cepat dengan Konsep A

| Aspek | Konsep A (existing) | Konsep B (Volt Industrial) |
|---|---|---|
| Kanvas | hijau mint `#EFFAF4` | abu hangat `#ECEDE7` |
| Aksen | hijau solid `#0E8A4F` | volt lime `#C8F04E` + near-black |
| Tombol utama | gradient hijau, radius 16px | pill near-black |
| Bottom nav | bar putih full-width | pill island melayang, 4 item |
| Font | Plus Jakarta Sans + Inter | Space Grotesk + DM Sans |
| Hero detail | gradient + siluet gunung | panel lime-tint + rail thumbnail + dots |
| Aksi utama | tombol floating full-width | action bar "meta kiri + pill kanan" |
| Konsep baru | — | antrian inspeksi, progress ring, grup tanggal di riwayat |

Catatan implementasi: Konsep B butuh dua hal yang belum ada di backend —
**antrian inspeksi** (batch beberapa alat dalam satu sesi) dan **bookmark alat** per
teknisi. Dua-duanya perlu tabel baru kalau nanti jadi dipakai; di tahap UI/UX ini cukup
tampil sebagai desain.
