<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\QwenAIService;
use App\Services\ChatbotRuleService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    protected $qwenAIService;
    protected $ruleService;

    public function __construct(QwenAIService $qwenAIService, ChatbotRuleService $ruleService)
    {
        $this->qwenAIService = $qwenAIService;
        $this->ruleService = $ruleService;
    }

    /**
     * Display the chatbot page
     */
    public function index()
    {
        return view('chatbot.index');
    }

    /**
     * Handle chat message
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_history' => 'nullable|array'
        ]);

        try {
            $message = $request->input('message');
            $conversationHistory = $request->input('conversation_history', []);

            // Step 0: Cek apakah ini pertanyaan umum (bukan query database)
            if ($this->isGeneralQuestion($message)) {
                Log::info('General question detected', ['message' => $message]);
                return $this->handleNormalChat($message, $conversationHistory);
            }

            // Step 1: Gunakan AI (Gemini) untuk memilih rule dan generate query
            $aiRuleSelection = $this->selectRuleWithAI($message);
            
            // Step 2: Jika AI berhasil memilih rule, gunakan hasilnya
            // Jika tidak, fallback ke rule detection biasa
            if ($aiRuleSelection && $aiRuleSelection['success'] && $aiRuleSelection['data']['rule_id']) {
                return $this->handleAIRuleSelection($aiRuleSelection, $message, $conversationHistory);
            }
            
            // Fallback: AI-based intent understanding untuk memahami maksud user
            $intentAnalysis = $this->understandUserIntent($message);
            
            // Deteksi rule dengan intent yang sudah dipahami
            $matchedRule = $this->ruleService->detectRuleWithIntent($message, $intentAnalysis);

            if ($matchedRule) {
                Log::info('Rule matched', [
                    'rule_id' => $matchedRule['id'],
                    'rule_name' => $matchedRule['name'],
                    'message' => $message
                ]);

                // Generate SQL dari rule dengan intent analysis
                $sql = $this->ruleService->generateSQL($matchedRule, $message, $intentAnalysis);

                if ($sql) {
                    // Eksekusi SQL langsung
                    try {
                        $results = DB::select($sql);
                        $formattedResponse = $this->formatQueryResults($results, $matchedRule);
                        
                        // Generate chart data jika statistik (selalu generate untuk statistik)
                        $chartData = null;
                        if (isset($matchedRule['is_statistic']) && $matchedRule['is_statistic']) {
                            $chartData = $this->generateChartData($results, $matchedRule);
                            // Jika tidak ada chart dari generateChartData, buat chart untuk single stat
                            if (!$chartData && count($results) === 1) {
                                $rowArray = (array) $results[0];
                                if (count($rowArray) <= 2) {
                                    $chartData = $this->generateSingleStatChart($rowArray);
                                }
                            }
                        }
                        
                        // Generate AI explanation (khusus untuk insiden dengan analisis pembelajaran)
                        $explanation = null;
                        if (isset($matchedRule['is_incident']) && $matchedRule['is_incident']) {
                            $messageLower = strtolower($message);
                            if (stripos($messageLower, 'pelajari') !== false || 
                                stripos($messageLower, 'belajar') !== false ||
                                stripos($messageLower, 'apa yang bisa') !== false) {
                                $explanation = $this->generateIncidentLearningAnalysis($results, $message);
                            } else {
                                $explanation = $this->generateAIExplanation($results, $matchedRule, $message);
                            }
                        } else {
                            $explanation = $this->generateAIExplanation($results, $matchedRule, $message);
                        }
                        
                        return response()->json([
                            'success' => true,
                            'message' => $formattedResponse,
                            'model' => 'rule-based',
                            'rule_id' => $matchedRule['id'],
                            'sql' => $sql,
                            'chart' => $chartData,
                            'explanation' => $explanation,
                            'has_chart' => $chartData !== null
                        ]);
                    } catch (\Exception $e) {
                        Log::error('SQL execution error', [
                            'sql' => $sql,
                            'error' => $e->getMessage()
                        ]);
                        
                        // Fallback ke AI untuk generate SQL
                        return $this->generateSQLWithAI($message, $conversationHistory);
                    }
                } else {
                    // Complex query atau tidak bisa extract value - gunakan AI
                    return $this->generateSQLWithAI($message, $conversationHistory);
                }
            } else {
                // Tidak ada rule yang cocok - cek apakah ini query request
                if ($this->isQueryRequest($message) || ($intentAnalysis && $intentAnalysis['is_query'])) {
                    // Gunakan AI untuk generate SQL dengan intent yang sudah dipahami
                    return $this->generateSQLWithAI($message, $conversationHistory, $intentAnalysis);
                } else {
                    // Chat biasa
                    return $this->handleNormalChat($message, $conversationHistory);
                }
            }

        } catch (\Exception $e) {
            Log::error('ChatController Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Maaf, terjadi kesalahan saat memproses pesan Anda. Silakan coba lagi.'
            ], 500);
        }
    }

    /**
     * Understand user intent menggunakan AI
     */
    private function understandUserIntent($message)
    {
        try {
            $tableSchema = $this->getTableSchema();
            
            $prompt = "Anda adalah sistem Natural Language Understanding (NLU) yang ahli. Analisis maksud user dari kalimat berikut dan ekstrak informasi penting.

KALIMAT USER: {$message}

SCHEMA TABEL:
{$tableSchema}

Tugas Anda:
1. Tentukan apakah ini query request (true/false)
2. Identifikasi intent/keinginan user
3. Ekstrak parameter penting (kategori area, site, status, dll)
4. Normalisasi nilai (misal: 'non kritis' -> 'Area Non Kritis', 'kritis' -> 'Area Kritis')

Kembalikan HANYA JSON dengan format berikut (tanpa markdown, tanpa penjelasan):
{
  \"is_query\": true/false,
  \"intent\": \"string (contoh: 'count_area', 'list_cctv', 'statistics', 'distribution')\",
  \"entity_type\": \"string (contoh: 'area', 'cctv', 'site', 'status')\",
  \"entity_value\": \"string atau null (contoh: 'Area Non Kritis', 'BMO 1', 'Live View')\",
  \"operation\": \"string (contoh: 'count', 'list', 'group_by', 'filter')\",
  \"normalized_message\": \"string (kalimat yang sudah dinormalisasi)\"
}

CONTOH:
- Input: 'tampilkan data area non kritis ada berapa'
- Output: {\"is_query\": true, \"intent\": \"count_area\", \"entity_type\": \"area\", \"entity_value\": \"Area Non Kritis\", \"operation\": \"count\", \"normalized_message\": \"jumlah area non kritis\"}

- Input: 'cctv di site BMO 1'
- Output: {\"is_query\": true, \"intent\": \"list_cctv\", \"entity_type\": \"site\", \"entity_value\": \"BMO 1\", \"operation\": \"filter\", \"normalized_message\": \"cctv di site BMO 1\"}

- Input: 'distribusi area kritis'
- Output: {\"is_query\": true, \"intent\": \"distribution\", \"entity_type\": \"area\", \"entity_value\": \"Area Kritis\", \"operation\": \"group_by\", \"normalized_message\": \"distribusi area kritis\"}

Hanya return JSON, tanpa penjelasan apapun.";

            $aiResponse = $this->qwenAIService->chat($prompt, []);
            
            if ($aiResponse['success']) {
                $responseText = trim($aiResponse['message']);
                
                // Clean JSON dari markdown jika ada
                $responseText = preg_replace('/```json\s*/', '', $responseText);
                $responseText = preg_replace('/```\s*/', '', $responseText);
                $responseText = trim($responseText);
                
                $intent = json_decode($responseText, true);
                
                if (json_last_error() === JSON_ERROR_NONE && $intent) {
                    Log::info('Intent understood', [
                        'original' => $message,
                        'intent' => $intent
                    ]);
                    return $intent;
                }
            }
        } catch (\Exception $e) {
            Log::error('Intent understanding error', ['error' => $e->getMessage()]);
        }
        
        return null;
    }

    /**
     * Generate SQL menggunakan AI
     */
    private function generateSQLWithAI($message, $conversationHistory, $intentAnalysis = null)
    {
        $tableSchema = $this->getTableSchema();
        
        // Gunakan normalized message jika ada intent analysis
        $userQuery = $intentAnalysis && isset($intentAnalysis['normalized_message']) 
            ? $intentAnalysis['normalized_message'] 
            : $message;
        
        $intentInfo = $intentAnalysis ? "\n\nINTENT YANG DIPAHAMI:\n" . json_encode($intentAnalysis, JSON_PRETTY_PRINT) : "";
        
        $prompt = "Anda adalah asisten SQL yang ahli. User ingin melakukan query pada database. Tentukan tabel yang tepat berdasarkan konteks pertanyaan.

SCHEMA TABEL:
{$tableSchema}

PERINTAH USER: {$message}
{$intentInfo}

Tugas Anda:
1. Analisis perintah user untuk menentukan tabel yang tepat:
   - Jika tentang CCTV, gunakan tabel: cctv_data_bmo2
   - Jika tentang insiden/kejadian/kecelakaan, gunakan tabel: insiden_tabel
2. Generate SQL query yang sesuai
3. Hanya return SQL query saja, tanpa penjelasan apapun
4. Pastikan query aman (hindari SQL injection)
5. Gunakan format yang benar untuk string (pakai single quote)
6. Untuk insiden, urutkan berdasarkan tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id dan limit 100 (untuk memastikan semua layer dari satu insiden terambil)
7. PENTING: Untuk menghitung jumlah insiden, SELALU gunakan COUNT(DISTINCT no_kecelakaan) karena no_kecelakaan itu unik. Jangan gunakan COUNT(*) untuk insiden.

CONTOH CCTV:
- User: 'cctv di site BMO 1'
- SQL: SELECT * FROM cctv_data_bmo2 WHERE site = 'BMO 1'

- User: 'berapa total cctv'
- SQL: SELECT COUNT(*) as total FROM cctv_data_bmo2

- User: 'cctv yang statusnya live view'
- SQL: SELECT * FROM cctv_data_bmo2 WHERE status = 'Live View'

CONTOH INSIDEN:
- User: 'insiden apa saja'
- SQL: SELECT * FROM insiden_tabel ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100

- User: 'insiden di site BMO 2'
- SQL: SELECT * FROM insiden_tabel WHERE site = 'BMO 2' ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100

- User: 'berapa total insiden'
- SQL: SELECT COUNT(DISTINCT no_kecelakaan) as total FROM insiden_tabel

- User: 'ceritakan insiden'
- SQL: SELECT * FROM insiden_tabel ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100

Hanya return SQL query saja, tanpa markdown, tanpa penjelasan.";

        $aiResponse = $this->qwenAIService->chat($prompt, $conversationHistory);
        
        if ($aiResponse['success']) {
            $sql = trim($aiResponse['message']);
            
            // Clean SQL dari markdown jika ada
            $sql = preg_replace('/```sql\s*/', '', $sql);
            $sql = preg_replace('/```\s*/', '', $sql);
            $sql = trim($sql);
            
            // Eksekusi SQL
            try {
                $results = DB::select($sql);
                $formattedResponse = $this->formatQueryResults($results);
                
                // Generate chart data jika hasilnya statistik
                $chartData = $this->detectAndGenerateChartData($results, $sql);
                
                // Generate AI explanation (khusus untuk insiden dengan analisis pembelajaran)
                $explanation = null;
                $messageLower = strtolower($message);
                if (stripos($messageLower, 'insiden') !== false || 
                    stripos($messageLower, 'kejadian') !== false ||
                    stripos($messageLower, 'kecelakaan') !== false) {
                    if (stripos($messageLower, 'pelajari') !== false || 
                        stripos($messageLower, 'belajar') !== false ||
                        stripos($messageLower, 'apa yang bisa') !== false) {
                        $explanation = $this->generateIncidentLearningAnalysis($results, $message);
                    } else {
                        $explanation = $this->generateAIExplanation($results, null, $message);
                    }
                } else {
                    $explanation = $this->generateAIExplanation($results, null, $message);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $formattedResponse,
                    'model' => 'ai-generated',
                    'sql' => $sql,
                    'chart' => $chartData,
                    'explanation' => $explanation,
                    'has_chart' => $chartData !== null
                ]);
            } catch (\Exception $e) {
                Log::error('AI SQL execution error', [
                    'sql' => $sql,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat mengeksekusi query. ' . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Maaf, tidak dapat memproses query Anda.'
            ], 500);
        }
    }

    /**
     * Handle normal chat (bukan query)
     */
    private function handleNormalChat($message, $conversationHistory)
    {
        // Limit conversation history
        $conversationHistory = array_slice($conversationHistory, -10);
        
        // Tambahkan konteks sistem di awal jika belum ada
        $systemContext = "Anda adalah Asisten AI yang membantu pengguna dengan pertanyaan umum. Anda adalah bagian dari sistem manajemen CCTV untuk monitoring area kritis di pertambangan. ";
        $systemContext .= "Jawablah pertanyaan dengan ramah, jelas, dan informatif dalam Bahasa Indonesia. ";
        $systemContext .= "Jika pertanyaan terkait CCTV atau data, arahkan pengguna untuk menggunakan format query yang jelas (contoh: 'berapa total CCTV', 'cctv di site BMO 1').";
        
        // Buat message dengan konteks sistem untuk pertama kali
        $enhancedMessage = $message;
        if (empty($conversationHistory)) {
            $enhancedMessage = $systemContext . "\n\nPertanyaan pengguna: " . $message;
        }
        
        $response = $this->qwenAIService->chat($enhancedMessage, $conversationHistory);

        if ($response['success']) {
            return response()->json([
                'success' => true,
                'message' => $response['message'],
                'model' => $response['model'] ?? 'ai-chat'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $response['message'] ?? 'Terjadi kesalahan saat memproses pesan.'
            ], 500);
        }
    }

    /**
     * Check if message is a general question (not database query)
     */
    private function isGeneralQuestion($message)
    {
        $messageLower = trim(strtolower($message));
        
        // Jika jelas mengandung keyword query database, bukan pertanyaan umum
        if ($this->isQueryRequest($message)) {
            // Kecuali jika ini adalah pertanyaan konsep tentang query itu sendiri
            $conceptPatterns = [
                '/^(apa\s+itu\s+cctv|what\s+is\s+cctv|jelaskan\s+tentang\s+cctv)/i',
                '/^(bagaimana\s+cara\s+menggunakan|how\s+to\s+use)/i',
            ];
            
            foreach ($conceptPatterns as $pattern) {
                if (preg_match($pattern, $messageLower)) {
                    // Ini pertanyaan konsep, bukan query data
                    return true;
                }
            }
            
            // Jika mengandung keyword query, bukan pertanyaan umum
            return false;
        }
        
        // Pertanyaan umum yang tidak memerlukan query database
        $generalQuestionPatterns = [
            // Sapaan dan percakapan umum
            '/^(halo|hai|hi|hello|selamat\s+(pagi|siang|sore|malam))/i',
            '/^(terima\s+kasih|thanks|makasih|thank\s+you)/i',
            '/^(apa\s+kabar|how\s+are\s+you|bagaimana\s+kabar)/i',
            
            // Pertanyaan tentang chatbot/AI
            '/^(siapa\s+kamu|who\s+are\s+you|apa\s+fungsimu|what\s+can\s+you\s+do)/i',
            '/^(bagaimana\s+cara\s+menggunakan\s+chatbot|how\s+to\s+use\s+chatbot)/i',
            
            // Pertanyaan umum tanpa konteks CCTV/data
            '/^(apa\s+itu\s+(cuaca|weather|hujan|panas))/i',
            '/^(jam\s+berapa|what\s+time|kapan\s+sekarang)/i',
        ];
        
        // Cek pattern pertanyaan umum
        foreach ($generalQuestionPatterns as $pattern) {
            if (preg_match($pattern, $messageLower)) {
                return true;
            }
        }
        
        // Gunakan AI untuk deteksi yang lebih cerdas (hanya jika tidak jelas query)
        return $this->detectGeneralQuestionWithAI($message);
    }

    /**
     * Detect general question using AI
     */
    private function detectGeneralQuestionWithAI($message)
    {
        try {
            $prompt = "Analisis kalimat berikut dan tentukan apakah ini pertanyaan umum (general question) atau query database tentang CCTV.

KALIMAT: {$message}

Pertanyaan umum adalah:
- Sapaan, percakapan biasa, pertanyaan tentang AI/chatbot
- Pertanyaan yang tidak memerlukan akses database CCTV
- Pertanyaan tentang konsep, penjelasan, atau informasi umum
- Bukan pertanyaan yang meminta data, statistik, atau informasi spesifik dari database CCTV

Query database adalah:
- Pertanyaan yang meminta data CCTV (contoh: 'berapa total CCTV', 'cctv di site BMO 1')
- Pertanyaan yang meminta statistik (contoh: 'distribusi CCTV', 'jumlah area kritis')
- Pertanyaan yang meminta informasi spesifik dari database

Kembalikan HANYA JSON dengan format berikut (tanpa markdown, tanpa penjelasan):
{
  \"is_general_question\": true/false,
  \"confidence\": 0.0-1.0,
  \"reason\": \"string singkat alasan\"
}

CONTOH:
- Input: 'Halo, siapa kamu?'
- Output: {\"is_general_question\": true, \"confidence\": 0.95, \"reason\": \"Pertanyaan tentang identitas chatbot\"}

- Input: 'Berapa total CCTV?'
- Output: {\"is_general_question\": false, \"confidence\": 0.95, \"reason\": \"Meminta data statistik dari database\"}

- Input: 'Apa itu CCTV?'
- Output: {\"is_general_question\": true, \"confidence\": 0.85, \"reason\": \"Pertanyaan konsep umum, bukan query database\"}

- Input: 'CCTV di site BMO 1'
- Output: {\"is_general_question\": false, \"confidence\": 0.9, \"reason\": \"Meminta data spesifik dari database\"}

Hanya return JSON, tanpa penjelasan apapun.";

            $aiResponse = $this->qwenAIService->chat($prompt, []);
            
            if ($aiResponse['success']) {
                $responseText = trim($aiResponse['message']);
                
                // Clean JSON dari markdown jika ada
                $responseText = preg_replace('/```json\s*/', '', $responseText);
                $responseText = preg_replace('/```\s*/', '', $responseText);
                $responseText = trim($responseText);
                
                $result = json_decode($responseText, true);
                
                if (json_last_error() === JSON_ERROR_NONE && $result) {
                    // Jika confidence tinggi dan memang pertanyaan umum, return true
                    if (isset($result['is_general_question']) && $result['is_general_question'] === true) {
                        $confidence = $result['confidence'] ?? 0.5;
                        if ($confidence >= 0.7) {
                            Log::info('AI detected general question', [
                                'message' => $message,
                                'confidence' => $confidence,
                                'reason' => $result['reason'] ?? ''
                            ]);
                            return true;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('General question detection error', ['error' => $e->getMessage()]);
        }
        
        return false;
    }

    /**
     * Check if results are incident data
     */
    private function isIncidentData($results)
    {
        if (empty($results)) {
            return false;
        }
        
        $rowArray = (array) $results[0];
        $incidentColumns = ['no_kecelakaan', 'kronologis', 'kategori', 'keterangan_layer', 'layer'];
        
        // Jika hasil query mengandung kolom khas insiden, maka ini data insiden
        foreach ($incidentColumns as $col) {
            if (isset($rowArray[$col])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if message is a query request
     */
    private function isQueryRequest($message)
    {
        $queryKeywords = [
            'cctv', 'insiden', 'kejadian', 'kecelakaan', 'accident', 'query', 'cari', 'tampilkan', 'lihat', 'data',
            'berapa', 'jumlah', 'total', 'statistik', 'distribusi', 'ceritakan', 'pelajari'
        ];
        
        $messageLower = strtolower($message);
        foreach ($queryKeywords as $keyword) {
            if (stripos($messageLower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get table schema for AI prompt
     */
    private function getTableSchema()
    {
        return "Tabel: cctv_data_bmo2
Kolom:
- id (integer, primary key)
- site (string) - Contoh: 'BMO 1', 'BMO 2'
- perusahaan (string) - Contoh: 'PT Mutiara Tanjung Lestari'
- kategori (string)
- no_cctv (string) - Contoh: 'BMO1-MTL-0034'
- nama_cctv (string) - Contoh: 'TH - MELTING UNIT'
- fungsi_cctv (string) - Contoh: 'Fixed View'
- bentuk_instalasi_cctv (string) - Contoh: 'Statis'
- jenis (string) - Contoh: 'FIXED'
- tipe_cctv (string) - Contoh: 'HIKVISION'
- radius_pengawasan (string, nullable)
- jenis_spesifikasi_zoom (string) - Contoh: 'DS-2CD1023G0E-I'
- lokasi_pemasangan (string) - Contoh: 'WORKSHOP RIM'
- control_room (string) - Contoh: 'Control Room'
- status (string) - Contoh: 'Live View', 'Offline'
- kondisi (string) - Contoh: 'Baik', 'Breakdown', 'Repair', 'Dismantle'
- longitude (decimal)
- latitude (decimal)
- coverage_lokasi (string) - Contoh: 'Plant RM Suaran'
- coverage_detail_lokasi (string) - Contoh: 'Batching Plant MTL'
- kategori_area_tercapture (string) - Contoh: 'Area Non Kritis'
- kategori_aktivitas_tercapture (string) - Contoh: 'Aktivitas Non Kritis'
- link_akses (text)
- user_name (string)
- password (string)
- connected (string) - Contoh: 'YES', 'NO'
- mirrored (string) - Contoh: 'YES', 'NO'
- fitur_auto_alert (string, nullable)
- keterangan (text, nullable)
- verifikasi_by_petugas_ocr (string, nullable)
- bulan_update (integer)
- tahun_update (integer)
- qr_code (string)
- created_at (timestamp)
- updated_at (timestamp)

Tabel: insiden_tabel
Kolom:
- id (integer, primary key)
- no_kecelakaan (string) - Contoh: 'BMO2/2025/2'
- kode_be_investigasi (string, nullable)
- status_lpi (string) - Contoh: 'Open'
- target_penyelesaian_lpi (date, nullable)
- actual_penyelesaian_lpi (date, nullable)
- ketepatan_waktu_lpi (string, nullable)
- tanggal (date, nullable)
- bulan (integer) - Contoh: 1-12
- tahun (integer) - Contoh: 2025
- minggu_ke (integer, nullable)
- hari (string) - Contoh: 'Sabtu', 'Kamis'
- jam (integer) - Contoh: 0-23
- menit (integer) - Contoh: 0-59
- shift (string) - Contoh: 'Shift 1', 'Shift 2'
- perusahaan (string) - Contoh: 'PT Pamapersada Nusantara'
- latitude (decimal, nullable)
- longitude (decimal, nullable)
- departemen (string) - Contoh: 'Mining Operation'
- site (string) - Contoh: 'BMO 2'
- lokasi (string) - Contoh: '(B8) Pit J'
- sublokasi (string, nullable)
- lokasi_spesifik (string, nullable)
- lokasi_validasi_hsecm (string, nullable)
- pja (string, nullable)
- insiden_dalam_site_mining (string) - Contoh: 'Ya'
- kategori (string) - Contoh: 'Nearmiss', 'Accident'
- injury_status (string, nullable)
- kronologis (text) - Kronologi kejadian
- high_potential (string) - Contoh: 'NON HIPO', 'HIPO'
- alat_terlibat (string, nullable) - Contoh: 'Heavy Dumptruck'
- nama (string) - Nama korban/terlibat
- jabatan (string) - Contoh: 'Operator'
- shift_kerja_ke (integer, nullable)
- hari_kerja_ke (integer, nullable)
- npk (string, nullable)
- umur (integer, nullable)
- range_umur (string, nullable)
- masa_kerja_perusahaan_tahun (integer, nullable)
- masa_kerja_perusahaan_bulan (integer, nullable)
- range_masa_kerja_perusahaan (string, nullable)
- masa_kerja_bc_tahun (integer, nullable)
- masa_kerja_bc_bulan (integer, nullable)
- range_masa_kerja_bc (string, nullable)
- bagian_luka (string, nullable)
- loss_cost (decimal, nullable)
- saksi_langsung (string, nullable)
- atasan_langsung (string, nullable)
- jabatan_atasan_langsung (string, nullable)
- kontak (string, nullable)
- detail_kontak (text, nullable)
- sumber_kecelakaan (string, nullable)
- layer (string) - Contoh: 'Layer 1', 'Layer 2'
- jenis_item_ipls (string, nullable) - Contoh: 'Improvement', 'Nonconformity', 'Rootcause'
- detail_layer (string, nullable)
- klasifikasi_layer (string, nullable)
- keterangan_layer (text, nullable) - Keterangan detail layer
- id_lokasi_insiden (string, nullable)
- created_at (timestamp)
- updated_at (timestamp)";
    }

    /**
     * Format query results untuk ditampilkan
     */
    private function formatQueryResults($results, $rule = null)
    {
        if (empty($results)) {
            return "Tidak ada data yang ditemukan.";
        }

        $count = count($results);
        $response = "";

        // Jika hasilnya statistik
        if ($rule && isset($rule['is_statistic']) && $rule['is_statistic']) {
            $rowArray = (array) $results[0];
            $columnCount = count($rowArray);
            
            // Jika hanya 1 baris dengan 1-2 kolom (single statistic) - format sebagai card
            if ($count === 1 && $columnCount <= 2) {
                $keys = array_keys($rowArray);
                $values = array_values($rowArray);
                
                $label = ucwords(str_replace('_', ' ', $keys[0]));
                $value = number_format($values[0], 0, ',', '.');
                
                // Format sebagai card HTML yang menarik
                // HTML langsung di-return, tidak perlu escape karena akan di-render via innerHTML
                // Escape hanya untuk content (label dan value), bukan untuk HTML tags
                $response .= '<div class="stat-card">';
                $response .= '<div class="stat-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';
                $response .= '<div class="stat-value">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</div>';
                $response .= '</div>';
            }
            // Jika hanya 1 baris dengan beberapa kolom (overview/ringkasan)
            else if ($count === 1 && $columnCount > 2) {
                $response .= "**Ringkasan Statistik:**\n\n";
                $response .= "<div class='stats-grid'>";
                
                // Mapping label yang lebih baik
                $labelMapping = [
                    'total_cctv' => 'TOTAL CCTV',
                    'cctv_online' => 'TOTAL CCTV LIVE',
                    'cctv_offline' => 'TOTAL CCTV OFFLINE',
                    'cctv_baik' => 'TOTAL CCTV BAIK',
                    'cctv_tidak_baik' => 'TOTAL CCTV TIDAK BAIK',
                    'cctv_breakdown' => 'CCTV BREAKDOWN',
                    'cctv_repair' => 'CCTV REPAIR',
                    'cctv_dismantle' => 'CCTV DISMANTLE',
                    'cctv_rusak' => 'CCTV TIDAK BAIK', // Legacy support
                    'area_kritis' => 'AREA KRITIS',
                    'area_non_kritis' => 'AREA NON KRITIS',
                    'live_view' => 'LIVE VIEW',
                    'kondisi_baik' => 'KONDISI BAIK',
                ];
                
                foreach ($rowArray as $key => $val) {
                    if (is_numeric($val)) {
                        // Gunakan mapping jika ada, jika tidak gunakan format default
                        $label = isset($labelMapping[$key]) 
                            ? $labelMapping[$key] 
                            : strtoupper(str_replace('_', ' ', $key));
                        $response .= "<div class='stat-item'>";
                        $response .= "<div class='stat-item-label'>{$label}</div>";
                        $response .= "<div class='stat-item-value'>{$val}</div>";
                        $response .= "</div>";
                    }
                }
                $response .= "</div>";
            } 
            // Jika multiple rows dengan 2 kolom (distribusi)
            else if ($columnCount <= 2) {
                $response .= "**Statistik:**\n\n";
                $response .= "| " . implode(' | ', array_map(function($key) {
                    return ucwords(str_replace('_', ' ', $key));
                }, array_keys($rowArray))) . " |\n";
                $response .= "|" . str_repeat("---|", $columnCount) . "\n";
                
                foreach ($results as $row) {
                    $rowArray = (array) $row;
                    $values = array_values($rowArray);
                    $response .= "| " . implode(' | ', $values) . " |\n";
                }
            }
            // Format default untuk statistik
            else {
                $response .= "**Statistik:**\n\n";
                $response .= "```\n";
                foreach ($results as $row) {
                    $rowArray = (array) $row;
                    foreach ($rowArray as $key => $value) {
                        $response .= ucfirst(str_replace('_', ' ', $key)) . ": {$value}\n";
                    }
                    $response .= "---\n";
                }
                $response .= "```\n";
            }
        } 
        // Format untuk query data Insiden (deteksi dari rule atau dari kolom hasil query)
        else if (($rule && isset($rule['is_incident']) && $rule['is_incident']) || 
                 $this->isIncidentData($results)) {
            // Group insiden berdasarkan no_kecelakaan
            $groupedIncidents = $this->groupIncidentsByNoKecelakaan($results);
            $uniqueCount = count($groupedIncidents);
            
            // Header dengan statistik yang menarik
            $response = '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center;">';
            $response .= '<div style="color: #fff; font-size: 14px; margin-bottom: 8px; opacity: 0.9;">📊 Total Insiden Ditemukan</div>';
            $response .= '<div style="color: #fff; font-size: 32px; font-weight: 700; margin-bottom: 4px;">' . number_format($uniqueCount, 0, ',', '.') . '</div>';
            $response .= '<div style="color: rgba(255,255,255,0.8); font-size: 12px;">Berdasarkan No. Kecelakaan Unik</div>';
            $response .= '</div>';
            
            $displayLimit = min($uniqueCount, 10);
            $incidentIndex = 0;
            foreach (array_slice($groupedIncidents, 0, $displayLimit, true) as $noKecelakaan => $incidentData) {
                $incidentIndex++;
                $response .= $this->formatGroupedIncident($incidentIndex, $noKecelakaan, $incidentData);
            }
            
            if ($uniqueCount > 10) {
                $response .= '<div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-top: 16px; text-align: center;">';
                $response .= '<span style="color: #856404; font-size: 13px;">⚠️ Menampilkan 10 dari ' . number_format($uniqueCount, 0, ',', '.') . ' insiden. Gunakan query yang lebih spesifik untuk hasil yang lebih tepat.</span>';
                $response .= '</div>';
            }
        }
        // Format untuk query data CCTV
        else {
            $response = "**Ditemukan {$count} data:**\n\n";
            $response .= "| No | Site | Perusahaan | No CCTV | Nama CCTV | Status | Kondisi |\n";
            $response .= "|----|------|------------|---------|-----------|--------|----------|\n";
            
            $displayLimit = min($count, 10);
            foreach (array_slice($results, 0, $displayLimit) as $index => $row) {
                $rowArray = (array) $row;
                $no = $index + 1;
                $site = $rowArray['site'] ?? '-';
                $perusahaan = $rowArray['perusahaan'] ?? '-';
                $noCctv = $rowArray['no_cctv'] ?? '-';
                $namaCctv = $rowArray['nama_cctv'] ?? '-';
                $status = $rowArray['status'] ?? '-';
                $kondisi = $rowArray['kondisi'] ?? '-';
                
                $response .= "| {$no} | {$site} | {$perusahaan} | {$noCctv} | {$namaCctv} | {$status} | {$kondisi} |\n";
            }
            
            if ($count > 10) {
                $response .= "\n*Menampilkan 10 dari {$count} hasil. Gunakan query yang lebih spesifik untuk hasil yang lebih tepat.*";
            }
        }

        return $response;
    }

    /**
     * Group insiden berdasarkan no_kecelakaan
     */
    private function groupIncidentsByNoKecelakaan($results)
    {
        $grouped = [];
        
        foreach ($results as $row) {
            $rowArray = (array) $row;
            $noKecelakaan = $rowArray['no_kecelakaan'] ?? null;
            
            if (!$noKecelakaan) {
                continue;
            }
            
            // Jika belum ada, inisialisasi
            if (!isset($grouped[$noKecelakaan])) {
                $grouped[$noKecelakaan] = [
                    'basic_info' => $rowArray, // Informasi dasar dari baris pertama
                    'layers' => [], // Array untuk menyimpan layer-layer
                    'kronologis' => null, // Kronologi utama
                ];
            }
            
            // Simpan kronologi jika ada
            if (!empty($rowArray['kronologis']) && !$grouped[$noKecelakaan]['kronologis']) {
                $grouped[$noKecelakaan]['kronologis'] = $rowArray['kronologis'];
            }
            
            // Simpan layer jika ada
            if (!empty($rowArray['layer']) || !empty($rowArray['keterangan_layer'])) {
                $layerInfo = [
                    'layer' => $rowArray['layer'] ?? null,
                    'jenis_item_ipls' => $rowArray['jenis_item_ipls'] ?? null,
                    'detail_layer' => $rowArray['detail_layer'] ?? null,
                    'klasifikasi_layer' => $rowArray['klasifikasi_layer'] ?? null,
                    'keterangan_layer' => $rowArray['keterangan_layer'] ?? null,
                ];
                
                // Cek apakah layer ini sudah ada (untuk menghindari duplikasi)
                $layerExists = false;
                foreach ($grouped[$noKecelakaan]['layers'] as $existingLayer) {
                    if ($existingLayer['layer'] === $layerInfo['layer'] && 
                        $existingLayer['keterangan_layer'] === $layerInfo['keterangan_layer']) {
                        $layerExists = true;
                        break;
                    }
                }
                
                if (!$layerExists) {
                    $grouped[$noKecelakaan]['layers'][] = $layerInfo;
                }
            }
        }
        
        // Sort layers berdasarkan urutan layer (Layer 1, Layer 2, Layer 3, dll)
        foreach ($grouped as $noKecelakaan => &$data) {
            usort($data['layers'], function($a, $b) {
                $layerA = $a['layer'] ?? '';
                $layerB = $b['layer'] ?? '';
                
                // Extract number from layer (e.g., "Layer 1" -> 1)
                preg_match('/\d+/', $layerA, $matchesA);
                preg_match('/\d+/', $layerB, $matchesB);
                
                $numA = isset($matchesA[0]) ? (int)$matchesA[0] : 999;
                $numB = isset($matchesB[0]) ? (int)$matchesB[0] : 999;
                
                return $numA <=> $numB;
            });
        }
        
        return $grouped;
    }

    /**
     * Format grouped incident menjadi alur cerita dengan HTML yang menarik
     */
    private function formatGroupedIncident($index, $noKecelakaan, $incidentData)
    {
        $basicInfo = $incidentData['basic_info'];
        
        // Format tanggal
        $tanggal = $basicInfo['tanggal'] ?? null;
        if ($tanggal) {
            try {
                // Handle jika tanggal adalah string
                if (is_string($tanggal)) {
                    $dateObj = new \DateTime($tanggal);
                    $tanggal = $dateObj->format('d/m/Y');
                } 
                // Handle jika tanggal adalah object (dari Carbon/DateTime)
                elseif (is_object($tanggal) && method_exists($tanggal, 'format')) {
                    $tanggal = $tanggal->format('d/m/Y');
                }
                // Handle jika tanggal adalah object dengan property date
                elseif (is_object($tanggal) && isset($tanggal->date)) {
                    $dateObj = new \DateTime($tanggal->date);
                    $tanggal = $dateObj->format('d/m/Y');
                }
            } catch (\Exception $e) {
                $tanggal = $basicInfo['hari'] ?? '-';
                if ($basicInfo['bulan'] && $basicInfo['tahun']) {
                    $tanggal .= ', ' . $basicInfo['bulan'] . '/' . $basicInfo['tahun'];
                }
            }
        } else {
            $tanggal = $basicInfo['hari'] ?? '-';
            if ($basicInfo['bulan'] && $basicInfo['tahun']) {
                $tanggal .= ', ' . $basicInfo['bulan'] . '/' . $basicInfo['tahun'];
            }
        }
        
        $site = htmlspecialchars($basicInfo['site'] ?? '-', ENT_QUOTES, 'UTF-8');
        $perusahaan = htmlspecialchars($basicInfo['perusahaan'] ?? '-', ENT_QUOTES, 'UTF-8');
        $lokasi = htmlspecialchars($basicInfo['lokasi'] ?? ($basicInfo['lokasi_spesifik'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $kategori = htmlspecialchars($basicInfo['kategori'] ?? '-', ENT_QUOTES, 'UTF-8');
        $nama = htmlspecialchars($basicInfo['nama'] ?? '-', ENT_QUOTES, 'UTF-8');
        $jabatan = htmlspecialchars($basicInfo['jabatan'] ?? '-', ENT_QUOTES, 'UTF-8');
        $shift = htmlspecialchars($basicInfo['shift'] ?? '', ENT_QUOTES, 'UTF-8');
        $jam = $basicInfo['jam'] ?? null;
        $menit = $basicInfo['menit'] ?? null;
        $statusLpi = htmlspecialchars($basicInfo['status_lpi'] ?? '', ENT_QUOTES, 'UTF-8');
        $highPotential = htmlspecialchars($basicInfo['high_potential'] ?? '', ENT_QUOTES, 'UTF-8');
        $alatTerlibat = htmlspecialchars($basicInfo['alat_terlibat'] ?? '', ENT_QUOTES, 'UTF-8');
        $noKecelakaanEscaped = htmlspecialchars($noKecelakaan, ENT_QUOTES, 'UTF-8');
        $tanggalEscaped = htmlspecialchars($tanggal, ENT_QUOTES, 'UTF-8');
        
        // Warna badge berdasarkan kategori
        $kategoriColor = $this->getCategoryColor($kategori);
        $statusColor = $this->getStatusColor($statusLpi);
        $hipoColor = $this->getHipoColor($highPotential);
        
        // Mulai HTML card
        $response = '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 20px; margin: 16px 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
        
        // Header dengan no kecelakaan
        $response .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid rgba(255,255,255,0.3);">';
        $response .= '<div>';
        $response .= '<h3 style="margin: 0; color: #fff; font-size: 18px; font-weight: 600;">📋 Insiden #' . $index . '</h3>';
        $response .= '<p style="margin: 4px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px; font-weight: 500;">' . $noKecelakaanEscaped . '</p>';
        $response .= '</div>';
        if ($statusLpi) {
            $response .= '<span style="background: ' . $statusColor . '; color: #fff; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">' . $statusLpi . '</span>';
        }
        $response .= '</div>';
        
        // Informasi Dasar dalam Grid
        $response .= '<div style="background: #fff; border-radius: 8px; padding: 16px; margin-bottom: 16px;">';
        $response .= '<h4 style="margin: 0 0 12px 0; color: #333; font-size: 16px; font-weight: 600; display: flex; align-items: center;">';
        $response .= '<span style="margin-right: 8px;">📌</span> Informasi Dasar';
        $response .= '</h4>';
        
        $response .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">';
        
        // Tanggal & Waktu
        $response .= '<div style="padding: 8px; background: #f8f9fa; border-radius: 6px;">';
        $response .= '<div style="font-size: 11px; color: #666; margin-bottom: 4px;">📅 Tanggal</div>';
        $response .= '<div style="font-weight: 600; color: #333;">' . $tanggalEscaped;
        if ($jam !== null && $menit !== null) {
            $timeStr = str_pad($jam, 2, '0', STR_PAD_LEFT) . ':' . str_pad($menit, 2, '0', STR_PAD_LEFT);
            $response .= ' <span style="color: #667eea;">⏰ ' . htmlspecialchars($timeStr, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $response .= '</div></div>';
        
        // Site
        $response .= '<div style="padding: 8px; background: #f8f9fa; border-radius: 6px;">';
        $response .= '<div style="font-size: 11px; color: #666; margin-bottom: 4px;">🏢 Site</div>';
        $response .= '<div style="font-weight: 600; color: #333;">' . $site . '</div></div>';
        
        // Perusahaan
        $response .= '<div style="padding: 8px; background: #f8f9fa; border-radius: 6px;">';
        $response .= '<div style="font-size: 11px; color: #666; margin-bottom: 4px;">🏭 Perusahaan</div>';
        $response .= '<div style="font-weight: 600; color: #333;">' . $perusahaan . '</div></div>';
        
        // Lokasi
        $response .= '<div style="padding: 8px; background: #f8f9fa; border-radius: 6px;">';
        $response .= '<div style="font-size: 11px; color: #666; margin-bottom: 4px;">📍 Lokasi</div>';
        $response .= '<div style="font-weight: 600; color: #333;">' . $lokasi . '</div></div>';
        
        // Kategori dengan badge
        $response .= '<div style="padding: 8px; background: #f8f9fa; border-radius: 6px;">';
        $response .= '<div style="font-size: 11px; color: #666; margin-bottom: 4px;">🏷️ Kategori</div>';
        $response .= '<span style="background: ' . $kategoriColor . '; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">' . $kategori . '</span></div>';
        
        // Shift
        if ($shift) {
            $response .= '<div style="padding: 8px; background: #f8f9fa; border-radius: 6px;">';
            $response .= '<div style="font-size: 11px; color: #666; margin-bottom: 4px;">🔄 Shift</div>';
            $response .= '<div style="font-weight: 600; color: #333;">' . $shift . '</div></div>';
        }
        
        // High Potential
        if ($highPotential) {
            $response .= '<div style="padding: 8px; background: #f8f9fa; border-radius: 6px;">';
            $response .= '<div style="font-size: 11px; color: #666; margin-bottom: 4px;">⚠️ High Potential</div>';
            $response .= '<span style="background: ' . $hipoColor . '; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">' . $highPotential . '</span></div>';
        }
        
        // Alat Terlibat
        if ($alatTerlibat) {
            $response .= '<div style="padding: 8px; background: #f8f9fa; border-radius: 6px;">';
            $response .= '<div style="font-size: 11px; color: #666; margin-bottom: 4px;">🚛 Alat Terlibat</div>';
            $response .= '<div style="font-weight: 600; color: #333;">' . $alatTerlibat . '</div></div>';
        }
        
        // Terlibat
        if ($nama && $nama !== '-') {
            $response .= '<div style="padding: 8px; background: #f8f9fa; border-radius: 6px;">';
            $response .= '<div style="font-size: 11px; color: #666; margin-bottom: 4px;">👤 Terlibat</div>';
            $response .= '<div style="font-weight: 600; color: #333;">' . $nama;
            if ($jabatan && $jabatan !== '-') {
                $response .= ' <span style="color: #667eea; font-size: 12px;">(' . $jabatan . ')</span>';
            }
            $response .= '</div></div>';
        }
        
        $response .= '</div>'; // End grid
        $response .= '</div>'; // End info card
        
        // Kronologi
        if (!empty($incidentData['kronologis'])) {
            $kronologis = htmlspecialchars($incidentData['kronologis'], ENT_QUOTES, 'UTF-8');
            $response .= '<div style="background: #fff; border-radius: 8px; padding: 16px; margin-bottom: 16px;">';
            $response .= '<h4 style="margin: 0 0 12px 0; color: #333; font-size: 16px; font-weight: 600; display: flex; align-items: center;">';
            $response .= '<span style="margin-right: 8px;">📖</span> Kronologi Kejadian';
            $response .= '</h4>';
            $response .= '<div style="background: #f8f9fa; padding: 12px; border-radius: 6px; border-left: 4px solid #667eea; color: #333; line-height: 1.6;">' . nl2br($kronologis) . '</div>';
            $response .= '</div>';
        }
        
        // Alur Penanganan (Timeline)
        if (!empty($incidentData['layers'])) {
            $response .= '<div style="background: #fff; border-radius: 8px; padding: 16px;">';
            $response .= '<h4 style="margin: 0 0 16px 0; color: #333; font-size: 16px; font-weight: 600; display: flex; align-items: center;">';
            $response .= '<span style="margin-right: 8px;">🔧</span> Alur Penanganan';
            $response .= '</h4>';
            
            $layerCount = count($incidentData['layers']);
            foreach ($incidentData['layers'] as $idx => $layer) {
                $layerName = htmlspecialchars($layer['layer'] ?? 'Layer Tidak Diketahui', ENT_QUOTES, 'UTF-8');
                $jenisItem = htmlspecialchars($layer['jenis_item_ipls'] ?? '', ENT_QUOTES, 'UTF-8');
                $detailLayer = htmlspecialchars($layer['detail_layer'] ?? '', ENT_QUOTES, 'UTF-8');
                $klasifikasi = htmlspecialchars($layer['klasifikasi_layer'] ?? '', ENT_QUOTES, 'UTF-8');
                $keterangan = htmlspecialchars($layer['keterangan_layer'] ?? '', ENT_QUOTES, 'UTF-8');
                
                $isLast = ($idx === $layerCount - 1);
                $layerColor = $this->getLayerColor($jenisItem);
                
                $response .= '<div style="position: relative; padding-left: 32px; margin-bottom: ' . ($isLast ? '0' : '16px') . ';">';
                
                // Timeline line
                if (!$isLast) {
                    $response .= '<div style="position: absolute; left: 11px; top: 24px; bottom: -16px; width: 2px; background: #e0e0e0;"></div>';
                }
                
                // Timeline dot
                $response .= '<div style="position: absolute; left: 4px; top: 4px; width: 16px; height: 16px; border-radius: 50%; background: ' . $layerColor . '; border: 3px solid #fff; box-shadow: 0 0 0 2px ' . $layerColor . ';"></div>';
                
                // Layer content
                $response .= '<div style="background: ' . $layerColor . '; border-radius: 8px; padding: 12px; margin-left: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                $response .= '<div style="display: flex; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">';
                $response .= '<strong style="color: #fff; font-size: 14px; font-weight: 600;">' . $layerName . '</strong>';
                if ($jenisItem) {
                    $response .= '<span style="background: rgba(255,255,255,0.25); color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap;">' . $jenisItem . '</span>';
                }
                $response .= '</div>';
                
                if ($detailLayer) {
                    $response .= '<div style="color: rgba(255,255,255,0.95); font-size: 13px; margin-bottom: 6px; font-weight: 500; line-height: 1.5;">';
                    $response .= '<span style="margin-right: 6px;">📝</span>' . $detailLayer;
                    $response .= '</div>';
                }
                
                if ($klasifikasi) {
                    $response .= '<div style="color: rgba(255,255,255,0.9); font-size: 12px; margin-bottom: 6px; line-height: 1.5;">';
                    $response .= '<span style="margin-right: 6px;">🏷️</span>' . $klasifikasi;
                    $response .= '</div>';
                }
                
                if ($keterangan) {
                    $response .= '<div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 6px; margin-top: 10px; color: #fff; font-size: 13px; line-height: 1.6;">';
                    $response .= '<strong style="display: block; margin-bottom: 6px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">💡 Penanganan:</strong>';
                    $response .= '<div style="white-space: pre-wrap;">' . nl2br($keterangan) . '</div>';
                    $response .= '</div>';
                }
                
                $response .= '</div>'; // End layer content
                $response .= '</div>'; // End timeline item
            }
            
            $response .= '</div>'; // End timeline container
        }
        
        $response .= '</div>'; // End main card
        
        return $response;
    }
    
    /**
     * Get color untuk kategori
     */
    private function getCategoryColor($kategori)
    {
        $kategoriLower = strtolower($kategori);
        if (stripos($kategoriLower, 'accident') !== false || stripos($kategoriLower, 'kecelakaan') !== false) {
            return '#ef4444'; // Red
        } elseif (stripos($kategoriLower, 'nearmiss') !== false || stripos($kategoriLower, 'near miss') !== false) {
            return '#f59e0b'; // Orange
        } elseif (stripos($kategoriLower, 'property') !== false || stripos($kategoriLower, 'damage') !== false) {
            return '#8b5cf6'; // Purple
        }
        return '#667eea'; // Default blue
    }
    
    /**
     * Get color untuk status LPI
     */
    private function getStatusColor($status)
    {
        $statusLower = strtolower($status);
        if (stripos($statusLower, 'open') !== false) {
            return '#ef4444'; // Red
        } elseif (stripos($statusLower, 'progress') !== false || stripos($statusLower, 'in progress') !== false) {
            return '#f59e0b'; // Orange
        } elseif (stripos($statusLower, 'closed') !== false || stripos($statusLower, 'resolved') !== false) {
            return '#10b981'; // Green
        }
        return '#6b7280'; // Default gray
    }
    
    /**
     * Get color untuk High Potential
     */
    private function getHipoColor($hipo)
    {
        $hipoLower = strtolower($hipo);
        if (stripos($hipoLower, 'hipo') !== false) {
            return '#ef4444'; // Red
        }
        return '#10b981'; // Green for NON HIPO
    }
    
    /**
     * Get color untuk layer berdasarkan jenis item
     */
    private function getLayerColor($jenisItem)
    {
        $jenisLower = strtolower($jenisItem ?? '');
        if (stripos($jenisLower, 'improvement') !== false) {
            return '#10b981'; // Green
        } elseif (stripos($jenisLower, 'nonconformity') !== false) {
            return '#f59e0b'; // Orange
        } elseif (stripos($jenisLower, 'rootcause') !== false || stripos($jenisLower, 'root cause') !== false) {
            return '#ef4444'; // Red
        }
        return '#667eea'; // Default blue
    }

    /**
     * Generate chart data dari query results
     */
    private function generateChartData($results, $rule)
    {
        if (empty($results)) {
            return null;
        }

        $rowArray = (array) $results[0];
        $columnCount = count($rowArray);
        $rowCount = count($results);

        // Jika 1 row dengan multiple columns (overview)
        if ($rowCount === 1 && $columnCount > 2) {
            return $this->generateOverviewChart($rowArray);
        }
        
        // Jika multiple rows dengan 2 columns (distribusi)
        if ($rowCount > 1 && $columnCount <= 2) {
            return $this->generateDistributionChart($results);
        }

        // Jika 1 row dengan 1-2 columns (single stat) - selalu generate chart
        if ($rowCount === 1 && $columnCount <= 2) {
            return $this->generateSingleStatChart($rowArray);
        }

        return null;
    }

    /**
     * Detect dan generate chart data untuk AI-generated queries
     */
    private function detectAndGenerateChartData($results, $sql)
    {
        if (empty($results)) {
            return null;
        }

        $rowArray = (array) $results[0];
        $columnCount = count($rowArray);
        $rowCount = count($results);

        // Detect jika query mengandung GROUP BY atau COUNT
        $isGroupBy = stripos($sql, 'GROUP BY') !== false;
        $isCount = stripos($sql, 'COUNT') !== false;

        if ($isGroupBy && $rowCount > 1 && $columnCount <= 2) {
            return $this->generateDistributionChart($results);
        }

        if ($isCount && $rowCount === 1 && $columnCount <= 2) {
            return $this->generateSingleStatChart($rowArray);
        }

        if ($rowCount === 1 && $columnCount > 2) {
            return $this->generateOverviewChart($rowArray);
        }

        return null;
    }

    /**
     * Generate chart untuk overview (multiple metrics)
     */
    private function generateOverviewChart($data)
    {
        $labels = [];
        $values = [];
        $colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe'];

        // Mapping label yang lebih baik
        $labelMapping = [
            'total_cctv' => 'Total Cctv',
            'cctv_online' => 'Total Cctv Live',
            'cctv_offline' => 'Total Cctv Offline',
            'cctv_baik' => 'Total Cctv Baik',
            'cctv_tidak_baik' => 'Total Cctv Tidak Baik',
            'cctv_breakdown' => 'Cctv Breakdown',
            'cctv_repair' => 'Cctv Repair',
            'cctv_dismantle' => 'Cctv Dismantle',
            'cctv_rusak' => 'Cctv Tidak Baik', // Legacy support
            'area_kritis' => 'Area Kritis',
            'area_non_kritis' => 'Area Non Kritis',
            'live_view' => 'Live View',
            'kondisi_baik' => 'Kondisi Baik',
        ];

        $index = 0;
        foreach ($data as $key => $value) {
            if (is_numeric($value)) {
                // Gunakan mapping jika ada, jika tidak gunakan format default
                $label = isset($labelMapping[$key]) 
                    ? $labelMapping[$key] 
                    : ucwords(str_replace('_', ' ', $key));
                $labels[] = $label;
                $values[] = (float) $value;
                $index++;
            }
        }

        if (empty($values)) {
            return null;
        }

        return [
            'type' => 'bar',
            'config' => [
                'series' => [['name' => 'Nilai', 'data' => $values]],
                'chart' => [
                    'type' => 'bar',
                    'height' => 350,
                    'toolbar' => ['show' => false]
                ],
                'plotOptions' => [
                    'bar' => [
                        'horizontal' => false,
                        'columnWidth' => '55%',
                        'borderRadius' => 4
                    ]
                ],
                'dataLabels' => ['enabled' => true],
                'xaxis' => ['categories' => $labels],
                'colors' => array_slice($colors, 0, count($values)),
                'title' => ['text' => 'Overview Statistik', 'align' => 'left']
            ]
        ];
    }

    /**
     * Generate chart untuk distribusi (2 columns)
     */
    private function generateDistributionChart($results)
    {
        $labels = [];
        $values = [];
        $colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe', '#43e97b', '#fa709a'];

        foreach ($results as $row) {
            $rowArray = (array) $row;
            $keys = array_keys($rowArray);
            
            // Ambil kolom pertama sebagai label, kedua sebagai value
            $labelKey = $keys[0];
            $valueKey = $keys[1] ?? $keys[0];
            
            $label = $rowArray[$labelKey];
            $value = is_numeric($rowArray[$valueKey]) ? (float) $rowArray[$valueKey] : 0;
            
            if ($value > 0) {
                $labels[] = $label ?: 'Unknown';
                $values[] = $value;
            }
        }

        if (empty($values)) {
            return null;
        }

        // Tentukan chart type berdasarkan jumlah data
        $chartType = count($values) <= 5 ? 'pie' : 'bar';

        if ($chartType === 'pie') {
            return [
                'type' => 'pie',
                'config' => [
                    'series' => $values,
                    'chart' => [
                        'type' => 'pie',
                        'height' => 350
                    ],
                    'labels' => $labels,
                    'colors' => array_slice($colors, 0, count($values)),
                    'legend' => ['position' => 'bottom'],
                    'dataLabels' => ['enabled' => true]
                ]
            ];
        } else {
            return [
                'type' => 'bar',
                'config' => [
                    'series' => [['name' => 'Jumlah', 'data' => $values]],
                    'chart' => [
                        'type' => 'bar',
                        'height' => 350,
                        'toolbar' => ['show' => false]
                    ],
                    'plotOptions' => [
                        'bar' => [
                            'horizontal' => true,
                            'columnWidth' => '55%',
                            'borderRadius' => 4
                        ]
                    ],
                    'dataLabels' => ['enabled' => true],
                    'xaxis' => ['categories' => $labels],
                    'colors' => ['#667eea']
                ]
            ];
        }
    }

    /**
     * Generate chart untuk single statistic
     */
    private function generateSingleStatChart($data)
    {
        $keys = array_keys($data);
        $values = array_values($data);
        
        $label = ucwords(str_replace('_', ' ', $keys[0]));
        $value = is_numeric($values[0]) ? (float) $values[0] : 0;

        return [
            'type' => 'donut',
            'config' => [
                'series' => [$value],
                'chart' => [
                    'type' => 'donut',
                    'height' => 300
                ],
                'labels' => [$label],
                'colors' => ['#667eea'],
                'plotOptions' => [
                    'pie' => [
                        'donut' => [
                            'size' => '70%',
                            'labels' => [
                                'show' => true,
                                'name' => ['show' => true, 'fontSize' => '16px', 'fontWeight' => 600],
                                'value' => ['show' => true, 'fontSize' => '20px', 'fontWeight' => 700]
                            ]
                        ]
                    ]
                ],
                'dataLabels' => ['enabled' => false]
            ]
        ];
    }

    /**
     * Generate AI explanation berdasarkan data (menggunakan Gemini)
     */
    private function generateAIExplanation($results, $rule, $originalMessage)
    {
        try {
            // Prepare data summary
            $dataSummary = $this->prepareDataSummary($results, $rule);
            
            // Determine context dari rule dan message
            $context = $this->determineContext($rule, $originalMessage, $results);
            
            $prompt = "Anda adalah analis data HSE (Health, Safety, Environment) yang ahli di bidang manajemen CCTV dan monitoring area kritis di pertambangan. Berdasarkan hasil query database CCTV berikut, berikan analisis lengkap dalam Bahasa Indonesia.

DATA HASIL QUERY:
{$dataSummary}

PERTANYAAN USER: {$originalMessage}

KONTEKS: {$context}

Tugas Anda:
1. **Penjelasan Data**: Jelaskan apa yang ditampilkan dari data ini dengan jelas dan mudah dipahami
2. **Analisis Mendalam**: Berikan analisis yang lebih dalam tentang data ini, apa artinya dalam konteks HSE dan monitoring CCTV
3. **Rekomendasi Tindakan**: Berikan rekomendasi spesifik yang harus dilakukan berdasarkan data ini. Rekomendasi harus:
   - Spesifik dan actionable
   - Relevan dengan konteks HSE dan monitoring CCTV
   - Prioritas tindakan jika ada
   - Saran perbaikan atau peningkatan jika diperlukan

Format jawaban:
- Gunakan struktur yang jelas dengan heading
- Gunakan bullet points untuk rekomendasi
- Maksimal 250 kata
- Fokus pada insight yang actionable

Contoh untuk area kritis:
Jika data menunjukkan area kritis sebanyak 660, jelaskan:
- Apa artinya memiliki 660 area kritis
- Apakah ini jumlah yang normal atau perlu perhatian
- Rekomendasi: monitoring lebih ketat, penambahan CCTV, review prosedur keselamatan, dll";

            // Gunakan Gemini langsung untuk explanation
            $conversationHistory = [];
            $aiResponse = $this->qwenAIService->chat($prompt, $conversationHistory);

            if ($aiResponse['success']) {
                return $aiResponse['message'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('AI Explanation Error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate analisis pembelajaran dari insiden
     */
    private function generateIncidentLearningAnalysis($results, $originalMessage)
    {
        try {
            // Prepare data summary untuk insiden
            $dataSummary = $this->prepareIncidentDataSummary($results);
            
            $prompt = "Anda adalah analis HSE (Health, Safety, Environment) yang ahli dalam investigasi insiden dan analisis pembelajaran dari kejadian kecelakaan/nearmiss di pertambangan. Berdasarkan data insiden berikut, berikan analisis pembelajaran yang mendalam dalam Bahasa Indonesia.

DATA INSIDEN:
{$dataSummary}

PERTANYAAN USER: {$originalMessage}

Tugas Anda:
1. **Ringkasan Kejadian**: Ringkas kejadian utama dari insiden-insiden ini dengan jelas
2. **Pelajaran yang Dapat Diambil**: Identifikasi pelajaran penting yang dapat dipelajari dari setiap insiden, termasuk:
   - Faktor penyebab (root cause)
   - Kondisi tidak aman yang teridentifikasi
   - Pelanggaran prosedur atau SOP
   - Masalah sistemik yang perlu diperbaiki
3. **Rekomendasi Pencegahan**: Berikan rekomendasi spesifik untuk mencegah kejadian serupa di masa depan:
   - Perbaikan prosedur/SOP
   - Peningkatan pengawasan
   - Training yang diperlukan
   - Perbaikan infrastruktur/area kerja
   - Peningkatan kesadaran safety
4. **Tindakan Prioritas**: Urutkan rekomendasi berdasarkan prioritas dan urgensi

Format jawaban:
- Gunakan struktur yang jelas dengan heading
- Gunakan bullet points untuk setiap poin
- Maksimal 400 kata
- Fokus pada insight yang actionable dan dapat diterapkan
- Berikan contoh konkret dari data insiden

Contoh format:
**Ringkasan Kejadian:**
- [Ringkasan singkat kejadian]

**Pelajaran yang Dapat Diambil:**
- [Pelajaran 1]
- [Pelajaran 2]

**Rekomendasi Pencegahan:**
- [Rekomendasi 1]
- [Rekomendasi 2]

**Tindakan Prioritas:**
- [Tindakan prioritas tinggi]";

            $conversationHistory = [];
            $aiResponse = $this->qwenAIService->chat($prompt, $conversationHistory);

            if ($aiResponse['success']) {
                return $aiResponse['message'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Incident Learning Analysis Error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Prepare data summary untuk insiden
     */
    private function prepareIncidentDataSummary($results)
    {
        if (empty($results)) {
            return "Tidak ada data insiden ditemukan.";
        }

        // Group insiden berdasarkan no_kecelakaan
        $groupedIncidents = $this->groupIncidentsByNoKecelakaan($results);
        $uniqueCount = count($groupedIncidents);
        
        $summary = "Jumlah insiden (berdasarkan no kecelakaan): {$uniqueCount}\n\n";
        
        $index = 0;
        foreach (array_slice($groupedIncidents, 0, 10, true) as $noKecelakaan => $incidentData) {
            $index++;
            $basicInfo = $incidentData['basic_info'];
            
            $summary .= "**Insiden {$index} - {$noKecelakaan}:**\n";
            $summary .= "- Tanggal: " . ($basicInfo['tanggal'] ?? ($basicInfo['hari'] ?? '-') . ', ' . ($basicInfo['bulan'] ?? '-') . '/' . ($basicInfo['tahun'] ?? '-')) . "\n";
            $summary .= "- Site: " . ($basicInfo['site'] ?? '-') . "\n";
            $summary .= "- Perusahaan: " . ($basicInfo['perusahaan'] ?? '-') . "\n";
            $summary .= "- Lokasi: " . ($basicInfo['lokasi'] ?? ($basicInfo['lokasi_spesifik'] ?? '-')) . "\n";
            $summary .= "- Kategori: " . ($basicInfo['kategori'] ?? '-') . "\n";
            $summary .= "- Nama Terlibat: " . ($basicInfo['nama'] ?? '-') . " (" . ($basicInfo['jabatan'] ?? '-') . ")\n";
            
            if (!empty($incidentData['kronologis'])) {
                $kronologis = $incidentData['kronologis'];
                $kronologisShort = strlen($kronologis) > 300 ? substr($kronologis, 0, 300) . '...' : $kronologis;
                $summary .= "- Kronologi: " . $kronologisShort . "\n";
            }
            
            if (!empty($incidentData['layers'])) {
                $summary .= "- Layer Penanganan:\n";
                foreach ($incidentData['layers'] as $layer) {
                    $layerName = $layer['layer'] ?? 'Layer Tidak Diketahui';
                    $jenisItem = $layer['jenis_item_ipls'] ?? null;
                    $keterangan = $layer['keterangan_layer'] ?? null;
                    
                    $summary .= "  * {$layerName}";
                    if ($jenisItem) {
                        $summary .= " ({$jenisItem})";
                    }
                    $summary .= "\n";
                    
                    if ($keterangan) {
                        $keteranganShort = strlen($keterangan) > 200 ? substr($keterangan, 0, 200) . '...' : $keterangan;
                        $summary .= "    " . $keteranganShort . "\n";
                    }
                }
            }
            
            $summary .= "\n";
        }

        if ($uniqueCount > 10) {
            $summary .= "\n*Total {$uniqueCount} insiden, menampilkan 10 pertama untuk analisis.*";
        }

        return $summary;
    }

    /**
     * Determine context dari rule dan message untuk AI explanation
     */
    private function determineContext($rule, $message, $results)
    {
        $context = [];
        
        if ($rule) {
            $context[] = "Rule: " . ($rule['name'] ?? 'Unknown');
            $context[] = "Kategori Query: " . (isset($rule['is_statistic']) && $rule['is_statistic'] ? 'Statistik' : 'Data Query');
        }
        
        // Extract key information dari message
        $messageLower = strtolower($message);
        if (stripos($messageLower, 'area kritis') !== false) {
            $context[] = "Fokus: Area Kritis - Area yang memerlukan monitoring ketat";
        }
        if (stripos($messageLower, 'area non kritis') !== false) {
            $context[] = "Fokus: Area Non Kritis - Area dengan risiko lebih rendah";
        }
        if (stripos($messageLower, 'cctv') !== false) {
            $context[] = "Fokus: Monitoring CCTV";
        }
        if (stripos($messageLower, 'distribusi') !== false || stripos($messageLower, 'perbandingan') !== false) {
            $context[] = "Tipe Analisis: Distribusi/Perbandingan";
        }
        
        // Extract value dari results
        if (!empty($results)) {
            $rowArray = (array) $results[0];
            $keys = array_keys($rowArray);
            $values = array_values($rowArray);
            
            if (count($rowArray) <= 2 && is_numeric($values[0])) {
                $context[] = "Nilai: " . number_format($values[0], 0, ',', '.');
            }
        }
        
        return implode("\n", $context);
    }

    /**
     * Prepare data summary untuk AI explanation
     */
    private function prepareDataSummary($results, $rule)
    {
        if (empty($results)) {
            return "Tidak ada data ditemukan.";
        }

        $summary = "Jumlah baris: " . count($results) . "\n\n";
        
        $rowArray = (array) $results[0];
        $summary .= "Kolom yang ada: " . implode(', ', array_keys($rowArray)) . "\n\n";
        
        if (count($results) <= 5) {
            $summary .= "Data detail:\n";
            foreach ($results as $index => $row) {
                $rowArray = (array) $row;
                $summary .= "Baris " . ($index + 1) . ":\n";
                foreach ($rowArray as $key => $value) {
                    $summary .= "  - " . ucwords(str_replace('_', ' ', $key)) . ": {$value}\n";
                }
                $summary .= "\n";
            }
        } else {
            $summary .= "Data ringkasan (5 pertama):\n";
            foreach (array_slice($results, 0, 5) as $index => $row) {
                $rowArray = (array) $row;
                $summary .= "Baris " . ($index + 1) . ": " . json_encode($rowArray) . "\n";
            }
        }

        if ($rule) {
            $summary .= "\nRule yang digunakan: " . ($rule['name'] ?? 'Unknown');
        }

        return $summary;
    }

    /**
     * Select rule menggunakan AI (Gemini)
     */
    private function selectRuleWithAI($message)
    {
        try {
            $allRules = $this->ruleService->getAllRules();
            $tableSchema = $this->getTableSchema();
            
            $aiResponse = $this->qwenAIService->selectRuleAndGenerateQuery($message, $allRules, $tableSchema);
            
            if ($aiResponse['success']) {
                Log::info('AI Rule Selection Success', [
                    'rule_id' => $aiResponse['data']['rule_id'] ?? null,
                    'confidence' => $aiResponse['data']['confidence'] ?? 0
                ]);
                return $aiResponse;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('AI Rule Selection Error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Handle AI rule selection result
     */
    private function handleAIRuleSelection($aiSelection, $message, $conversationHistory)
    {
        try {
            $data = $aiSelection['data'];
            $ruleId = $data['rule_id'] ?? null;
            $sql = $data['sql_query'] ?? null;
            $initialRecommendation = $data['initial_recommendation'] ?? null;
            
            // Jika AI tidak memilih rule atau confidence terlalu rendah, fallback
            if (!$ruleId || ($data['confidence'] ?? 0) < 0.5) {
                return $this->generateSQLWithAI($message, $conversationHistory);
            }
            
            // Get rule dari rule service
            $rule = $this->ruleService->getRuleById($ruleId);
            
            if (!$rule) {
                Log::warning('Rule not found', ['rule_id' => $ruleId]);
                return $this->generateSQLWithAI($message, $conversationHistory);
            }
            
            // Gunakan SQL dari AI jika ada, atau generate dari rule
            if (!$sql) {
                $sql = $this->ruleService->generateSQL($rule, $message);
            }
            
            if (!$sql) {
                return $this->generateSQLWithAI($message, $conversationHistory);
            }
            
            // Eksekusi SQL
            try {
                $results = DB::select($sql);
                $formattedResponse = $this->formatQueryResults($results, $rule);
                
                // Generate chart data
                $chartData = null;
                if (isset($rule['is_statistic']) && $rule['is_statistic']) {
                    $chartData = $this->generateChartData($results, $rule);
                    if (!$chartData && count($results) === 1) {
                        $rowArray = (array) $results[0];
                        if (count($rowArray) <= 2) {
                            $chartData = $this->generateSingleStatChart($rowArray);
                        }
                    }
                }
                
                // Generate rekomendasi kontekstual menggunakan Gemini
                // Khusus untuk insiden dengan analisis pembelajaran
                $contextualRecommendation = null;
                if (isset($rule['is_incident']) && $rule['is_incident']) {
                    $messageLower = strtolower($message);
                    if (stripos($messageLower, 'pelajari') !== false || 
                        stripos($messageLower, 'belajar') !== false ||
                        stripos($messageLower, 'apa yang bisa') !== false) {
                        $contextualRecommendation = $this->generateIncidentLearningAnalysis($results, $message);
                    } else {
                        $contextualRecommendation = $this->generateContextualRecommendationWithAI(
                            $results, 
                            $rule, 
                            $message,
                            $initialRecommendation
                        );
                    }
                } else {
                    $contextualRecommendation = $this->generateContextualRecommendationWithAI(
                        $results, 
                        $rule, 
                        $message,
                        $initialRecommendation
                    );
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $formattedResponse,
                    'model' => 'ai-rule-based',
                    'rule_id' => $ruleId,
                    'sql' => $sql,
                    'chart' => $chartData,
                    'explanation' => $contextualRecommendation,
                    'has_chart' => $chartData !== null,
                    'ai_reasoning' => $data['reasoning'] ?? null
                ]);
                
            } catch (\Exception $e) {
                Log::error('AI SQL execution error', [
                    'sql' => $sql,
                    'error' => $e->getMessage()
                ]);
                
                return $this->generateSQLWithAI($message, $conversationHistory);
            }
            
        } catch (\Exception $e) {
            Log::error('Handle AI Rule Selection Error', ['error' => $e->getMessage()]);
            return $this->generateSQLWithAI($message, $conversationHistory);
        }
    }

    /**
     * Generate rekomendasi kontekstual menggunakan AI (Gemini)
     */
    private function generateContextualRecommendationWithAI($results, $rule, $message, $initialRecommendation = null)
    {
        try {
            $context = $this->determineContext($rule, $message, $results);
            
            // Gunakan Gemini untuk generate rekomendasi kontekstual
            $recommendation = $this->qwenAIService->generateContextualRecommendation(
                $results,
                $context,
                $message
            );
            
            // Gabungkan dengan initial recommendation jika ada
            if ($initialRecommendation && $recommendation) {
                return "**Rekomendasi Awal:**\n{$initialRecommendation}\n\n**Analisis Mendalam:**\n{$recommendation}";
            } elseif ($initialRecommendation) {
                return "**Rekomendasi:**\n{$initialRecommendation}";
            } elseif ($recommendation) {
                return $recommendation;
            }
            
            // Fallback ke generateAIExplanation jika tidak ada
            return $this->generateAIExplanation($results, $rule, $message);
            
        } catch (\Exception $e) {
            Log::error('Contextual Recommendation Error', ['error' => $e->getMessage()]);
            return $this->generateAIExplanation($results, $rule, $message);
        }
    }
}

