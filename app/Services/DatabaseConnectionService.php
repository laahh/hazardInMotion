<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Simple Database Connection Service
 * 
 * This service provides a simpler way to connect to PostgreSQL.
 * Use manual SSH tunnel setup as it's more reliable on Windows.
 */
class DatabaseConnectionService
{
    /**
     * Check if SSH tunnel is active (port is listening)
     */
    public function isTunnelActive()
    {
        $localPort = config('database.connections.pgsql_ssh.local_port', 5433);
        $connection = @fsockopen('127.0.0.1', $localPort, $errno, $errstr, 1);
        if ($connection) {
            fclose($connection);
            return true;
        }
        return false;
    }

    /**
     * Test database connection
     */
    public function testConnection()
    {
        try {
            if (!$this->isTunnelActive()) {
                throw new Exception('SSH tunnel is not active. Please start the tunnel first.');
            }

            DB::connection('pgsql_ssh')->select('SELECT 1');
            return true;
        } catch (Exception $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Get tunnel status info
     */
    public function getTunnelStatus()
    {
        $localPort = config('database.connections.pgsql_ssh.local_port', 5433);
        $isActive = $this->isTunnelActive();
        
        return [
            'is_active' => $isActive,
            'local_port' => $localPort,
            'status' => $isActive ? 'Connected' : 'Not Connected',
            'message' => $isActive 
                ? 'SSH tunnel is active. You can now access the database.' 
                : 'SSH tunnel is not active. Please start the tunnel first.',
        ];
    }
}

