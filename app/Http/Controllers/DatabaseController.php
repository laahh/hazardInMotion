<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * DatabaseController - READ ONLY MODE
 * 
 * WARNING: This controller only performs SELECT queries.
 * No write operations (INSERT, UPDATE, DELETE, CREATE, DROP, etc.) are allowed.
 * Requires manual SSH tunnel setup before use.
 */
class DatabaseController extends Controller
{

    /**
     * Display all tables and their data
     * READ ONLY - Only SELECT queries are used
     */
    public function index()
    {
        try {
            // Check if SSH tunnel is active (don't try to create it automatically)
            if (!$this->isTunnelActive()) {
                throw new Exception('SSH tunnel is not active. Please start the tunnel manually first.');
            }
            
            // Get all tables from the database (public and datamart schemas)
            $tables = $this->getAllTables();
            
            // Get data from each table
            $tablesData = [];
            foreach ($tables as $table) {
                try {
                    $qualified = $table['schema'].'.'.$table['name'];
                    $data = DB::connection('pgsql_ssh')->table($qualified)->get();
                    $tablesData[$qualified] = $data;
                } catch (Exception $e) {
                    $tablesData[$qualified] = [
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return view('database.index', compact('tables', 'tablesData'));
            
        } catch (Exception $e) {
            return view('database.error', ['error' => $e->getMessage()]);
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

    /**
     * Get all table names from database
     */
    private function getAllTables()
    {
        try {
            $tables = DB::connection('pgsql_ssh')->select("
                SELECT table_schema, table_name 
                FROM information_schema.tables 
                WHERE table_type = 'BASE TABLE'
                  AND table_schema NOT IN ('pg_catalog', 'information_schema')
                ORDER BY table_schema, table_name
            ");
            
            return array_map(function($t) {
                return [
                    'schema' => $t->table_schema,
                    'name' => $t->table_name,
                ];
            }, $tables);
        } catch (Exception $e) {
            throw new Exception("Failed to fetch tables: " . $e->getMessage());
        }
    }

    /**
     * Display data for a specific table
     * READ ONLY - Only SELECT queries are used
     */
    public function showTable($schema, $tableName)
    {
        try {
            // Check if SSH tunnel is active
            if (!$this->isTunnelActive()) {
                throw new Exception('SSH tunnel is not active. Please start the tunnel manually first.');
            }
            
            // Get table data
            $qualified = $schema.'.'.$tableName;
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
            return view('database.table', ['tableName' => $displayName, 'data' => $data, 'columns' => $columns]);
            
        } catch (Exception $e) {
            return view('database.error', ['error' => $e->getMessage()]);
        }
    }
}

