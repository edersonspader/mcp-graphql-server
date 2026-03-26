<?php

declare(strict_types=1);

namespace App;

class SchemaRegistry
{
	private const INTROSPECTION_QUERY = <<<'GRAPHQL'
        query IntrospectionQuery {
          __schema {
            queryType { name }
            mutationType { name }
            subscriptionType { name }
            types {
              kind name description
              fields(includeDeprecated: true) {
                name description
                args {
                  name description
                  type { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name } } } } } } } }
                  defaultValue
                }
                type { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name } } } } } } } }
                isDeprecated deprecationReason
              }
              inputFields {
                name description
                type { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name } } } } } } } }
                defaultValue
              }
              interfaces { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name } } } } } } } }
              enumValues(includeDeprecated: true) { name description isDeprecated deprecationReason }
              possibleTypes { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name } } } } } } } }
            }
            directives {
              name description locations
              args {
                name description
                type { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name } } } } } } } }
                defaultValue
              }
            }
          }
        }
    GRAPHQL;

	/** @var array<string, mixed> */
	private array $schema = [];

	public function __construct(
		private readonly Config $config,
		private readonly ShopifyClientInterface $client,
	) {
		$this->load();
	}

	public function reload(): void
	{
		$this->fetchAndCache();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getSchema(): array
	{
		return $this->schema;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function getTypes(): array
	{
		/** @var list<array<string, mixed>> */
		return $this->schema['types'] ?? [];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findTypeByName(string $name): array|null
	{
		foreach ($this->getTypes() as $type) {
			if (isset($type['name']) && $type['name'] === $name) {
				return $type;
			}
		}

		return null;
	}

	public function getQueryTypeName(): string|null
	{
		/** @var array<string, mixed>|null $queryType */
		$queryType = $this->schema['queryType'] ?? null;
		$name = $queryType['name'] ?? null;

		return is_string($name) ? $name : null;
	}

	public function getMutationTypeName(): string|null
	{
		/** @var array<string, mixed>|null $mutationType */
		$mutationType = $this->schema['mutationType'] ?? null;
		$name = $mutationType['name'] ?? null;

		return is_string($name) ? $name : null;
	}

	/**
	 * Flatten a nested GraphQL type reference into a human-readable string.
	 *
	 * Examples:
	 *   {kind: SCALAR, name: String}                           → "String"
	 *   {kind: NON_NULL, ofType: {kind: SCALAR, name: ID}}     → "ID!"
	 *   {kind: LIST, ofType: {kind: OBJECT, name: Product}}    → "[Product]"
	 *   {kind: NON_NULL, ofType: {kind: LIST, ofType: {kind: NON_NULL, ofType: {kind: OBJECT, name: Product}}}} → "[Product!]!"
	 *
	 * @param array<string, mixed> $typeRef
	 */
	public function resolveTypeName(array $typeRef): string
	{
		$kind = $typeRef['kind'] ?? null;

		if ($kind === 'NON_NULL') {
			/** @var array<string, mixed> $ofType */
			$ofType = $typeRef['ofType'] ?? [];

			return $this->resolveTypeName($ofType) . '!';
		}

		if ($kind === 'LIST') {
			/** @var array<string, mixed> $ofType */
			$ofType = $typeRef['ofType'] ?? [];

			return '[' . $this->resolveTypeName($ofType) . ']';
		}

		$name = $typeRef['name'] ?? null;

		return is_string($name) ? $name : 'Unknown';
	}

	/**
	 * Resolve a nested type reference into the underlying named type.
	 *
	 * @param array<string, mixed> $typeRef
	 *
	 * @return array{name: string, kind: string, isList: bool, isNonNull: bool}
	 */
	public function resolveTypeRef(array $typeRef): array
	{
		$isNonNull = false;
		$isList = false;
		$current = $typeRef;

		while (true) {
			$kind = $current['kind'] ?? null;

			if ($kind === 'NON_NULL') {
				$isNonNull = true;
				/** @var array<string, mixed> */
				$current = $current['ofType'] ?? [];

				continue;
			}

			if ($kind === 'LIST') {
				$isList = true;
				/** @var array<string, mixed> */
				$current = $current['ofType'] ?? [];

				continue;
			}

			break;
		}

		return [
			'name' => is_string($current['name'] ?? null) ? $current['name'] : 'Unknown',
			'kind' => is_string($current['kind'] ?? null) ? $current['kind'] : 'Unknown',
			'isList' => $isList,
			'isNonNull' => $isNonNull,
		];
	}

	public function getSource(): string
	{
		return sprintf(
			'https://%s/admin/api/%s/graphql.json',
			$this->config->store,
			$this->config->apiVersion,
		);
	}

	private function getCachePath(): string
	{
		return \dirname(__DIR__) . '/var/cache/schema.json';
	}

	private function load(): void
	{
		$cachePath = $this->getCachePath();

		if (file_exists($cachePath)) {
			$raw = file_get_contents($cachePath);

			if ($raw !== false) {
				/** @var mixed $cache */
				$cache = json_decode($raw, true);

				if (
					is_array($cache)
					&& isset($cache['version'])
					&& $cache['version'] === $this->config->apiVersion
					&& isset($cache['data'])
					&& is_array($cache['data'])
				) {
					/** @var array<string, mixed> $schema */
					$schema = $cache['data'];
					$this->schema = $schema;

					return;
				}
			}
		}

		$this->fetchAndCache();
	}

	private function fetchAndCache(): void
	{
		$result = $this->client->query(self::INTROSPECTION_QUERY);

		if (
			!isset($result['data'])
			|| !is_array($result['data'])
			|| !isset($result['data']['__schema'])
			|| !is_array($result['data']['__schema'])
		) {
			throw new \RuntimeException('Response does not contain __schema');
		}

		/** @var array<string, mixed> $schema */
		$schema = $result['data']['__schema'];
		$this->schema = $schema;

		$cachePath = $this->getCachePath();
		$cacheDir = \dirname($cachePath);

		if (!is_dir($cacheDir)) {
			mkdir($cacheDir, 0755, true);
		}

		file_put_contents(
			$cachePath,
			json_encode(
				['version' => $this->config->apiVersion, 'data' => $schema],
				JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
			),
		);
	}
}
