<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ClickHouseService
{
    private $baseUrl;
    private $username;
    private $password;
    private $database;
    private $timeout;
    private $isConnected = false;

    public function __construct()
    {
        $host = config('database.connections.clickhouse.host');
        $port = config('database.connections.clickhouse.port');
        $protocol = config('database.connections.clickhouse.options.protocol', 'http');
        
        // Jika konfigurasi tidak lengkap, set sebagai tidak terhubung
        if (empty($host) || empty($port)) {
            Log::info('ClickHouse configuration is incomplete. Host or port is missing.');
            $this->isConnected = false;
            return;
        }
        
        // Build base URL dengan protocol
        // Pastikan menggunakan HTTP jika port 8123 (default HTTP port untuk ClickHouse)
        if ($port == 8123 && $protocol == 'https') {
            Log::warning('Port 8123 is typically used for HTTP, not HTTPS. Consider using CLICKHOUSE_PROTOCOL=http');
        }
        
        $this->baseUrl = $protocol . '://' . $host . ':' . $port;
        $this->username = config('database.connections.clickhouse.username', 'default');
        $this->password = config('database.connections.clickhouse.password', '');
        $this->database = config('database.connections.clickhouse.database', 'default');
        $this->timeout = config('database.connections.clickhouse.options.timeout', 30);
        
        Log::info('ClickHouse configuration loaded', [
            'baseUrl' => $this->baseUrl,
            'database' => $this->database,
            'username' => $this->username,
            'password_set' => !empty($this->password),
            'protocol' => $protocol
        ]);

        // Test connection (non-blocking, will be tested when needed)
        // Don't throw exception here, just set isConnected flag
        try {
            $pingResult = $this->ping();
            if ($pingResult) {
                $this->isConnected = true;
                Log::info('ClickHouse connected successfully to ' . $this->baseUrl);
            } else {
                $this->isConnected = false;
                Log::warning('ClickHouse ping returned false');
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('ClickHouse connection failed (network/timeout): ' . $e->getMessage());
            Log::warning('ClickHouse URL: ' . $this->baseUrl);
            $this->isConnected = false;
        } catch (Exception $e) {
            // Jika status 200 tapi format check gagal, tetap set sebagai connected
            // karena server accessible (masalah hanya di format response)
            if (strpos($e->getMessage(), 'Status: 200') !== false) {
                Log::info('ClickHouse ping returned 200, treating as connected: ' . $e->getMessage());
                $this->isConnected = true;
            } else {
                Log::warning('ClickHouse connection failed: ' . $e->getMessage());
                Log::warning('ClickHouse URL: ' . $this->baseUrl);
                $this->isConnected = false;
            }
        }
    }

    /**
     * Check if ClickHouse is connected
     */
    public function isConnected()
    {
        return $this->isConnected;
    }

    /**
     * Get connection details for debugging
     */
    public function getConnectionInfo()
    {
        return [
            'baseUrl' => $this->baseUrl ?? 'Not set',
            'host' => config('database.connections.clickhouse.host'),
            'port' => config('database.connections.clickhouse.port'),
            'database' => $this->database ?? 'Not set',
            'username' => $this->username ?? 'Not set',
            'isConnected' => $this->isConnected,
        ];
    }

    /**
     * Test connection with detailed error information
     */
    public function testConnection()
    {
        $info = [];
        $info['config'] = $this->getConnectionInfo();
        
        // Test 1: Check if host and port are set
        if (empty($this->baseUrl) || $this->baseUrl === '://:') {
            $info['error'] = 'Host or port is not configured';
            return $info;
        }
        
        // Test 2: Try to ping
        try {
            $this->ping();
            $info['ping'] = 'OK';
            $info['status'] = 'Connected';
        } catch (Exception $e) {
            $info['ping'] = 'FAILED';
            $info['ping_error'] = $e->getMessage();
            $info['status'] = 'Not Connected';
        }
        
        // Test 3: Try simple query
        if ($info['ping'] === 'OK') {
            try {
                $result = $this->query('SELECT 1 as test');
                $info['query'] = 'OK';
                $info['query_result'] = $result;
            } catch (Exception $e) {
                $info['query'] = 'FAILED';
                $info['query_error'] = $e->getMessage();
            }
        }
        
        return $info;
    }

    /**
     * Ping ClickHouse server
     */
    public function ping()
    {
        try {
            $pingUrl = $this->baseUrl . '/ping';
            Log::info('Attempting to ping ClickHouse: ' . $pingUrl);
            
            // Untuk HTTPS, mungkin perlu handle SSL verification
            $httpClient = Http::timeout(10);
            
            // Jika menggunakan HTTPS, disable SSL verification jika diperlukan (untuk development)
            // Untuk production, sebaiknya gunakan certificate yang valid
            if (strpos($this->baseUrl, 'https://') === 0) {
                // Disable SSL verification untuk development (karena error SSL version number biasanya berarti HTTP bukan HTTPS)
                $httpClient = $httpClient->withoutVerifying();
            }
            
            // Jika ada username, gunakan basic auth (bahkan jika password kosong)
            // ClickHouse menggunakan basic auth dengan username 'default' dan password kosong
            if (!empty($this->username)) {
                $httpClient = $httpClient->withBasicAuth($this->username, $this->password ?? '');
                Log::debug('Using basic auth with username: ' . $this->username);
            }
            
            $response = $httpClient->get($pingUrl);
            
            Log::info('ClickHouse ping response status: ' . $response->status());
            Log::info('ClickHouse ping response body: ' . $response->body());
            
            // ClickHouse ping bisa return "Ok", "Ok.", "Ok\n", "Ok.\n", dll
            // Cek jika status 200 dan body mengandung "Ok" (case insensitive)
            if ($response->successful()) {
                $body = trim($response->body());
                // Normalize: remove trailing dot and whitespace
                $normalizedBody = rtrim($body, '.');
                $normalizedBody = trim($normalizedBody);
                
                if (strcasecmp($normalizedBody, 'Ok') === 0) {
                    Log::info('ClickHouse ping successful');
                    return true;
                }
            }
            
            throw new Exception('ClickHouse ping failed. Status: ' . $response->status() . ', Body: ' . $response->body());
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $errorMsg = $e->getMessage();
            
            // Jika error SSL version number, kemungkinan server menggunakan HTTP bukan HTTPS
            if (strpos($errorMsg, 'wrong version number') !== false || strpos($errorMsg, 'SSL routines') !== false) {
                Log::warning('SSL error detected. Server might be using HTTP instead of HTTPS. Trying HTTP fallback...');
                // Coba dengan HTTP sebagai fallback
                try {
                    $httpUrl = str_replace('https://', 'http://', $this->baseUrl) . '/ping';
                    Log::info('Trying HTTP fallback: ' . $httpUrl);
                    
                    $httpClient = Http::timeout(10);
                    if (!empty($this->username)) {
                        $httpClient = $httpClient->withBasicAuth($this->username, $this->password ?? '');
                    }
                    
                    $response = $httpClient->get($httpUrl);
                    if ($response->successful() && trim($response->body()) === 'Ok') {
                        Log::warning('ClickHouse works with HTTP, not HTTPS. Please update CLICKHOUSE_PROTOCOL=http in .env');
                        throw new Exception('Server uses HTTP, not HTTPS. Please set CLICKHOUSE_PROTOCOL=http in .env file.');
                    }
                } catch (Exception $fallbackError) {
                    // Ignore fallback error, throw original
                }
                
                throw new Exception('SSL error: Server might be using HTTP instead of HTTPS. Please check CLICKHOUSE_PROTOCOL setting. Original error: ' . $errorMsg);
            }
            
            Log::error('ClickHouse connection timeout/network error: ' . $errorMsg);
            throw new Exception('Cannot connect to ClickHouse server. Check if server is running and accessible. Error: ' . $errorMsg);
        } catch (Exception $e) {
            Log::error('ClickHouse ping error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute a SELECT query
     */
    public function query(string $sql, array $params = [])
    {
        if (!$this->isConnected) {
            throw new Exception('ClickHouse is not connected. Please check configuration and server status.');
        }

        try {
            // Replace placeholders if params provided
            if (!empty($params)) {
                $sql = $this->bindParams($sql, $params);
            }

            // Set database dan format di URL query parameter
            // ClickHouse HTTP interface menggunakan format: ?database=xxx&default_format=JSON
            $url = $this->baseUrl . '/?database=' . urlencode($this->database) . '&default_format=JSON';
            
            Log::debug('ClickHouse query URL: ' . $url);
            Log::debug('ClickHouse query SQL: ' . substr($sql, 0, 200) . '...');
            
            // Setup HTTP client
            $httpClient = Http::timeout($this->timeout);
            
            // Jika menggunakan HTTPS, disable SSL verification (untuk development)
            // Error "wrong version number" biasanya berarti server menggunakan HTTP bukan HTTPS
            if (strpos($this->baseUrl, 'https://') === 0) {
                $httpClient = $httpClient->withoutVerifying();
            }
            
            // Add basic auth jika ada username (bahkan jika password kosong)
            // ClickHouse menggunakan basic auth dengan username 'default' dan password kosong
            if (!empty($this->username)) {
                $httpClient = $httpClient->withBasicAuth($this->username, $this->password ?? '');
                Log::debug('Using basic auth with username: ' . $this->username);
            }
            
            // ClickHouse HTTP interface menggunakan POST dengan query di body sebagai plain text
            // Format: POST dengan body berisi SQL query (bukan form data)
            $response = $httpClient
                ->withBody($sql, 'text/plain')
                ->post($url);

            Log::debug('ClickHouse response status: ' . $response->status());

            if (!$response->successful()) {
                $errorBody = $response->body();
                Log::error('ClickHouse query failed', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'sql' => substr($sql, 0, 200)
                ]);
                throw new Exception('ClickHouse query failed (Status: ' . $response->status() . '): ' . $errorBody);
            }

            $result = $response->json();
            
            // Parse ClickHouse JSON response
            if (isset($result['data'])) {
                return $result['data'];
            } elseif (isset($result[0])) {
                // If response is array of objects
                return $result;
            } else {
                // Try to parse as JSON lines format
                $lines = explode("\n", trim($response->body()));
                $data = [];
                foreach ($lines as $line) {
                    if (!empty(trim($line))) {
                        $decoded = json_decode($line, true);
                        if ($decoded !== null) {
                            $data[] = $decoded;
                        }
                    }
                }
                return $data;
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('ClickHouse connection timeout: ' . $e->getMessage());
            throw new Exception('Cannot connect to ClickHouse server. Check network connectivity and server status. Error: ' . $e->getMessage());
        } catch (Exception $e) {
            Log::error('ClickHouse query error: ' . $e->getMessage(), [
                'sql' => substr($sql, 0, 200),
                'params' => $params
            ]);
            throw $e;
        }
    }

    /**
     * Execute a raw query (for INSERT, CREATE, etc.)
     */
    public function rawQuery(string $sql, array $params = [])
    {
        if (!$this->isConnected) {
            throw new Exception('ClickHouse is not connected');
        }

        try {
            if (!empty($params)) {
                $sql = $this->bindParams($sql, $params);
            }

            // Set database di URL query parameter
            $url = $this->baseUrl . '/?database=' . urlencode($this->database);
            
            // Setup HTTP client
            $httpClient = Http::timeout($this->timeout);
            
            // Jika menggunakan HTTPS, disable SSL verification (untuk development)
            // Error "wrong version number" biasanya berarti server menggunakan HTTP bukan HTTPS
            if (strpos($this->baseUrl, 'https://') === 0) {
                $httpClient = $httpClient->withoutVerifying();
            }
            
            // Add basic auth jika ada username
            if (!empty($this->username)) {
                $httpClient = $httpClient->withBasicAuth($this->username, $this->password ?? '');
            }
            
            // ClickHouse HTTP interface menggunakan POST dengan query di body (bukan form data)
            $response = $httpClient
                ->withBody($sql, 'text/plain')
                ->post($url);

            if (!$response->successful()) {
                throw new Exception('ClickHouse raw query failed: ' . $response->body());
            }

            return $response->body();
        } catch (Exception $e) {
            Log::error('ClickHouse raw query error: ' . $e->getMessage(), [
                'sql' => $sql,
                'params' => $params
            ]);
            throw $e;
        }
    }

    /**
     * Insert data into table
     */
    public function insert(string $table, array $data)
    {
        if (!$this->isConnected) {
            throw new Exception('ClickHouse is not connected');
        }

        try {
            // Convert array to TSV format for ClickHouse
            $columns = array_keys($data);
            $values = array_values($data);
            
            $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES";
            
            // Format values for ClickHouse
            $formattedValues = [];
            foreach ($values as $value) {
                if (is_null($value)) {
                    $formattedValues[] = 'NULL';
                } elseif (is_string($value)) {
                    $formattedValues[] = "'" . addslashes($value) . "'";
                } elseif (is_bool($value)) {
                    $formattedValues[] = $value ? '1' : '0';
                } else {
                    $formattedValues[] = $value;
                }
            }
            
            $sql .= " (" . implode(', ', $formattedValues) . ")";
            
            return $this->rawQuery($sql);
        } catch (Exception $e) {
            Log::error('ClickHouse insert error: ' . $e->getMessage(), [
                'table' => $table,
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Insert batch data
     */
    public function insertBatch(string $table, array $dataArray)
    {
        if (!$this->isConnected) {
            throw new Exception('ClickHouse is not connected');
        }

        try {
            if (empty($dataArray)) {
                return true;
            }

            $columns = array_keys($dataArray[0]);
            $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ";
            
            $valueRows = [];
            foreach ($dataArray as $data) {
                $formattedValues = [];
                foreach ($columns as $col) {
                    $value = $data[$col] ?? null;
                    if (is_null($value)) {
                        $formattedValues[] = 'NULL';
                    } elseif (is_string($value)) {
                        $formattedValues[] = "'" . addslashes($value) . "'";
                    } elseif (is_bool($value)) {
                        $formattedValues[] = $value ? '1' : '0';
                    } else {
                        $formattedValues[] = $value;
                    }
                }
                $valueRows[] = "(" . implode(', ', $formattedValues) . ")";
            }
            
            $sql .= implode(', ', $valueRows);
            
            return $this->rawQuery($sql);
        } catch (Exception $e) {
            Log::error('ClickHouse batch insert error: ' . $e->getMessage(), [
                'table' => $table,
                'count' => count($dataArray)
            ]);
            throw $e;
        }
    }

    /**
     * Simple parameter binding (basic implementation)
     * Note: ClickHouse uses different syntax, this is a simple replacement
     */
    private function bindParams(string $sql, array $params)
    {
        // Simple placeholder replacement
        // For production, consider using proper parameter binding
        foreach ($params as $key => $value) {
            if (is_string($value)) {
                $value = "'" . addslashes($value) . "'";
            } elseif (is_null($value)) {
                $value = 'NULL';
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            
            // Replace ? or :param
            if (is_numeric($key)) {
                $sql = preg_replace('/\?/', $value, $sql, 1);
            } else {
                $sql = str_replace(':' . $key, $value, $sql);
            }
        }
        
        return $sql;
    }
}

