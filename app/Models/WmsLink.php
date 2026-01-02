<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsLink extends Model
{
    use HasFactory;

    protected $table = 'wms_links';

    protected $fillable = [
        'location_name',
        'wms_link',
        'week',
        'year',
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
     * Scope to get links for current week and year
     */
    public function scopeCurrentWeek($query)
    {
        return $query->where('year', self::getCurrentYear())
                    ->where('week', self::getCurrentWeek());
    }

    /**
     * Scope to get links by year
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to get links by week
     */
    public function scopeByWeek($query, $week)
    {
        return $query->where('week', $week);
    }
}

