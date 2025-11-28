<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class CarRegisterController extends Controller
{
    /**
     * Display data from car_register table
     * READ ONLY - Only SELECT queries are used
     */
    public function index()
    {
        try {
            // Check if SSH tunnel is active
            if (!$this->isTunnelActive()) {
                throw new Exception('SSH tunnel is not active. Please start the tunnel manually first. You can use the setup-ssh-tunnel.bat or setup-ssh-tunnel.ps1 script.');
            }
            
            // Try to find table in multiple schemas: bcbeats, datamart, public
            $schema = null;
            $tableName = 'car_register';
            $schemasToCheck = ['bcbeats', 'datamart', 'public'];
            
            foreach ($schemasToCheck as $checkSchema) {
                $exists = DB::connection('pgsql_ssh')->select("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = ? 
                        AND table_name = ?
                    )
                ", [$checkSchema, $tableName]);
                
                if ($exists[0]->exists) {
                    $schema = $checkSchema;
                    break;
                }
            }
            
            if (!$schema) {
                throw new Exception('Table car_register not found in schemas: ' . implode(', ', $schemasToCheck));
            }
            
            // Get table data
            $qualified = $schema . '.' . $tableName;
            $data = DB::connection('pgsql_ssh')->table($qualified)->get();
            
            // Get column names
            $columns = [];
            if (count($data) > 0) {
                $columns = array_keys((array)$data[0]);
            } else {
                // If no data, get columns from schema
                $columnsInfo = DB::connection('pgsql_ssh')->select("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_name = ? AND table_schema = ?
                    ORDER BY ordinal_position
                ", [$tableName, $schema]);
                
                $columns = array_map(function($col) {
                    return $col->column_name;
                }, $columnsInfo);
            }
            
            $displayName = $qualified;
            return view('car-register.index', [
                'tableName' => $displayName,
                'data' => $data,
                'columns' => $columns,
                'totalRows' => count($data)
            ]);
            
        } catch (Exception $e) {
            return view('car-register.error', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Check if SSH tunnel port is active
     */
    private function isTunnelActive()
    {
        $localPort = config('database.connections.pgsql_ssh.local_port', 5433);
        $connection = @fsockopen('127.0.0.1', $localPort, $errno, $errstr, 1);
        if ($connection) {
            fclose($connection);
            return true;
        }
        return false;
    }
}

