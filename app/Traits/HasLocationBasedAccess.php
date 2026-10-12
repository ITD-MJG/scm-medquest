<?php

namespace App\Traits;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasLocationBasedAccess
{
    /**
     * Apply location-based filtering to the query
     */
    public static function applyLocationFilter(Builder $query): Builder
    {
        $user = Auth::user();

        // Super Admin, Admin, Supply Chain Manager, QA Manager, QA Supervisor and QA Staff can see all records
        if ($user->hasRole(['Super Admin', 'Admin', 'Supply Chain Manager', 'QA Manager', 'QA Supervisor', 'QA Staff'])) {
            return $query;
        }

        // Regular users can only see records from their assigned location
        if ($user->location_id) {
            return $query->where('location_id', $user->location_id);
        }

        // Users without assigned location see nothing
        return $query->whereRaw('1 = 0');
    }

    /**
     * Get accessible location IDs restricted to Bizpark 1 & 2
     * (used by temperature humidity/deviation resources)
     */
    public static function getAccessibleLocationIdsForTemperature(): array
    {
        $accessible = static::getAccessibleLocationIds();
        if (empty($accessible)) {
            return [];
        }

        $bizparkIds = Location::whereIn('location_code', ['BP1', 'BP2'])->pluck('id')->toArray();

        return array_values(array_intersect($accessible, $bizparkIds));
    }

    /**
     * Get accessible location IDs for the current user
     */
    public static function getAccessibleLocationIds(): array
    {
        $user = Auth::user();

        // Super Admin, Admin, Supply Chain Manager, QA Manager, QA Supervisor, and QA Staff can access all locations
        if ($user->hasRole(['Super Admin', 'Admin', 'Supply Chain Manager', 'QA Manager', 'QA Supervisor', 'QA Staff'])) {
            return Location::pluck('id')->toArray();
        }

        // Regular users can only access their assigned location
        return $user->location_id ? [$user->location_id] : [];
    }

    /**
     * Check if user can access a specific location
     */
    public static function canAccessLocation(int $locationId): bool
    {
        return in_array($locationId, static::getAccessibleLocationIds());
    }
}
