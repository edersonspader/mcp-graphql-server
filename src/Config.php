<?php

declare(strict_types=1);

namespace App;

readonly class Config
{
	private function __construct(
		public string $store,
		public string $accessToken,
		public string $apiVersion,
	) {}

	public static function fromEnv(): self
	{
		$store = getenv('SHOPIFY_STORE');
		$token = getenv('SHOPIFY_ACCESS_TOKEN');
		$version = getenv('SHOPIFY_API_VERSION') ?: '2025-01';

		if ($store === false || $store === '') {
			throw new \RuntimeException('SHOPIFY_STORE environment variable is required');
		}

		if ($token === false || $token === '') {
			throw new \RuntimeException('SHOPIFY_ACCESS_TOKEN environment variable is required');
		}

		return new self(
			store: $store,
			accessToken: $token,
			apiVersion: $version,
		);
	}
}
