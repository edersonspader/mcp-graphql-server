<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

readonly class ShopifyClient implements ShopifyClientInterface
{
	private Client $http;

	public function __construct(Config $config)
	{
		$url = sprintf(
			'https://%s/admin/api/%s/graphql.json',
			$config->store,
			$config->apiVersion,
		);

		$this->http = new Client([
			'base_uri' => $url,
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'X-Shopify-Access-Token' => $config->accessToken,
			],
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function query(string $query): array
	{
		$response = $this->http->post('', [
			RequestOptions::JSON => ['query' => $query],
		]);

		/** @var array<string, mixed> $result */
		$result = json_decode(
			(string) $response->getBody(),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);

		return $result;
	}
}
