<?php

namespace App\Cache;

interface CacheInterface
{
	public function get(string $key): ?string;
	public function set(string $key, string $value, int $ttl = 0): void;
	public function delete(string $key): void;
}
