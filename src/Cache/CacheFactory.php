<?php

namespace App\Cache;

use App\Config;

class CacheFactory
{
	public static function create(Config $config): CacheInterface
	{
		if (!$config->cacheEnabled) {
			return new NullCache();
		}

		return new FileCache(\dirname(__DIR__, 2) . '/var/cache');
	}
}
