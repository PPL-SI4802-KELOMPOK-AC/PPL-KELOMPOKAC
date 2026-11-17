<?php

namespace App\Services\Map;

use Illuminate\Support\Facades\Cache;

class MapSnapshotCache
{
    private static function store()
    {
        return Cache::store(config('map.snapshot_cache_store', 'file'));
    }

    public static function invalidate(): void
    {
        self::store()->forever('map-snapshot:revision', bin2hex(random_bytes(16)));
    }

    public static function remember(callable $build, bool &$hit): array
    {
        return self::rememberData('map-snapshot:v1', [
            hash_file('sha256', config('map.boundary_path')),
            config('wilayah.kondisi_listrik'),
        ], $build, $hit);
    }

    public static function rememberSummary(callable $build): array
    {
        $hit = false;
        return self::rememberData('dashboard-summary:v1', [], $build, $hit);
    }

    private static function rememberData(string $namespace, array $dependencies, callable $build, bool &$hit): array
    {
        $cache = self::store();
        $revision = $cache->get('map-snapshot:revision', 'initial');
        $connection = config('database.default');
        $identity = [
            $revision, $connection, config("database.connections.$connection.host"),
            config("database.connections.$connection.database"),
            $dependencies,
        ];
        $key = $namespace.':'.hash('sha256', serialize($identity));
        $snapshot = $cache->get($key);
        $hit = $snapshot !== null;
        if (! $hit) {
            $snapshot = $build();
            // A concurrent invalidation changes the revision, so this old key
            // cannot overwrite the new snapshot. TTL also bounds external SQL edits.
            $cache->put($key, $snapshot, 300);
        }
        return $snapshot;
    }
}
