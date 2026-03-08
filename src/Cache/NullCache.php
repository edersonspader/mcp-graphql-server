<?php

namespace App\Cache;

/**
 * No-op cache — useful for tests and for forcing a fresh fetch every time.
 * Set CACHE_ENABLED=false to use this in production.
 */
class NullCache implements CacheInterface
{
	public function get(string $key): ?string
	{
		return null;
	}
	public function set(string $key, string $value, int $ttl = 0): void {}
	public function delete(string $key): void {}
}
