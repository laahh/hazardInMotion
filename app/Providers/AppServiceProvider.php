<?php

namespace App\Providers;

use App\Database\Connectors\DwhRedshiftConnector;
use App\Models\CctvData;
use Illuminate\Database\Connection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register ChatbotRuleService as singleton
        $this->app->singleton(\App\Services\ChatbotRuleService::class);
        $this->app->bind(
            \App\Services\Dms\DmsDashboardDataSource::class,
            \App\Services\DmsMonitoring\DmsAlertMonitoringDataReader::class
        );

        $this->app->bind('db.connector.redshift', DwhRedshiftConnector::class);
        Connection::resolverFor('redshift', function ($connection, $database, $prefix, $config) {
            return new PostgresConnection($connection, $database, $prefix, $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureOpenSslCaBundle();

        // libpq (PHP 8+) mencoba GSSAPI dulu; Redshift tidak support → timeout.
        putenv('PGGSSENCMODE=disable');

        // 🔑 Force HTTPS for asset URLs in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Schema::defaultStringLength(191);
        
        // Share control room data to sidebar
        View::composer('layouts.sidebarWmsAdmin', function ($view) {
            $controlRooms = CctvData::select('control_room')
                ->whereNotNull('control_room')
                ->where('control_room', '!=', '')
                ->distinct()
                ->orderBy('control_room')
                ->get()
                ->map(function ($item) {
                    $controlRoom = $item->control_room;
                    $cctvList = CctvData::where('control_room', $controlRoom)
                        ->orderBy('nama_cctv')
                        ->get(['id', 'no_cctv', 'nama_cctv', 'lokasi_pemasangan', 'status', 'kondisi', 'link_akses']);
                    
                    return [
                        'name' => $controlRoom,
                        'cctv_count' => $cctvList->count(),
                        'cctv_list' => $cctvList->map(function ($cctv) {
                            return [
                                'id' => $cctv->id,
                                'no_cctv' => $cctv->no_cctv,
                                'nama_cctv' => $cctv->nama_cctv,
                                'lokasi_pemasangan' => $cctv->lokasi_pemasangan,
                                'status' => $cctv->status,
                                'kondisi' => $cctv->kondisi,
                                'link_akses' => $cctv->link_akses,
                            ];
                        })->toArray(),
                    ];
                });
            
            $view->with('controlRooms', $controlRooms);
        });
    }

    /**
     * Pastikan OpenSSL punya CA bundle (sering kosong di Laragon Windows),
     * supaya STARTTLS SMTP tidak gagal "certificate verify failed".
     */
    private function ensureOpenSslCaBundle(): void
    {
        if (ini_get('openssl.cafile')) {
            return;
        }

        $candidates = [
            'C:\\laragon\\etc\\ssl\\cacert.pem',
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'cacert.pem',
        ];

        foreach ($candidates as $caFile) {
            if (is_file($caFile)) {
                ini_set('openssl.cafile', $caFile);
                break;
            }
        }
    }
}