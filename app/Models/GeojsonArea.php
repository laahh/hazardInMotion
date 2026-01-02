<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeojsonArea extends Model
{
    use HasFactory;

    protected $table = 'geojson_areas';

    protected $fillable = [
        'name',
        'type',
        'geojson_data',
        'file_name',
        'week',
        'year',
        'description',
    ];

    protected $casts = [
        'geojson_data' => 'array',
    ];

    /**
     * Get current week number of the year
     */
    public static function getCurrentWeek()
    {
        return (int) date('W');
    }

    /**
     * Get current year
     */
    public static function getCurrentYear()
    {
        return (int) date('Y');
    }

    /**
     * Scope to get areas by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get areas for current week and year
     */
    public function scopeCurrentWeek($query)
    {
        return $query->where('year', self::getCurrentYear())
                    ->where('week', self::getCurrentWeek());
    }

    /**
     * Scope to get areas by year
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to get areas by week
     */
    public function scopeByWeek($query, $week)
    {
        return $query->where('week', $week);
    }
}

