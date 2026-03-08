<?php

namespace App;

class Config
{
	public string $graphqlUrl;
	public string $graphqlToken;
	public bool $cacheEnabled;
	public int $cacheTtl;
	public string $introspectionFile;

	public function __construct()
	{
		$this->graphqlUrl = getenv('GRAPHQL_URL') ?: '';
		$this->graphqlToken = getenv('GRAPHQL_TOKEN') ?: '';
		$this->cacheEnabled = (getenv('CACHE_ENABLED') ?: 'true') !== 'false';
		$this->cacheTtl = (int) (getenv('CACHE_TTL') ?: 3600);
		$this->introspectionFile = getenv('INTROSPECTION_FILE')
			?: __DIR__ . '/../schema/schema.json';
	}
}
