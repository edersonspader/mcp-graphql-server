<?php

namespace App;

use App\Cache\CacheInterface;

class MCPGraphQLServer
{
	private array $schema = [];
	private string $cacheKey = '';

	public function __construct(
		private Config $config,
		private CacheInterface $cache
	) {
		$this->loadIntrospection();
	}

	private function getCacheKey(): string
	{
		return 'graphql:schema:' . sha1($this->config->graphqlUrl . ':' . $this->config->graphqlToken);
	}

	private function loadIntrospection(): void
	{
		$this->cacheKey = $this->getCacheKey();

		$cached = $this->cache->get($this->cacheKey);
		if ($cached !== null) {
			$schema = json_decode($cached, true);
			if (is_array($schema)) {
				$this->schema = $schema;
				return;
			}
		}

		$raw = $this->fetchRaw();
		$data = json_decode($raw, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
		}
		if (!isset($data['data']['__schema'])) {
			throw new \RuntimeException('Response does not contain __schema');
		}

		$this->schema = $data['data']['__schema'];
		$this->cache->set(
			$this->cacheKey,
			json_encode($this->schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			$this->config->cacheTtl
		);
	}

	/**
	 * Returns the raw JSON string from URL or local file.
	 */
	private function fetchRaw(): string
	{
		if ($this->config->graphqlUrl !== '') {
			return $this->fetchFromUrl();
		}

		$path = $this->config->introspectionFile;
		if (!file_exists($path)) {
			throw new \RuntimeException(
				'No GRAPHQL_URL configured and introspection file not found: ' . $path
			);
		}

		$content = file_get_contents($path);
		if ($content === false) {
			throw new \RuntimeException('Failed to read introspection file: ' . $path);
		}

		return $content;
	}

	/**
	 * Fetches introspection by POSTing the standard introspection query to the
	 * configured GraphQL endpoint using the Bearer token when provided.
	 */
	private function fetchFromUrl(): string
	{
		$queryFile = __DIR__ . '/../schema/introspection.graphql';
		if (!file_exists($queryFile)) {
			throw new \RuntimeException('Introspection query file not found: ' . $queryFile);
		}

		$query = file_get_contents($queryFile);
		if ($query === false) {
			throw new \RuntimeException('Failed to read introspection query file: ' . $queryFile);
		}

		$payload = json_encode(['query' => $query], JSON_THROW_ON_ERROR);

		$headers = ['Content-Type: application/json', 'Accept: application/json'];
		if ($this->config->graphqlToken !== '') {
			$headers[] = 'Authorization: Bearer ' . $this->config->graphqlToken;
		}

		$ch = curl_init($this->config->graphqlUrl);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => true,
		]);

		$response = curl_exec($ch);
		$error = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($response === false) {
			throw new \RuntimeException('GraphQL request failed: ' . $error);
		}
		if ($httpCode < 200 || $httpCode >= 300) {
			throw new \RuntimeException("GraphQL endpoint returned HTTP {$httpCode}");
		}

		return $response;
	}

	/**
	 * Clears the cached schema and reloads from the source.
	 * Useful when the schema changes and the LLM needs fresh data.
	 */
	public function clearCache(): array
	{
		$this->cache->delete($this->cacheKey);
		$this->loadIntrospection();

		return [
			'status' => 'ok',
			'message' => 'Cache cleared and schema refreshed',
			'source' => $this->config->graphqlUrl ?: $this->config->introspectionFile,
		];
	}

	public function introspection(): array
	{
		return $this->schema;
	}

	private function findTypeByName(string $name): ?array
	{
		foreach ($this->schema['types'] as $t) {
			if (isset($t['name']) && $t['name'] === $name) {
				return $t;
			}
		}
		return null;
	}

	public function listQueries(): array
	{
		$name = $this->schema['queryType']['name'] ?? null;
		if (!$name) return [];

		$type = $this->findTypeByName($name);
		if (!$type) return [];

		$fields = $type['fields'] ?? [];
		$out = [];
		foreach ($fields as $f) {
			$out[] = [
				'name' => $f['name'] ?? null,
				'description' => $f['description'] ?? null,
				'args' => $f['args'] ?? [],
				'type' => $f['type'] ?? null
			];
		}
		return $out;
	}

	public function listMutations(): array
	{
		$name = $this->schema['mutationType']['name'] ?? null;
		if (!$name) return [];

		$type = $this->findTypeByName($name);
		if (!$type) return [];

		$fields = $type['fields'] ?? [];
		$out = [];
		foreach ($fields as $f) {
			$out[] = [
				'name' => $f['name'] ?? null,
				'description' => $f['description'] ?? null,
				'args' => $f['args'] ?? [],
				'type' => $f['type'] ?? null
			];
		}
		return $out;
	}

	public function getType(string $name): array
	{
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('Type name is required');
		}

		$type = $this->findTypeByName($name);
		if (!$type) {
			throw new \RuntimeException('Type not found: ' . $name);
		}

		return $type;
	}

	public function search(string $term): array
	{
		$term = trim($term);
		if ($term === '') return [];

		$results = ['types' => [], 'fields' => []];

		foreach ($this->schema['types'] as $t) {
			$tname = $t['name'] ?? '';
			if ($tname && mb_stripos($tname, $term) !== false) {
				$results['types'][] = ['name' => $tname, 'kind' => $t['kind'] ?? null];
			}

			$fields = $t['fields'] ?? [];
			foreach ($fields as $f) {
				$fname = $f['name'] ?? '';
				if ($fname && mb_stripos($fname, $term) !== false) {
					$results['fields'][] = [
						'type' => $tname,
						'name' => $fname,
						'description' => $f['description'] ?? null
					];
				}
			}
		}

		return $results;
	}
}
