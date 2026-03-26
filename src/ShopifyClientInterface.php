<?php

declare(strict_types=1);

namespace App;

interface ShopifyClientInterface
{
	/**
	 * @return array<string, mixed>
	 */
	public function query(string $query): array;
}
