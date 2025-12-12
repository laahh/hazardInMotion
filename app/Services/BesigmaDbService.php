<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

class BesigmaDbService
{
    private $clickhouse;
    private $database = 'nitip';

    public function __construct()
    {
        $this->clickhouse = new ClickHouseService();
    }

    /**
     * Check if ClickHouse is connected
     */
    public function isConnected()
    {
        return $this->clickhouse->isConnected();
    }

    /**
     * Get unit GPS logs data from ClickHouse
     */
    public function getUnitGpsLogs()
    {
        try {
            // Check if ClickHouse is connected
            if (!$this->isConnected()) {
                Log::info('ClickHouse is not connected. Unit vehicle data will not be available.');
                return [];
            }
            
            // Try to get from unit_gps_latests first (latest data per unit)
            // Menggunakan kolom-kolom yang sesuai dengan struktur tabel nitip.unit_gps_latests
            try {
                $sql = "SELECT 
                    id,
                    unit_id,
                    integration_id,
                    latitude,
                    longitude,
                    course,
                    speed,
                    heading,
                    battery,
                    vehicle_type,
                    vehicle_number,
                    vehicle_name,
                    vendor_name,
                    vendor_type,
                    user_id,
                    is_unit,
                    timezone,
                    created_at,
                    updated_at
                FROM {$this->database}.unit_gps_latests
                WHERE latitude IS NOT NULL 
                    AND longitude IS NOT NULL 
                    AND latitude != 0 
                    AND longitude != 0
                    AND is_unit = true";
                
                $results = $this->queryWithDatabase($sql, $this->database);
                
                return $this->formatUnitData($results);
            } catch (Exception $e) {
                Log::info('unit_gps_latests table not found, trying unit_gps_logs: ' . $e->getMessage());
                
                // Fallback to unit_gps_logs with latest record per unit
                // Menggunakan kolom-kolom yang sesuai dengan struktur tabel nitip.unit_gps_logs
                $sql = "SELECT 
                    id,
                    unit_id,
                    integration_id,
                    latitude,
                    longitude,
                    course,
                    speed,
                    heading,
                    battery,
                    vehicle_type,
                    vehicle_number,
                    vehicle_name,
                    vendor_name,
                    vendor_type,
                    user_id,
                    is_unit,
                    timezone,
                    created_at,
                    updated_at
                FROM {$this->database}.unit_gps_logs
                WHERE latitude IS NOT NULL 
                    AND longitude IS NOT NULL 
                    AND latitude != 0 
                    AND longitude != 0
                    AND is_unit = true
                ORDER BY updated_at DESC
                LIMIT 1000";
                
                $allLogs = $this->queryWithDatabase($sql, $this->database);
                
                // Group by unit_id or integration_id and get latest for each
                $grouped = [];
                foreach ($allLogs as $log) {
                    $key = $log['unit_id'] ?? $log['integration_id'] ?? $log['id'] ?? null;
                    if ($key && !isset($grouped[$key])) {
                        $grouped[$key] = $log;
                    }
                }
                
                return $this->formatUnitData(array_values($grouped));
            }

        } catch (Exception $e) {
            Log::error('Error fetching unit GPS logs from ClickHouse: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get units data from ClickHouse (all units, no filter)
     */
    public function getUnits()
    {
        try {
            // Check if ClickHouse is connected
            if (!$this->isConnected()) {
                Log::info('ClickHouse is not connected. Unit vehicle data will not be available.');
                return [];
            }
            
            // Get ALL units from units table (no coordinate filter)
            // Menggunakan kolom-kolom yang sesuai dengan struktur tabel nitip.units
            $sql = "SELECT 
                id,
                integration_id,
                vendor_type,
                vendor_name,
                vehicle_type,
                vehicle_number,
                vehicle_name,
                last_latitude as latitude,
                last_longitude as longitude,
                last_course as course,
                last_battery as battery,
                timezone,
                created_at,
                updated_at
            FROM {$this->database}.units
            ORDER BY vehicle_name, vehicle_number";
            
            $results = $this->queryWithDatabase($sql, $this->database);
            
            return $this->formatUnitData($results);

        } catch (Exception $e) {
            Log::error('Error fetching units from ClickHouse: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Query ClickHouse with specific database (nitip)
     */
    private function queryWithDatabase($sql, $database)
    {
        // ClickHouse supports database.table syntax, so we can use it directly
        // But we need to ensure the database is set correctly in the URL
        // Since ClickHouseService uses database from config, we'll use database.table in SQL
        // which should work regardless of the default database setting
        return $this->clickhouse->query($sql);
    }

    /**
     * Format unit data to consistent structure
     */
    private function formatUnitData($results)
    {
        $formatted = [];
        foreach ($results as $item) {
            $formatted[] = [
                'id' => $item['id'] ?? null,
                'unit_id' => $item['unit_id'] ?? null,
                'integration_id' => $item['integration_id'] ?? null,
                'latitude' => isset($item['latitude']) && $item['latitude'] !== '' ? (float) $item['latitude'] : 0,
                'longitude' => isset($item['longitude']) && $item['longitude'] !== '' ? (float) $item['longitude'] : 0,
                'course' => isset($item['course']) && $item['course'] !== '' ? (float) $item['course'] : 0,
                'speed' => isset($item['speed']) && $item['speed'] !== '' && $item['speed'] !== null ? (float) $item['speed'] : null,
                'heading' => isset($item['heading']) && $item['heading'] !== '' && $item['heading'] !== null ? (float) $item['heading'] : null,
                'battery' => isset($item['battery']) && $item['battery'] !== '' ? (float) $item['battery'] : 0,
                'vehicle_type' => $item['vehicle_type'] ?? 'Unknown',
                'vehicle_number' => $item['vehicle_number'] ?? 'N/A',
                'vehicle_name' => $item['vehicle_name'] ?? 'N/A',
                'vendor_name' => $item['vendor_name'] ?? 'N/A',
                'vendor_type' => $item['vendor_type'] ?? null,
                'user_id' => $item['user_id'] ?? null,
                'is_unit' => isset($item['is_unit']) ? (bool) $item['is_unit'] : true,
                'timezone' => $item['timezone'] ?? null,
                'created_at' => $item['created_at'] ?? null,
                'updated_at' => $item['updated_at'] ?? null,
            ];
        }
        return $formatted;
    }

    /**
     * Get latest GPS data per unit from unit_gps_logs table
     * Returns the most recent GPS log for each unit
     */
    public function getLatestUnitGpsLogs()
    {
        try {
            // Check if ClickHouse is connected
            if (!$this->isConnected()) {
                Log::info('ClickHouse is not connected. Unit GPS logs will not be available.');
                return [];
            }
            
            // Try to use unit_gps_latests first if available (optimized table)
            // Menggunakan kolom-kolom yang sesuai dengan struktur tabel nitip.unit_gps_latests
            try {
                $sql = "SELECT 
                    id,
                    unit_id,
                    integration_id,
                    latitude,
                    longitude,
                    course,
                    speed,
                    heading,
                    battery,
                    vehicle_type,
                    vehicle_number,
                    vehicle_name,
                    vendor_name,
                    vendor_type,
                    user_id,
                    is_unit,
                    timezone,
                    created_at,
                    updated_at
                FROM {$this->database}.unit_gps_latests
                WHERE latitude IS NOT NULL 
                    AND longitude IS NOT NULL 
                    AND latitude != 0 
                    AND longitude != 0
                    AND is_unit = true";
                
                $results = $this->queryWithDatabase($sql, $this->database);
                return $this->formatUnitData($results);
            } catch (Exception $e) {
                Log::info('unit_gps_latests table not available, using unit_gps_logs with latest per unit: ' . $e->getMessage());
                
                // Fallback: Get latest GPS log per unit from unit_gps_logs
                // Use nested subquery to create partition key first, then use it in ROW_NUMBER
                // Menggunakan kolom-kolom yang sesuai dengan struktur tabel nitip.unit_gps_logs
                $sql = "SELECT 
                    id,
                    unit_id,
                    integration_id,
                    latitude,
                    longitude,
                    course,
                    speed,
                    heading,
                    battery,
                    vehicle_type,
                    vehicle_number,
                    vehicle_name,
                    vendor_name,
                    vendor_type,
                    user_id,
                    is_unit,
                    timezone,
                    created_at,
                    updated_at
                FROM (
                    SELECT 
                        id,
                        unit_id,
                        integration_id,
                        latitude,
                        longitude,
                        course,
                        speed,
                        heading,
                        battery,
                        vehicle_type,
                        vehicle_number,
                        vehicle_name,
                        vendor_name,
                        vendor_type,
                        user_id,
                        is_unit,
                        timezone,
                        created_at,
                        updated_at,
                        partition_key,
                        ROW_NUMBER() OVER (
                            PARTITION BY partition_key
                            ORDER BY updated_at DESC
                        ) as rn
                    FROM (
                        SELECT 
                            id,
                            unit_id,
                            integration_id,
                            latitude,
                            longitude,
                            course,
                            speed,
                            heading,
                            battery,
                            vehicle_type,
                            vehicle_number,
                            vehicle_name,
                            vendor_name,
                            vendor_type,
                            user_id,
                            is_unit,
                            timezone,
                            created_at,
                            updated_at,
                            -- Create partition key as String to ensure type consistency
                            if(integration_id IS NOT NULL AND length(toString(integration_id)) > 0, 
                                toString(integration_id), 
                                toString(id)
                            ) as partition_key
                        FROM {$this->database}.unit_gps_logs
                        WHERE latitude IS NOT NULL 
                            AND longitude IS NOT NULL 
                            AND latitude != 0 
                            AND longitude != 0
                            AND (integration_id IS NOT NULL OR id IS NOT NULL)
                            AND is_unit = true
                    ) with_key
                ) ranked
                WHERE rn = 1";
                
                $results = $this->queryWithDatabase($sql, $this->database);
                return $this->formatUnitData($results);
            }

        } catch (Exception $e) {
            Log::error('Error fetching latest unit GPS logs from ClickHouse: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get unit GPS logs for movement tracking (from unit_gps_logs table)
     * Can filter by unit_id and limit results for history tracking
     */
    public function getUnitGpsLogsForTracking($unitId = null, $limit = 1000)
    {
        try {
            // Check if ClickHouse is connected
            if (!$this->isConnected()) {
                Log::info('ClickHouse is not connected. Unit GPS logs will not be available.');
                return [];
            }
            
            $whereClause = "WHERE latitude IS NOT NULL 
                AND longitude IS NOT NULL 
                AND latitude != 0 
                AND longitude != 0
                AND is_unit = true";
            
            if ($unitId) {
                $whereClause .= " AND (unit_id = '{$unitId}' OR integration_id = '{$unitId}')";
            }
            
            $sql = "SELECT 
                id,
                unit_id,
                integration_id,
                latitude,
                longitude,
                course,
                speed,
                heading,
                battery,
                vehicle_type,
                vehicle_number,
                vehicle_name,
                vendor_name,
                vendor_type,
                user_id,
                is_unit,
                timezone,
                updated_at,
                created_at
            FROM {$this->database}.unit_gps_logs
            {$whereClause}
            ORDER BY updated_at DESC
            LIMIT {$limit}";
            
            $results = $this->queryWithDatabase($sql, $this->database);
            
            return $this->formatUnitData($results);

        } catch (Exception $e) {
            Log::error('Error fetching unit GPS logs from ClickHouse: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get combined unit data (use units as base, merge with latest GPS logs for real-time tracking)
     * This method returns all units from units table, with GPS data from unit_gps_logs if available
     */
    public function getCombinedUnitData()
    {
        // Check if ClickHouse is connected first
        if (!$this->isConnected()) {
            // Use info level and log only once here to avoid duplicate messages
            Log::info('ClickHouse is not connected. Unit vehicle tracking is disabled. Please check ClickHouse configuration.');
            return [];
        }
        
        // Get ALL units from units table (this is the BASE - shows all units)
        $units = $this->getUnits();
        
        // Get latest GPS data from unit_gps_logs (or unit_gps_latests if available)
        // This provides real-time tracking data (position, speed, battery, updated_at)
        $gpsLogs = $this->getLatestUnitGpsLogs();
        Log::info('GPS logs fetched', ['count' => count($gpsLogs)]);
        
        // Get users data for integration - only fetch users that exist in GPS logs to avoid timeout
        $userIds = [];
        foreach ($gpsLogs as $gpsLog) {
            if (!empty($gpsLog['user_id'])) {
                $userIds[] = $gpsLog['user_id'];
            }
        }
        $users = !empty($userIds) ? $this->getUsersByIds($userIds) : [];
        
        // Create a map of users by id
        $usersMap = [];
        foreach ($users as $user) {
            if (!empty($user['id'])) {
                $usersMap[$user['id']] = $user;
            }
        }
        
        // Create a map of GPS data by multiple keys for flexible matching
        $gpsMap = [];
        foreach ($gpsLogs as $gpsLog) {
            // Create multiple keys for flexible matching
            $keys = [];
            if (!empty($gpsLog['integration_id'])) {
                $keys[] = 'integration_id:' . $gpsLog['integration_id'];
            }
            if (!empty($gpsLog['unit_id'])) {
                $keys[] = 'unit_id:' . $gpsLog['unit_id'];
            }
            if (!empty($gpsLog['id'])) {
                $keys[] = 'id:' . $gpsLog['id'];
            }
            // Also match by vehicle_number if available
            if (!empty($gpsLog['vehicle_number'])) {
                $keys[] = 'vehicle_number:' . $gpsLog['vehicle_number'];
            }
            
            // Store GPS data with all possible keys
            foreach ($keys as $key) {
                if (!isset($gpsMap[$key])) {
                    $gpsMap[$key] = $gpsLog;
                }
            }
        }

        // Start with units as base, then merge with GPS data if available
        $combined = [];
        foreach ($units as $unit) {
            // Try to find matching GPS data using multiple keys
            $gpsData = null;
            
            // Try matching by integration_id
            if (!empty($unit['integration_id'])) {
                $key = 'integration_id:' . $unit['integration_id'];
                if (isset($gpsMap[$key])) {
                    $gpsData = $gpsMap[$key];
                }
            }
            
            // Try matching by id if no match yet
            if (!$gpsData && !empty($unit['id'])) {
                $key = 'id:' . $unit['id'];
                if (isset($gpsMap[$key])) {
                    $gpsData = $gpsMap[$key];
                }
            }
            
            // Try matching by vehicle_number if no match yet
            if (!$gpsData && !empty($unit['vehicle_number'])) {
                $key = 'vehicle_number:' . $unit['vehicle_number'];
                if (isset($gpsMap[$key])) {
                    $gpsData = $gpsMap[$key];
                }
            }
            
            // Get user data if user_id is available in GPS data
            $userData = null;
            if (!empty($gpsData['user_id']) && isset($usersMap[$gpsData['user_id']])) {
                $userData = $usersMap[$gpsData['user_id']];
            }
            
            // Determine which data source to use for coordinates
            // Priority: GPS data (real-time) > Unit data (master)
            $finalLatitude = $gpsData['latitude'] ?? $unit['latitude'] ?? 0;
            $finalLongitude = $gpsData['longitude'] ?? $unit['longitude'] ?? 0;
            $finalUpdatedAt = $gpsData['updated_at'] ?? $unit['updated_at'] ?? null;
            
            // Log if GPS data is found for debugging
            if ($gpsData && !empty($gpsData['updated_at'])) {
                Log::debug('GPS data found for unit', [
                    'vehicle_number' => $unit['vehicle_number'] ?? 'N/A',
                    'gps_updated_at' => $gpsData['updated_at'],
                    'unit_updated_at' => $unit['updated_at'] ?? null,
                    'latitude' => $finalLatitude,
                    'longitude' => $finalLongitude
                ]);
            }
            
            // Build combined data: use GPS data if available, otherwise use unit data
            $combined[] = [
                'id' => $unit['id'],
                'unit_id' => $gpsData['unit_id'] ?? null,
                'integration_id' => $unit['integration_id'],
                // Use GPS data for position/speed/battery if available, otherwise use unit data
                'latitude' => $finalLatitude,
                'longitude' => $finalLongitude,
                'course' => $gpsData['course'] ?? $unit['course'] ?? 0,
                'speed' => $gpsData['speed'] ?? $unit['speed'] ?? null,
                'heading' => $gpsData['heading'] ?? null,
                'battery' => $gpsData['battery'] ?? $unit['battery'] ?? 0,
                // Use unit data for vehicle info (more reliable), but GPS data can override
                'vehicle_type' => $gpsData['vehicle_type'] ?? $unit['vehicle_type'] ?? 'Unknown',
                'vehicle_number' => $unit['vehicle_number'] ?? $gpsData['vehicle_number'] ?? 'N/A',
                'vehicle_name' => $unit['vehicle_name'] ?? $gpsData['vehicle_name'] ?? 'N/A',
                'vendor_name' => $unit['vendor_name'] ?? $gpsData['vendor_name'] ?? 'N/A',
                'vendor_type' => $unit['vendor_type'] ?? $gpsData['vendor_type'] ?? null,
                'timezone' => $gpsData['timezone'] ?? $unit['timezone'] ?? null,
                // Use GPS updated_at if available (real-time tracking), otherwise use unit updated_at
                'updated_at' => $finalUpdatedAt,
                // User data from users table
                'user_id' => $gpsData['user_id'] ?? null,
                'user' => $userData ? [
                    'id' => $userData['id'],
                    'npk' => $userData['npk'],
                    'fullname' => $userData['fullname'],
                    'sid_code' => $userData['sid_code'],
                    'email' => $userData['email'],
                    'phone' => $userData['phone'],
                    'employee_id' => $userData['employee_id'],
                    'functional_position' => $userData['functional_position'],
                    'structural_position' => $userData['structural_position'],
                    'department_name' => $userData['department_name'],
                    'division_name' => $userData['division_name'],
                    'site_assignment' => $userData['site_assignment'],
                    'dedicated_site' => $userData['dedicated_site'],
                ] : null,
            ];
        }

        // Log summary for debugging
        $gpsMatchedCount = 0;
        foreach ($combined as $item) {
            if (!empty($item['updated_at']) && 
                (!empty($item['latitude']) && $item['latitude'] != 0) &&
                (!empty($item['longitude']) && $item['longitude'] != 0)) {
                $gpsMatchedCount++;
            }
        }
        
        Log::info('Combined unit data prepared', [
            'total_units' => count($combined),
            'units_with_gps' => $gpsMatchedCount,
            'gps_logs_count' => count($gpsLogs)
        ]);

        return $combined;
    }

    /**
     * Get users data from ClickHouse by IDs (optimized to avoid timeout)
     */
    public function getUsersByIds(array $userIds)
    {
        try {
            // Check if ClickHouse is connected
            if (!$this->isConnected()) {
                Log::info('ClickHouse is not connected. Users data will not be available.');
                return [];
            }
            
            if (empty($userIds)) {
                return [];
            }
            
            // Remove duplicates and prepare IDs for query
            $uniqueIds = array_unique($userIds);
            $idsList = implode(',', array_map(function($id) {
                return "'" . addslashes($id) . "'";
            }, $uniqueIds));
            
            $sql = "SELECT 
                id,
                npk,
                fullname,
                sid_code,
                email,
                phone,
                employee_id,
                functional_position,
                structural_position,
                department_name,
                division_name,
                site_assignment,
                dedicated_site,
                company_id
            FROM {$this->database}.users
            WHERE id IN ({$idsList})
                AND is_active = true
                AND is_deleted = false";
            
            $results = $this->queryWithDatabase($sql, $this->database);
            
            $formatted = [];
            foreach ($results as $item) {
                $formatted[] = [
                    'id' => $item['id'] ?? null,
                    'npk' => $item['npk'] ?? null,
                    'fullname' => $item['fullname'] ?? 'N/A',
                    'sid_code' => $item['sid_code'] ?? null,
                    'email' => $item['email'] ?? null,
                    'phone' => $item['phone'] ?? null,
                    'employee_id' => $item['employee_id'] ?? null,
                    'functional_position' => $item['functional_position'] ?? null,
                    'structural_position' => $item['structural_position'] ?? null,
                    'department_name' => $item['department_name'] ?? null,
                    'division_name' => $item['division_name'] ?? null,
                    'site_assignment' => $item['site_assignment'] ?? null,
                    'dedicated_site' => $item['dedicated_site'] ?? null,
                    'company_id' => $item['company_id'] ?? null,
                ];
            }
            
            return $formatted;
        } catch (Exception $e) {
            Log::error('Error fetching users from ClickHouse: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all users data from ClickHouse (use with caution - may timeout)
     */
    public function getUsers()
    {
        try {
            // Check if ClickHouse is connected
            if (!$this->isConnected()) {
                Log::info('ClickHouse is not connected. Users data will not be available.');
                return [];
            }
            
            $sql = "SELECT 
                id,
                npk,
                fullname,
                sid_code,
                email,
                phone,
                employee_id,
                functional_position,
                structural_position,
                department_name,
                division_name,
                site_assignment,
                dedicated_site,
                company_id,
                is_active
            FROM {$this->database}.users
            WHERE is_active = true
                AND is_deleted = false
            LIMIT 10000";
            
            $results = $this->queryWithDatabase($sql, $this->database);
            
            $formatted = [];
            foreach ($results as $item) {
                $formatted[] = [
                    'id' => $item['id'] ?? null,
                    'npk' => $item['npk'] ?? null,
                    'fullname' => $item['fullname'] ?? 'N/A',
                    'sid_code' => $item['sid_code'] ?? null,
                    'email' => $item['email'] ?? null,
                    'phone' => $item['phone'] ?? null,
                    'employee_id' => $item['employee_id'] ?? null,
                    'functional_position' => $item['functional_position'] ?? null,
                    'structural_position' => $item['structural_position'] ?? null,
                    'department_name' => $item['department_name'] ?? null,
                    'division_name' => $item['division_name'] ?? null,
                    'site_assignment' => $item['site_assignment'] ?? null,
                    'dedicated_site' => $item['dedicated_site'] ?? null,
                    'company_id' => $item['company_id'] ?? null,
                ];
            }
            
            return $formatted;
        } catch (Exception $e) {
            Log::error('Error fetching users from ClickHouse: ' . $e->getMessage());
            return [];
        }
    }
}

