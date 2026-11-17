<?php

namespace App\Services\Map;

use Illuminate\Contracts\Cache\Repository;

class CachedGeometry
{
    private array $previous;
    private array $current = [];
    public int $hits = 0;
    public int $misses = 0;
    private string $key;

    public function __construct(private Repository $cache, private array $geometry)
    {
        $this->key = 'map-coordinates:v1:'.hash('sha256', serialize($geometry));
        $this->previous = $cache->get($this->key, []);
    }

    public function contains(array $point): bool
    {
        $key = hash('sha256', serialize($point));
        if (array_key_exists($key, $this->current)) {
            $this->hits++;
            return $this->current[$key];
        }
        if (array_key_exists($key, $this->previous)) {
            $this->hits++;
            return $this->current[$key] = $this->previous[$key];
        }
        $this->misses++;
        return $this->current[$key] = Geometry::contains($this->geometry, $point);
    }

    public function save(): void
    {
        // Retain only coordinates used by this request, limiting cache growth.
        if ($this->current !== $this->previous) {
            $this->cache->put($this->key, $this->current, 86400);
        }
    }
}