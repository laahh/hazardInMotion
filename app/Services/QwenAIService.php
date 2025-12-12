<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class QwenAIService
{
    private $apiKey;
    private $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models';
    private $mistralApiKey;
    private $mistralApiUrl = 'https://api.mistral.ai/v1/chat/completions';
    private $zaiApiKey;
    private $zaiApiUrl = 'https://api.z.ai/api/paas/v4/chat/completions';
    private $deepseekApiKey;
    private $deepseekApiUrl = 'https://api.deepseek.com/chat/completions';

    public function __construct()
    {
        // Prefer ENV over hardcoded key
        $this->apiKey = env('GEMINI_API_KEY', 'AIzaSyCObIr1JGmVkDQ2TwQm8OcrV0LuGWT4dy4');
        $this->mistralApiKey = env('MISTRAL_API_KEY', 'DiGwN0zEdDBbM7tjc3L46usJpIWYL8Ak');
        $this->zaiApiKey = env('ZAI_API_KEY', '06396132f63f40889e3bf9d321a9a721.ac0DAhmO9OqqwJoM');
        $this->deepseekApiKey = env('DEEPSEEK_API_KEY', 'sk-8ada76c8f9104ea781b9f22468ab867a');
    }

    /**
     * Generate prompt untuk validasi HSE
     */
    private function generatePrompt($description, $photoUrl)
    {
        $categories = $this->getCategoriesList();

        return "You are an expert HSE auditor in a mining company. Analyze the following safety finding STRICTLY according to the official categorization rules.

CRITICAL CLASSIFICATION RULES:
1. ONLY classify if the finding EXACTLY matches one of the specific categories listed below
2. If the finding does NOT match any specific category, set match_found=false and ALL concern_level values to false
3. Each finding can ONLY have ONE concern_level set to true - NEVER multiple
4. Be VERY STRICT - do NOT mark TBC just because it's a general safety concern. Only mark TBC if it SPECIFICALLY matches a category marked with → TBC
5. Pay attention to the arrows (→) - they indicate the exact concern level:
   - → TBC means To Be Concerned
   - → PSPP means a higher level concern
   - → GR means a serious concern
   - → Incident means an actual incident occurred
6. If unsure or the finding doesn't clearly match a category, set match_found=false

EXAMPLES:
- Finding: \"Pekerja memotong buah tidak pakai sarung tangan\" → Should match \"Tidak gunakan APD sesuai/benar/layak → TBC\" → Set TBC=true, others=false
- Finding: \"Jalan licin setelah hujan\" → Does NOT match any specific category → Set match_found=false, all concern_level=false
- Finding: \"Unit tidak menggunakan seatbelt\" → Matches \"Tidak menggunakan seatbelt → PSPP\" → Set PSPP=true, others=false

Description: \"{$description}\"
Photo URL: {$photoUrl}

{$categories}

Respond ONLY in valid JSON with these exact keys:
{
  \"match_found\": true/false,
  \"main_category\": \"string or null\",
  \"sub_category\": \"string or null\",
  \"concern_level\": {
    \"TBC\": true/false,
    \"PSPP\": true/false,
    \"GR\": true/false,
    \"Incident\": true/false
  },
  \"justification\": \"Penjelasan singkat dalam Bahasa Indonesia mengapa temuan ini masuk kategori tersebut (sebutkan kategori spesifik) atau mengapa tidak sesuai kategori\",
  \"confidence_score\": 0.0 to 1.0
}

CRITICAL: Only ONE of TBC, PSPP, GR, or Incident can be true. If match_found is false, ALL must be false.

Do not add any other text, markdown, or explanation.";
    }

    /**
     * Validasi dan normalisasi response dari AI
     * Memastikan hanya satu concern_level yang true
     */
    private function validateAndNormalizeResponse($result)
    {
        // Pastikan struktur concern_level ada
        if (!isset($result['concern_level']) || !is_array($result['concern_level'])) {
            $result['concern_level'] = [
                'TBC' => false,
                'PSPP' => false,
                'GR' => false,
                'Incident' => false
            ];
        }

        // Jika match_found false, semua concern_level harus false
        if (isset($result['match_found']) && $result['match_found'] === false) {
            $result['concern_level'] = [
                'TBC' => false,
                'PSPP' => false,
                'GR' => false,
                'Incident' => false
            ];
            return $result;
        }

        // Hitung berapa banyak concern_level yang true
        $trueCount = 0;
        $trueKeys = [];
        foreach ($result['concern_level'] as $key => $value) {
            // Normalisasi boolean
            $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            $result['concern_level'][$key] = $boolValue;
            
            if ($boolValue) {
                $trueCount++;
                $trueKeys[] = $key;
            }
        }

        // Jika lebih dari satu yang true, pilih yang pertama (prioritas: Incident > GR > PSPP > TBC)
        if ($trueCount > 1) {
            Log::warning('Multiple concern levels set to true, normalizing', [
                'true_keys' => $trueKeys,
                'result' => $result
            ]);
            
            // Reset semua ke false
            $result['concern_level'] = [
                'TBC' => false,
                'PSPP' => false,
                'GR' => false,
                'Incident' => false
            ];
            
            // Set hanya yang pertama sesuai prioritas
            $priority = ['Incident', 'GR', 'PSPP', 'TBC'];
            foreach ($priority as $level) {
                if (in_array($level, $trueKeys)) {
                    $result['concern_level'][$level] = true;
                    break;
                }
            }
        }

        // Jika tidak ada yang true tapi match_found true, set match_found ke false
        if ($trueCount === 0 && isset($result['match_found']) && $result['match_found'] === true) {
            Log::warning('match_found is true but no concern_level is true, setting match_found to false');
            $result['match_found'] = false;
        }

        return $result;
    }

    /**
     * Get categories list untuk prompt
     */
    private function getCategoriesList()
    {
        return "Daftar Kategori Resmi \"To Be Concerned\" – Juni 2025:

1. Deviasi pengoperasian kendaraan/unit
   - Fatigue (yawning, microsleep, closed eyes, dsb) → TBC
   - Melakukan aktivitas lain:
       • Makan, Minum, Memberi Barang dari/ke unit → TBC
       • Merokok → PSPP
       • Headset/Handphone → GR
   - Tidak menggunakan seatbelt → PSPP
   - Unit tidak layak operasi:
       • Tyre tidak memadai → TBC
       • Rem tidak berfungsi → PSPP
   - Tanpa SIMPER → PSPP
   - Unit tidak komunikasi 2 arah → TBC
   - Tidak lakukan P2H / P2H tidak sesuai → PSPP
   - Tidak jaga jarak beriringan → TBC
   - Overspeed:
       • ≤5 km/jam → TBC
       • 6–10 / 10–20 / 20–30 km/jam → PSPP
       • >30 km/jam → GR
   - Modifikasi alat keselamatan → PSPP
   - Tinggalkan unit menyala → PSPP
   - Melintas jalur berlawanan → TBC
   - Bawa HP ke Hauler/A2B → TBC
   - Jam tidur <6 jam saat Fit To Work → TBC
   - Seatbelt rusak (non-P2H) → TBC
   - Tidak ada crack test kendaraan → TBC
   - Tidak bunyikan klakson maju/mundur → TBC
   - Driver tidak Speak Up Self Declare → TBC
   - Penyiraman tidak putus-putus di tanjakan/turunan → TBC

2. Deviasi penggunaan APD
   - Tidak gunakan APD sesuai/benar/layak → TBC
   - APD tidak layak (meski belum digunakan) → TBC
   - Dekat air tanpa pelampung → GR
   - Kerja di ketinggian >1,8m tanpa body harness + double lanyard → GR
   - Tidak pasang welding screen saat welding → TBC

3. Geotech & Hydrology
   - Prasarana di radius longsor → TBC
   - Retakan → TBC
   - Tambang di area tidak aman geoteknik → TBC
   - Tidak ada kajian geoteknik → TBC
   - Tidak pantau kestabilan lereng → TBC
   - Potensi bahaya geoteknik/hidrologi → TBC
   - Landslide → Incident

4. Posisi Pekerja pada Area Tidak Aman / Pekerjaan Tidak Sesuai Prosedur
   - Di luar kabin di area tambang → TBC
   - Pijak tempat tidak semestinya → TBC
   - Turun unit tanpa 3 titik tumpu → TBC
   - <1,5x tebing → TBC (jika mudah longsor → PSPP)
   - Di atas crest/tanggul dengan beda tinggi → GR
   - Buang air kecil dari unit → PSPP
   - Berenang di sump → GR
   - Masuk area khusus tanpa izin → TBC (Blasting/Land Clearing → PSPP)
   - Tidak turun saat penyeberangan → TBC
   - Tidur di disposal/area terbuka → GR
   - Di radius manuver unit → GR
   - <6m (tyreman) / <10m (lainnya) saat pemompaan tyre → TBC
   - Di bawah beban/unit maintenance → GR
   - Area kerja tidak memadai → TBC
   - Welding velg tanpa lepas ban → TBC
   - Pekerja <1 tahun di area high-risk → TBC

5. Deviasi Loading/Dumping
   - Jarak dumping <20m (rawa), <10m (air), <4-5m (kering) → TBC (jika lewat crestline → PSPP)
   - Dumping menyentuh/naik tanggul → TBC
   - Undercut → GR
   - Material/dinding > tinggi kabin → TBC
   - Tidak ada tanggul Top Loading → TBC
   - Batas dumping tidak ada/tidak update → TBC

6. Tidak terdapat pengawas / pengawas tidak memadai
   - Tidak ada spotter/trafficman → TBC
   - Pengawas tidak kompeten → TBC
   - Tidak laksanakan SAP → TBC
   - Tidak isi checklist awal shift → TBC
   - Pengawas ikut bekerja → TBC

7. LOTO
   - Tidak pasang LOTO/tag → GR
   - Lepas LOTO orang lain → GR
   - Operasikan unit bertanda perawatan → GR
   - Tag LOTO pudar/rusak → TBC
   - Kunci LOTO digantung tanpa lepas → TBC
   - Tidak isi formulir LOTO → TBC

8. Deviasi Road Management
   - Akses area tidak aktif belum ditutup → TBC
   - Jalan shortcut → TBC
   - Simpang 5+ → TBC
   - Tidak ada tanggul red zone → TBC
   - Grade jalan >10% tanpa pengendalian → TBC
   - Jalan licin dipakai sebelum slippery selesai → TBC
   - Tidak ada median persimpangan → TBC
   - Blindspot → TBC
   - Tidak ada tanggul pengaman → TBC
   - Lebar jalan tidak standar tanpa pengendalian → TBC
   - Superelevasi terbalik → TBC

9. Kesesuaian Dokumen Kerja
   - Tidak sesuai DOP/tidak ada DOP → TBC
   - Pengawas tidak punya izin/IKK tidak sesuai → TBC
   - Tidak bawa ID Card / pakai punya orang → TBC
   - Tidak punya kompetensi/SIMPER → TBC (SIMPER → PSPP)
   - Kerja di ruang terbatas tanpa izin → GR
   - Tidak punya JSA/tidak disosialisasi → TBC
   - MPRP tidak tersedia/tidak sesuai → TBC

10. Tools Tidak Standard
    - Tools tidak standard → TBC (Alat angkat → PSPP)
    - Gunakan tools tidak sesuai → TBC (Alat angkat → PSPP)
    - Alat angkat pengganti tidak memadai → GR
    - Tidak ada pengaman kabin saat maintenance → TBC
    - Modifikasi tools tanpa sertifikasi → TBC

11. Bahaya Elektrikal
    - Percikan api → Incident
    - Tidak periksa instalasi listrik → TBC
    - Tidak ada barikade area tegangan tinggi → TBC
    - Perbaikan listrik tanpa matikan arus → GR
    - Instalasi tidak standar → TBC

12. Bahaya Biologis
    - Tidak identifikasi bahaya biologis → TBC
    - Tanaman merambat (sarang ular) → TBC
    - Tidak tersedia SABU → TBC
    - Pohon kering belum dipotong → TBC
    - Tidak laporkan gigitan ular → Incident

13. Aktivitas Drill and Blast
    - Akses gudang handak tidak layak → TBC
    - Jarak bor terlalu dekat MPU → TBC
    - Tanggul peledakan tidak standar → TBC
    - Permukaan basah/lunak → TBC
    - Tidak ada area parkir drill → TBC
    - Parkir tidak standar / tidak ada rambu → TBC
    - Tidak ada barikade/safety line → TBC
    - Tidak pasang rambu pengeboran → TBC
    - Aksesoris peledakan tidak aman → TBC
    - Area maneuver MPU tidak memadai → TBC
    - Lubang panas / collapse / miring → TBC
    - Lubang ledak terlalu dekat jalan → TBC
    - Aktivitas saat petir → TBC
    - Tidak ada pita radius peledakan → TBC
    - Tidak ada pondok/lampu flip-flop sleepblast → TBC
    - Tidak ada rambu peledakan tidur → TBC
    - Tidak ada rambu radius aman manusia → TBC
    - Charging dekat front loading → TBC
    - Mobilisasi drilling tanpa turunkan mast → TBC

14. Technology
    - Area kritis tidak tercover CCTV → TBC
    - P2H Control Room tidak dilakukan → TBC

15. GOLDEN RULES (GR) - Aturan Wajib yang Tidak Boleh Dilanggar:

15.1. KELAYAKAN KENDARAAN & UNIT → GR
   - Mengoperasikan kendaraan/unit dengan fungsi rem, kemudi, atau sabuk pengaman rusak → GR
   - Mengoperasikan kendaraan di area tambang tanpa buggy-whip, radio komunikasi, dan lampu strobe yang telah lulus uji kelayakan → GR
   - Merubah/menghilangkan fungsi alat keselamatan pada kendaraan dan unit → GR

15.2. MENGOPERASIKAN KENDARAAN & UNIT SECARA TIDAK LAYAK/BENAR → GR
   - Mengoperasikan kendaraan/unit tanpa SIMPER → GR
   - Tidak menggunakan sabuk pengaman dengan benar saat kendaraan/unit bergerak → GR
   - Menggunakan telepon genggam dan/atau alat bantu dengar (headset) ketika sedang mengoperasikan kendaraan atau unit → GR
   - Melebihi kecepatan lebih dari +30 km/jam dari batas kecepatan yang telah ditetapkan → GR
   - Mengoperasikan kendaraan atau unit dalam keadaan tidak bugar (lelah, mengantuk, tidak konsentrasi) → GR
   - Berhenti, parkir, atau memasuki area gerakan alat berat yang sedang bergerak tanpa izin dari operator alat berat tersebut → GR

15.3. LOCK OUT & TAG OUT (LOTO) → GR
   - Tidak memasang personal LOTO dengan benar saat melakukan perbaikan atau perawatan yang memerlukan isolasi → GR
   - Memindahkan atau melepaskan LOTO milik pekerja lain → GR
   - Mengoperasikan kendaraan/unit atau peralatan listrik yang terdapat tanda perawatan (label dan penandaan) → GR

15.4. KESELAMATAN BEKERJA DI KETINGGIAN → GR
   - Pekerja yang berada pada ketinggian lebih dari 1,8 meter dari permukaan tanah/lantai kerja yang tidak dilengkapi pagar tanpa menggunakan full body harness yang dilengkapi dua tali pengaman (double lanyard) yang dikaitkan pada titik yang kuat → GR

15.5. KESELAMATAN BEKERJA DI RUANG TERBATAS (CONFINED SPACE) → GR
   - Memasuki ruang terbatas tanpa izin → GR
   - Ruang terbatas adalah ruangan yang: Bukan ruangan kerja rutin, Hanya memiliki 1 pintu masuk/keluar, Memiliki sirkulasi udara terbatas, Berpotensi keracunan gas dari bahan/zat kimia, Berpotensi terperangkap (contoh: tangki, manhole, bunker, palka kapal, cerobong, silo, pipa saluran, ruang bawah tanah, dll.) → GR

15.6. KESELAMATAN ALAT ANGKAT & ALAT PENYANGGA → GR
   - Mengoperasikan alat angkat tanpa SIMPER atau KIMPER → GR
   - Berada di bawah beban yang sedang diangkat → GR
   - Pekerjaan pengangkatan & penyanggaan tidak menggunakan alat/peralatan yang sesuai fungsinya dan lulus uji kelayakan → GR

15.7. BEKERJA DI DEKAT TEBING → GR
   - Berada dalam jarak kurang dari 1,5x tinggi tebing dari bibir tebing (crest) pit → GR
   - Melakukan penggalian dengan sistem potong bawah (undercut) → GR
   - Berada di atas tanggul pada tepi tebing atau dinding galian yang mudah longsor (sesuai hasil assessment geoteknik) → GR
   - Berada di dalam jarak 15 m dari batas rencana penggalian material lunak, kecuali berada dalam unit A2B yang dilengkapi kabin unit alat gali yang digunakan → GR

15.8. BEKERJA PADA AREA PELEDAKAN → GR
   - Memasuki area peledakan (blasting area) tanpa izin dari Pengawas Peledakan → GR

15.9. BEKERJA DI DEKAT AIR → GR
   - Bekerja/berada di tepi/atas air yang tidak dilengkapi alat pelindung jatuh dengan kedalaman air lebih dari 1 meter tanpa menggunakan pelampung keselamatan (life jacket/work vest) → GR

15.10. BEKERJA DI AREA DISPOSAL → GR
   - Melakukan penimbunan/pembuangan material langsung ke kolam (sump) melewati bibir tebing (crest line) → GR
   - Tidur di area disposal atau area terbuka lainnya di area tambang → GR

15.11. BEKERJA PADA AREA PEMBERSIHAN LAHAN (LAND CLEARING) → GR
   - Memasuki area pembersihan lahan/land clearing tanpa izin dari pengawas land clearing → GR";
    }

    /**
     * Test API key dan koneksi
     */
    public function testConnection()
    {
        try {
            $testUrl = $this->apiUrl . '/gemini-2.0-flash:generateContent';
            $testResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-goog-api-key' => $this->apiKey,
            ])->timeout(30)->post($testUrl, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Hello'
                            ]
                        ]
                    ]
                ]
            ]);

            return [
                'success' => $testResponse->successful(),
                'status' => $testResponse->status(),
                'body' => $testResponse->body(),
                'json' => $testResponse->json(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validasi temuan HSE menggunakan AI
     */
    public function validateFinding($description, $photoUrl)
    {
        try {
            $prompt = $this->generatePrompt($description, $photoUrl);

            // Daftar model Gemini yang akan dicoba
            $models = [
                'gemini-2.0-flash',
                'gemini-1.5-pro',
                'gemini-1.5-flash',
                'gemini-pro',
            ];

            $response = null;
            $lastError = null;

            // Coba setiap model sampai ada yang berhasil
            foreach ($models as $model) {
                try {
                    $modelUrl = $this->apiUrl . '/' . $model . ':generateContent';
                    
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'X-goog-api-key' => $this->apiKey,
                    ])->timeout(60)->post($modelUrl, [
                        'contents' => [
                            [
                                'parts' => [
                                    [
                                        'text' => $prompt
                                    ]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.1,
                            'maxOutputTokens' => 2000,
                        ]
                    ]);

                    // Jika berhasil, keluar dari loop
                    if ($response->successful()) {
                        Log::info('Gemini API success with model', ['model' => $model]);
                        break;
                    }

                    $errorData = $response->json();
                    
                    // Jika error bukan "model tidak tersedia", simpan error dan lanjut
                    if (isset($errorData['error']['status']) && 
                        $errorData['error']['status'] !== 'NOT_FOUND' &&
                        $errorData['error']['status'] !== 'INVALID_ARGUMENT') {
                        $lastError = $response;
                        break; // Error lain, stop trying
                    }

                    Log::warning('Gemini model not available', [
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);

                } catch (Exception $e) {
                    Log::warning('Gemini API request exception', [
                        'model' => $model,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            if (!$response || !$response->successful()) {
                $errorBody = $response ? $response->body() : 'No response';
                $errorData = $response ? $response->json() : [];
                
                // Cek apakah error karena token habis atau quota exceeded
                $isTokenExhausted = false;
                if ($response) {
                    $status = $response->status();
                    $errorMessage = $errorData['error']['message'] ?? $errorData['message'] ?? '';
                    
                    // Deteksi error token habis/quota
                    if ($status === 429 || // Rate limit
                        $status === 403 || // Forbidden (bisa karena quota)
                        stripos($errorMessage, 'quota') !== false ||
                        stripos($errorMessage, 'exceeded') !== false ||
                        stripos($errorMessage, 'billing') !== false ||
                        stripos($errorMessage, 'insufficient') !== false) {
                        $isTokenExhausted = true;
                    }
                }
                
                // Jika token habis, coba fallback ke Mistral.ai
                if ($isTokenExhausted) {
                    Log::warning('Gemini token/quota exhausted, falling back to Mistral.ai', [
                        'status' => $response ? $response->status() : 'No response',
                        'error' => $errorData
                    ]);
                    
                    try {
                        return $this->validateFindingWithMistral($description, $photoUrl, $prompt);
                    } catch (Exception $mistralError) {
                        Log::error('Mistral.ai fallback also failed, trying z.ai', [
                            'error' => $mistralError->getMessage()
                        ]);
                        // Try z.ai as next fallback
                        try {
                            return $this->validateFindingWithZai($description, $photoUrl, $prompt);
                        } catch (Exception $zaiError) {
                            Log::error('z.ai fallback also failed, trying DeepSeek', [
                                'error' => $zaiError->getMessage()
                            ]);
                            // Try DeepSeek as next fallback
                            try {
                                return $this->validateFindingWithDeepSeek($description, $photoUrl, $prompt);
                            } catch (Exception $deepseekError) {
                                Log::error('DeepSeek fallback also failed', [
                                    'error' => $deepseekError->getMessage()
                                ]);
                                // Continue to throw original error
                            }
                        }
                    }
                }
                
                Log::error('Gemini API Error', [
                    'status' => $response ? $response->status() : 'No response',
                    'body' => $errorBody,
                    'json' => $errorData,
                    'api_key_length' => strlen($this->apiKey),
                    'api_key_prefix' => substr($this->apiKey, 0, 10) . '...'
                ]);
                
                $errorMessage = 'API request failed: ' . ($response ? $response->status() : 'No response');
                if (isset($errorData['error']['message'])) {
                    $errorMessage .= ' - ' . $errorData['error']['message'];
                } elseif (isset($errorData['message'])) {
                    $errorMessage .= ' - ' . $errorData['message'];
                }
                
                throw new Exception($errorMessage);
            }

            $responseData = $response->json();

            // Extract text dari response Gemini
            $text = '';
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
            }
            
            // Parse JSON menggunakan method yang sama dengan Mistral
            $result = $this->parseJsonResponse($text);

            // Validasi dan normalisasi response
            $result = $this->validateAndNormalizeResponse($result);

            return $result;

        } catch (Exception $e) {
            // Jika error dan belum dicoba Mistral, coba fallback
            $errorMessage = $e->getMessage();
            if (stripos($errorMessage, 'quota') !== false || 
                stripos($errorMessage, 'exceeded') !== false ||
                stripos($errorMessage, '429') !== false ||
                stripos($errorMessage, '403') !== false ||
                stripos($errorMessage, 'billing') !== false ||
                stripos($errorMessage, 'insufficient') !== false) {
                
                Log::warning('Gemini error detected, trying Mistral.ai fallback', [
                    'error' => $errorMessage
                ]);
                
                try {
                    $prompt = $this->generatePrompt($description, $photoUrl);
                    return $this->validateFindingWithMistral($description, $photoUrl, $prompt);
                } catch (Exception $mistralError) {
                    Log::error('Mistral.ai fallback also failed, trying z.ai', [
                        'error' => $mistralError->getMessage()
                    ]);
                    // Try z.ai as next fallback
                    try {
                        return $this->validateFindingWithZai($description, $photoUrl, $prompt);
                    } catch (Exception $zaiError) {
                        Log::error('z.ai fallback also failed, trying DeepSeek', [
                            'error' => $zaiError->getMessage()
                        ]);
                        // Try DeepSeek as next fallback
                        try {
                            return $this->validateFindingWithDeepSeek($description, $photoUrl, $prompt);
                        } catch (Exception $deepseekError) {
                            Log::error('DeepSeek fallback also failed', [
                                'error' => $deepseekError->getMessage()
                            ]);
                        }
                    }
                }
            }
            
            Log::error('GeminiAIService Error', [
                'message' => $errorMessage,
                'description' => $description,
                'photo_url' => $photoUrl
            ]);
            
            // Return default response on error
            return [
                'match_found' => false,
                'main_category' => null,
                'sub_category' => null,
                'concern_level' => [
                    'TBC' => false,
                    'PSPP' => false,
                    'GR' => false,
                    'Incident' => false
                ],
                'justification' => 'Error: ' . $errorMessage,
                'confidence_score' => 0.0
            ];
        }
    }

    /**
     * Validasi temuan menggunakan Mistral.ai sebagai fallback
     */
    private function validateFindingWithMistral($description, $photoUrl, $prompt)
    {
        try {
            Log::info('Using Mistral.ai for validation');
            
            // Daftar model Mistral yang akan dicoba
            $models = [
                'mistral-large-latest',
                'mistral-medium-latest',
                'mistral-small-latest',
            ];

            $response = null;
            $lastError = null;

            // Coba setiap model sampai ada yang berhasil
            foreach ($models as $model) {
                try {
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->mistralApiKey,
                    ])->timeout(60)->post($this->mistralApiUrl, [
                        'model' => $model,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'temperature' => 0.1,
                        'max_tokens' => 2000,
                    ]);

                    // Jika berhasil, keluar dari loop
                    if ($response->successful()) {
                        Log::info('Mistral.ai API success with model', ['model' => $model]);
                        break;
                    }

                    $errorData = $response->json();
                    
                    // Jika error bukan "model tidak tersedia", simpan error dan lanjut
                    if (isset($errorData['error']['type']) && 
                        $errorData['error']['type'] !== 'invalid_request_error') {
                        $lastError = $response;
                        break; // Error lain, stop trying
                    }

                    Log::warning('Mistral model not available', [
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);

                } catch (Exception $e) {
                    Log::warning('Mistral API request exception', [
                        'model' => $model,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            if (!$response || !$response->successful()) {
                $errorBody = $response ? $response->body() : 'No response';
                $errorData = $response ? $response->json() : [];
                
                Log::error('Mistral.ai API Error', [
                    'status' => $response ? $response->status() : 'No response',
                    'body' => $errorBody,
                    'json' => $errorData
                ]);
                
                $errorMessage = 'Mistral API request failed: ' . ($response ? $response->status() : 'No response');
                if (isset($errorData['error']['message'])) {
                    $errorMessage .= ' - ' . $errorData['error']['message'];
                } elseif (isset($errorData['message'])) {
                    $errorMessage .= ' - ' . $errorData['message'];
                }
                
                throw new Exception($errorMessage);
            }

            $responseData = $response->json();

            // Extract text dari response Mistral
            $text = '';
            if (isset($responseData['choices'][0]['message']['content'])) {
                $text = $responseData['choices'][0]['message']['content'];
            } else {
                throw new Exception('Invalid Mistral response structure');
            }

            // Parse JSON dari text response menggunakan method yang sama
            $result = $this->parseJsonResponse($text);

            // Validasi dan normalisasi response
            $result = $this->validateAndNormalizeResponse($result);

            Log::info('Mistral.ai validation completed', [
                'match_found' => $result['match_found'] ?? false,
                'confidence' => $result['confidence_score'] ?? 0
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Mistral.ai Service Error', [
                'message' => $e->getMessage(),
                'description' => $description,
                'photo_url' => $photoUrl
            ]);
            
            throw $e; // Re-throw untuk ditangani di level atas
        }
    }

    /**
     * Validasi temuan menggunakan z.ai sebagai fallback
     */
    private function validateFindingWithZai($description, $photoUrl, $prompt)
    {
        try {
            Log::info('Using z.ai for validation');
            
            // Daftar model z.ai yang akan dicoba
            $models = [
                'glm-4.6',
                'glm-4.5',
                'glm-4-32B-0414-128K',
            ];

            $response = null;
            $lastError = null;

            // Coba setiap model sampai ada yang berhasil
            foreach ($models as $model) {
                try {
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept-Language' => 'en-US,en',
                        'Authorization' => 'Bearer ' . $this->zaiApiKey,
                    ])->timeout(60)->post($this->zaiApiUrl, [
                        'model' => $model,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are a helpful AI assistant that responds in valid JSON format only.'
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'temperature' => 0.1,
                        'max_tokens' => 2000,
                    ]);

                    // Jika berhasil, keluar dari loop
                    if ($response->successful()) {
                        Log::info('z.ai API success with model', ['model' => $model]);
                        break;
                    }

                    $errorData = $response->json();
                    
                    // Jika error bukan "model tidak tersedia", simpan error dan lanjut
                    if (isset($errorData['error']['type']) && 
                        $errorData['error']['type'] !== 'invalid_request_error') {
                        $lastError = $response;
                        break; // Error lain, stop trying
                    }

                    Log::warning('z.ai model not available', [
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);

                } catch (Exception $e) {
                    Log::warning('z.ai API request exception', [
                        'model' => $model,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            if (!$response || !$response->successful()) {
                $errorBody = $response ? $response->body() : 'No response';
                $errorData = $response ? $response->json() : [];
                
                Log::error('z.ai API Error', [
                    'status' => $response ? $response->status() : 'No response',
                    'body' => $errorBody,
                    'json' => $errorData
                ]);
                
                $errorMessage = 'z.ai API request failed: ' . ($response ? $response->status() : 'No response');
                if (isset($errorData['error']['message'])) {
                    $errorMessage .= ' - ' . $errorData['error']['message'];
                } elseif (isset($errorData['message'])) {
                    $errorMessage .= ' - ' . $errorData['message'];
                }
                
                throw new Exception($errorMessage);
            }

            $responseData = $response->json();

            // Extract text dari response z.ai (format mirip OpenAI)
            $text = '';
            if (isset($responseData['choices'][0]['message']['content'])) {
                $text = $responseData['choices'][0]['message']['content'];
            } else {
                throw new Exception('Invalid z.ai response structure');
            }

            // Parse JSON dari text response menggunakan method yang sama
            $result = $this->parseJsonResponse($text);

            // Validasi dan normalisasi response
            $result = $this->validateAndNormalizeResponse($result);

            Log::info('z.ai validation completed', [
                'match_found' => $result['match_found'] ?? false,
                'confidence' => $result['confidence_score'] ?? 0
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('z.ai Service Error', [
                'message' => $e->getMessage(),
                'description' => $description,
                'photo_url' => $photoUrl
            ]);
            
            throw $e; // Re-throw untuk ditangani di level atas
        }
    }

    /**
     * Validasi temuan menggunakan DeepSeek sebagai fallback
     */
    private function validateFindingWithDeepSeek($description, $photoUrl, $prompt)
    {
        try {
            Log::info('Using DeepSeek for validation');
            
            // Daftar model DeepSeek yang akan dicoba
            $models = [
                'deepseek-chat',
                'deepseek-reasoner',
            ];

            $response = null;
            $lastError = null;

            // Coba setiap model sampai ada yang berhasil
            foreach ($models as $model) {
                try {
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->deepseekApiKey,
                    ])->timeout(60)->post($this->deepseekApiUrl, [
                        'model' => $model,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are a helpful AI assistant that responds in valid JSON format only.'
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'temperature' => 0.1,
                        'max_tokens' => 2000,
                        'stream' => false,
                    ]);

                    // Jika berhasil, keluar dari loop
                    if ($response->successful()) {
                        Log::info('DeepSeek API success with model', ['model' => $model]);
                        break;
                    }

                    $errorData = $response->json();
                    
                    // Jika error bukan "model tidak tersedia", simpan error dan lanjut
                    if (isset($errorData['error']['type']) && 
                        $errorData['error']['type'] !== 'invalid_request_error') {
                        $lastError = $response;
                        break; // Error lain, stop trying
                    }

                    Log::warning('DeepSeek model not available', [
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);

                } catch (Exception $e) {
                    Log::warning('DeepSeek API request exception', [
                        'model' => $model,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            if (!$response || !$response->successful()) {
                $errorBody = $response ? $response->body() : 'No response';
                $errorData = $response ? $response->json() : [];
                
                Log::error('DeepSeek API Error', [
                    'status' => $response ? $response->status() : 'No response',
                    'body' => $errorBody,
                    'json' => $errorData
                ]);
                
                $errorMessage = 'DeepSeek API request failed: ' . ($response ? $response->status() : 'No response');
                if (isset($errorData['error']['message'])) {
                    $errorMessage .= ' - ' . $errorData['error']['message'];
                } elseif (isset($errorData['message'])) {
                    $errorMessage .= ' - ' . $errorData['message'];
                }
                
                throw new Exception($errorMessage);
            }

            $responseData = $response->json();

            // Extract text dari response DeepSeek (format mirip OpenAI)
            $text = '';
            if (isset($responseData['choices'][0]['message']['content'])) {
                $text = $responseData['choices'][0]['message']['content'];
            } else {
                throw new Exception('Invalid DeepSeek response structure');
            }

            // Parse JSON dari text response menggunakan method yang sama
            $result = $this->parseJsonResponse($text);

            // Validasi dan normalisasi response
            $result = $this->validateAndNormalizeResponse($result);

            Log::info('DeepSeek validation completed', [
                'match_found' => $result['match_found'] ?? false,
                'confidence' => $result['confidence_score'] ?? 0
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('DeepSeek Service Error', [
                'message' => $e->getMessage(),
                'description' => $description,
                'photo_url' => $photoUrl
            ]);
            
            throw $e; // Re-throw untuk ditangani di level atas
        }
    }

    /**
     * Parse JSON response dari text (digunakan oleh Gemini, Mistral, z.ai, dan DeepSeek)
     */
    private function parseJsonResponse($text)
    {
        // Clean up text untuk extract JSON
        $text = trim($text);
        
        // Remove markdown code blocks jika ada
        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);
        
        // Parse JSON
        $result = json_decode($text, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Jika JSON tidak valid, coba extract JSON dari text dengan regex yang lebih robust
            // Cari JSON object yang paling lengkap
            if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches)) {
                $result = json_decode($matches[0], true);
            } else {
                // Coba cari JSON di antara backticks atau code blocks
                if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $matches)) {
                    $result = json_decode($matches[1], true);
                }
            }
        }
        
        if (json_last_error() !== JSON_ERROR_NONE || !$result) {
            Log::error('Failed to parse JSON from AI response', [
                'text' => substr($text, 0, 500), // Log first 500 chars
                'json_error' => json_last_error_msg(),
            ]);
            throw new Exception('Failed to parse JSON response: ' . json_last_error_msg());
        }
        
        return $result;
    }

    /**
     * Chat umum dengan AI (untuk chatbot)
     */
    public function chat($message, $conversationHistory = [])
    {
        try {
            // Daftar model Gemini yang akan dicoba
            $models = [
                'gemini-2.0-flash',
                'gemini-1.5-pro',
                'gemini-1.5-flash',
                'gemini-pro',
            ];

            $response = null;

            // Build conversation history
            $contents = [];
            foreach ($conversationHistory as $msg) {
                $contents[] = [
                    'parts' => [
                        [
                            'text' => $msg['content']
                        ]
                    ],
                    'role' => $msg['role'] === 'user' ? 'user' : 'model'
                ];
            }

            // Add current message
            $contents[] = [
                'parts' => [
                    [
                        'text' => $message
                    ]
                ],
                'role' => 'user'
            ];

            // Coba setiap model sampai ada yang berhasil
            foreach ($models as $model) {
                try {
                    $modelUrl = $this->apiUrl . '/' . $model . ':generateContent';
                    
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'X-goog-api-key' => $this->apiKey,
                    ])->timeout(60)->post($modelUrl, [
                        'contents' => $contents,
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 2048,
                        ]
                    ]);

                    // Jika berhasil, keluar dari loop
                    if ($response->successful()) {
                        Log::info('Gemini API chat success with model', ['model' => $model]);
                        break;
                    }

                    $errorData = $response->json();
                    
                    // Jika error bukan "model tidak tersedia", simpan error dan lanjut
                    if (isset($errorData['error']['status']) && 
                        $errorData['error']['status'] !== 'NOT_FOUND' &&
                        $errorData['error']['status'] !== 'INVALID_ARGUMENT') {
                        break; // Error lain, stop trying
                    }

                } catch (Exception $e) {
                    Log::warning('Gemini API chat request exception', [
                        'model' => $model,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            // Jika Gemini gagal, coba fallback ke Mistral
            if (!$response || !$response->successful()) {
                return $this->chatWithMistral($message, $conversationHistory);
            }

            $responseData = $response->json();

            // Extract text dari response Gemini
            $text = '';
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
            } else {
                throw new Exception('Invalid Gemini response structure');
            }

            return [
                'success' => true,
                'message' => $text,
                'model' => 'gemini'
            ];

        } catch (Exception $e) {
            Log::error('QwenAIService Chat Error', [
                'message' => $e->getMessage(),
                'user_message' => substr($message, 0, 100)
            ]);
            
            // Try fallback
            try {
                return $this->chatWithMistral($message, $conversationHistory);
            } catch (Exception $fallbackError) {
                return [
                    'success' => false,
                    'message' => 'Maaf, terjadi kesalahan saat memproses pesan Anda. Silakan coba lagi.',
                    'error' => $e->getMessage()
                ];
            }
        }
    }

    /**
     * Chat dengan Mistral.ai sebagai fallback
     */
    private function chatWithMistral($message, $conversationHistory = [])
    {
        try {
            Log::info('Using Mistral.ai for chat');
            
            $models = [
                'mistral-large-latest',
                'mistral-medium-latest',
                'mistral-small-latest',
            ];

            $response = null;

            // Build messages array
            $messages = [];
            foreach ($conversationHistory as $msg) {
                $messages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content']
                ];
            }
            $messages[] = [
                'role' => 'user',
                'content' => $message
            ];

            // Coba setiap model
            foreach ($models as $model) {
                try {
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->mistralApiKey,
                    ])->timeout(60)->post($this->mistralApiUrl, [
                        'model' => $model,
                        'messages' => $messages,
                        'temperature' => 0.7,
                        'max_tokens' => 2048,
                    ]);

                    if ($response->successful()) {
                        Log::info('Mistral.ai API chat success with model', ['model' => $model]);
                        break;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            if (!$response || !$response->successful()) {
                throw new Exception('Mistral API request failed');
            }

            $responseData = $response->json();
            $text = $responseData['choices'][0]['message']['content'] ?? '';

            return [
                'success' => true,
                'message' => $text,
                'model' => 'mistral'
            ];

        } catch (Exception $e) {
            Log::error('Mistral.ai Chat Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * AI-based rule selection menggunakan Gemini
     * Menerjemahkan prompt user menjadi query berdasarkan rule base
     */
    public function selectRuleAndGenerateQuery($userPrompt, $availableRules, $tableSchema)
    {
        try {
            // Format rules untuk prompt
            $rulesDescription = $this->formatRulesForPrompt($availableRules);
            
            $prompt = "Anda adalah asisten AI yang ahli dalam memahami maksud user dan memilih rule yang tepat untuk generate SQL query.

SCHEMA TABEL:
{$tableSchema}

RULES YANG TERSEDIA:
{$rulesDescription}

PROMPT USER: {$userPrompt}

Tugas Anda:
1. Analisis prompt user dengan teliti
2. Pilih rule yang PALING COCOK dari daftar rules di atas
3. Jika tidak ada rule yang cocok, return null untuk rule_id
4. Extract parameter yang diperlukan dari prompt user
5. Generate SQL query berdasarkan rule yang dipilih
6. Berikan rekomendasi awal berdasarkan konteks query

Kembalikan HANYA JSON dengan format berikut (tanpa markdown, tanpa penjelasan):
{
  \"rule_id\": \"string atau null\",
  \"confidence\": 0.0-1.0,
  \"extracted_params\": {
    \"key\": \"value\"
  },
  \"sql_query\": \"string SQL query atau null\",
  \"initial_recommendation\": \"string rekomendasi singkat berdasarkan konteks\",
  \"reasoning\": \"string penjelasan singkat mengapa rule ini dipilih\"
}

CONTOH:
- User: 'tampilkan data area non kritis ada berapa'
- Output: {
    \"rule_id\": \"stat_total_area_non_kritis\",
    \"confidence\": 0.95,
    \"extracted_params\": {},
    \"sql_query\": \"SELECT COUNT(*) as total FROM cctv_data_bmo2 WHERE kategori_area_tercapture = 'Area Non Kritis'\",
    \"initial_recommendation\": \"Area non kritis perlu monitoring rutin untuk memastikan tetap aman\",
    \"reasoning\": \"User ingin mengetahui jumlah area non kritis, cocok dengan rule stat_total_area_non_kritis\"
  }

- User: 'cctv di site BMO 1'
- Output: {
    \"rule_id\": \"query_by_site\",
    \"confidence\": 0.9,
    \"extracted_params\": {\"site\": \"BMO 1\"},
    \"sql_query\": \"SELECT * FROM cctv_data_bmo2 WHERE site = 'BMO 1'\",
    \"initial_recommendation\": \"Pastikan semua CCTV di site BMO 1 berfungsi dengan baik\",
    \"reasoning\": \"User ingin melihat CCTV berdasarkan site, cocok dengan rule query_by_site\"
  }

Hanya return JSON, tanpa markdown, tanpa penjelasan apapun.";

            // Gunakan Gemini langsung (tanpa fallback untuk rule selection)
            $models = [
                'gemini-2.0-flash',
                'gemini-1.5-pro',
                'gemini-1.5-flash',
            ];

            $response = null;

            foreach ($models as $model) {
                try {
                    $modelUrl = $this->apiUrl . '/' . $model . ':generateContent';
                    
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'X-goog-api-key' => $this->apiKey,
                    ])->timeout(60)->post($modelUrl, [
                        'contents' => [
                            [
                                'parts' => [
                                    [
                                        'text' => $prompt
                                    ]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.3, // Lower temperature untuk konsistensi
                            'maxOutputTokens' => 2048,
                        ]
                    ]);

                    if ($response->successful()) {
                        Log::info('Gemini rule selection success', ['model' => $model]);
                        break;
                    }
                } catch (Exception $e) {
                    Log::warning('Gemini rule selection error', [
                        'model' => $model,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            if (!$response || !$response->successful()) {
                throw new Exception('Gemini API failed for rule selection');
            }

            $responseData = $response->json();
            $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Parse JSON response
            $text = trim($text);
            $text = preg_replace('/```json\s*/', '', $text);
            $text = preg_replace('/```\s*/', '', $text);
            $text = trim($text);

            $result = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to parse JSON: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'data' => $result,
                'model' => 'gemini'
            ];

        } catch (Exception $e) {
            Log::error('Gemini Rule Selection Error', [
                'error' => $e->getMessage(),
                'prompt' => substr($userPrompt, 0, 100)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Format rules untuk prompt AI
     */
    private function formatRulesForPrompt($rules)
    {
        $formatted = "ID | Nama | Keywords | SQL Template | Description\n";
        $formatted .= str_repeat("-", 80) . "\n";

        foreach ($rules as $rule) {
            $keywords = implode(', ', array_slice($rule['keywords'] ?? [], 0, 5));
            $sqlTemplate = $rule['sql_template'] ?? 'N/A';
            if (strlen($sqlTemplate) > 50) {
                $sqlTemplate = substr($sqlTemplate, 0, 50) . '...';
            }

            $formatted .= sprintf(
                "%s | %s | %s | %s | %s\n",
                $rule['id'] ?? 'N/A',
                $rule['name'] ?? 'N/A',
                $keywords,
                $sqlTemplate,
                $rule['description'] ?? 'N/A'
            );
        }

        return $formatted;
    }

    /**
     * Generate rekomendasi kontekstual menggunakan Gemini (langsung, tanpa fallback)
     */
    public function generateContextualRecommendation($queryResult, $queryContext, $userPrompt)
    {
        try {
            $dataSummary = $this->prepareDataSummaryForRecommendation($queryResult);
            
            $prompt = "Anda adalah konsultan HSE (Health, Safety, Environment) yang ahli di bidang monitoring CCTV dan manajemen area kritis di pertambangan.

KONTEKS QUERY:
{$queryContext}

HASIL QUERY:
{$dataSummary}

PERTANYAAN USER: {$userPrompt}

Tugas Anda:
Berdasarkan hasil query dan konteks, berikan rekomendasi yang:
1. **Spesifik dan Actionable** - Rekomendasi yang bisa langsung ditindaklanjuti
2. **Relevan dengan Konteks** - Sesuai dengan jenis data yang ditampilkan
3. **Prioritas Tindakan** - Urutkan dari yang paling penting
4. **Fokus HSE** - Relevan dengan keselamatan, kesehatan, dan lingkungan kerja

Format rekomendasi:
- Gunakan struktur yang jelas dengan heading
- Gunakan bullet points untuk setiap rekomendasi
- Berikan prioritas (High/Medium/Low) jika relevan
- Maksimal 250 kata
- Bahasa Indonesia

CONTOH:
Jika hasil menunjukkan area kritis sebanyak 660:
**Rekomendasi Tindakan:**

• **Monitoring Rutin (High Priority)**: Lakukan pengecekan harian pada semua area kritis untuk memastikan tidak ada perubahan kondisi yang membahayakan

• **Penambahan CCTV (High Priority)**: Pertimbangkan penambahan CCTV di area kritis yang belum tercover dengan baik untuk meningkatkan monitoring

• **Review Prosedur (Medium Priority)**: Lakukan review prosedur keselamatan untuk area kritis secara berkala dan update jika diperlukan

• **Training Personel (Medium Priority)**: Pastikan semua personel yang bekerja di area kritis telah mendapat training yang memadai dan sertifikasi yang valid";

            // Gunakan Gemini langsung (tanpa fallback)
            $models = [
                'gemini-2.0-flash',
                'gemini-1.5-pro',
                'gemini-1.5-flash',
            ];

            $response = null;

            foreach ($models as $model) {
                try {
                    $modelUrl = $this->apiUrl . '/' . $model . ':generateContent';
                    
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'X-goog-api-key' => $this->apiKey,
                    ])->timeout(60)->post($modelUrl, [
                        'contents' => [
                            [
                                'parts' => [
                                    [
                                        'text' => $prompt
                                    ]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 2048,
                        ]
                    ]);

                    if ($response->successful()) {
                        Log::info('Gemini contextual recommendation success', ['model' => $model]);
                        break;
                    }
                } catch (Exception $e) {
                    Log::warning('Gemini contextual recommendation error', [
                        'model' => $model,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            if (!$response || !$response->successful()) {
                Log::error('Gemini contextual recommendation failed');
                return null;
            }

            $responseData = $response->json();
            $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return $text ?: null;

        } catch (Exception $e) {
            Log::error('Contextual Recommendation Error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Prepare data summary untuk rekomendasi
     */
    private function prepareDataSummaryForRecommendation($results)
    {
        if (empty($results)) {
            return "Tidak ada data ditemukan.";
        }

        $summary = "Jumlah baris: " . count($results) . "\n";
        
        if (count($results) === 1) {
            $rowArray = (array) $results[0];
            $summary .= "Data: " . json_encode($rowArray, JSON_PRETTY_PRINT);
        } else {
            $summary .= "Data (5 pertama):\n";
            foreach (array_slice($results, 0, 5) as $index => $row) {
                $rowArray = (array) $row;
                $summary .= ($index + 1) . ". " . json_encode($rowArray) . "\n";
            }
        }

        return $summary;
    }
}

