<?php

declare(strict_types=1);

namespace App\Tests;

use App\ShopifyClientInterface;

final class FakeGraphQLClient implements ShopifyClientInterface
{
	/**
	 * @return array<string, mixed>
	 */
	public function query(string $query): array
	{
		$path = \dirname(__DIR__) . '/tests/fixtures/schema.json';
		$content = file_get_contents($path);

		if ($content === false) {
			throw new \RuntimeException('Failed to read schema fixture: ' . $path);
		}

		/** @var array<string, mixed> */
		return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
	}
}
