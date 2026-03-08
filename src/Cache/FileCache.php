<?php

namespace App\Cache;

/**
 * File-based cache.
 * Each entry is stored as a serialized PHP array containing the expiry
 * timestamp and the raw string value. Suitable for single-instance deployments
 * and large payloads (e.g. full GraphQL introspection JSON ~5-10 MB) where
 * network round-trips to an external store would add more overhead than disk I/O.
 */
class FileCache implements CacheInterface
{
	public function __construct(private string $dir)
	{
		if (!is_dir($this->dir)) {
			mkdir($this->dir, 0755, true);
		}
	}

	private function path(string $key): string
	{
		return $this->dir . '/' . sha1($key) . '.cache';
	}

	public function get(string $key): ?string
	{
		$path = $this->path($key);
		if (!file_exists($path)) {
			return null;
		}

		$data = unserialize(file_get_contents($path));
		if (!is_array($data)) {
			@unlink($path);
			return null;
		}
		if ($data['expires'] > 0 && $data['expires'] < time()) {
			@unlink($path);
			return null;
		}

		return $data['value'];
	}

	public function set(string $key, string $value, int $ttl = 0): void
	{
		$data = [
			'expires' => $ttl > 0 ? time() + $ttl : 0,
			'value' => $value,
		];
		file_put_contents($this->path($key), serialize($data), LOCK_EX);
	}

	public function delete(string $key): void
	{
		$path = $this->path($key);
		if (file_exists($path)) {
			@unlink($path);
		}
	}
}
