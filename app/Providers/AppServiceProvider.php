<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use App\Models\CctvData;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register ChatbotRuleService as singleton
        $this->app->singleton(\App\Services\ChatbotRuleService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
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
}
