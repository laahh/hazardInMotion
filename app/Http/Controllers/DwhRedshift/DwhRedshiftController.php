<?php

declare(strict_types=1);

namespace App\Http\Controllers\DwhRedshift;

use App\Http\Controllers\Controller;
use App\Services\DwhRedshift\DwhRedshiftQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * Endpoint JSON read-only untuk Amazon Redshift DWH.
 */
final class DwhRedshiftController extends Controller
{
    public function __construct(
        private readonly DwhRedshiftQueryService $dwhRedshiftQueryService,
    ) {
    }

    /**
     * Status koneksi + daftar schema/tabel sebagai JSON.
     */
    public function index(): JsonResponse
    {
        $ping = $this->dwhRedshiftQueryService->ping();

        if (! $ping['connected']) {
            return response()->json([
                'connected' => false,
                'tcp_reachable' => $ping['tcp_reachable'],
                'host' => config('database.connections.redshift.host'),
                'port' => (int) config('database.connections.redshift.port'),
                'database' => config('database.connections.redshift.database'),
                'username' => config('database.connections.redshift.username'),
                'error' => $ping['error'],
            ], 503);
        }

        try {
            $schemas = $this->dwhRedshiftQueryService->listSchemas();
            $tables = $this->dwhRedshiftQueryService->listTables();
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'connected' => true,
                'database' => $ping['database'],
                'username' => $ping['username'],
                'version' => $ping['version'],
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'connected' => true,
            'host' => config('database.connections.redshift.host'),
            'port' => (int) config('database.connections.redshift.port'),
            'database' => $ping['database'],
            'username' => $ping['username'],
            'version' => $ping['version'],
            'schema_count' => count($schemas),
            'table_count' => count($tables),
            'schemas' => $schemas,
            'tables' => $tables,
            'preview_url' => url('/dwh-redshift/preview?schema=public&table=NAMA_TABEL&limit=50'),
        ]);
    }

    /**
     * Preview isi satu tabel (maks 100 baris) sebagai JSON.
     */
    public function preview(Request $request): JsonResponse
    {
        $schema = trim((string) $request->query('schema', 'public'));
        $table = trim((string) $request->query('table', ''));
        $limit = (int) $request->query('limit', DwhRedshiftQueryService::DEFAULT_PREVIEW_LIMIT);

        if ($table === '') {
            return response()->json([
                'error' => 'Parameter table wajib diisi.',
                'example' => url('/dwh-redshift/preview?schema=public&table=NAMA_TABEL&limit=50'),
            ], 422);
        }

        try {
            $rows = $this->dwhRedshiftQueryService->previewTable($schema, $table, $limit);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => $e->getMessage(),
                'schema' => $schema,
                'table' => $table,
            ], 500);
        }

        return response()->json([
            'schema' => $schema,
            'table' => $table,
            'limit' => max(1, min($limit, DwhRedshiftQueryService::MAX_PREVIEW_LIMIT)),
            'row_count' => count($rows),
            'data' => $rows,
        ]);
    }
}
