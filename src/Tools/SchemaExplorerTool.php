<?php

declare(strict_types=1);

namespace App\Tools;

use App\SchemaRegistry;
use Mcp\Capability\Attribute\McpTool;

class SchemaExplorerTool
{
	private const int MAX_SEARCH_RESULTS = 50;
	private const int MAX_DESCRIPTION_LENGTH = 120;

	public function __construct(
		private readonly SchemaRegistry $registry,
	) {}

	/**
	 * @return array<string, mixed>
	 */
	#[McpTool(
		name: 'listQueries',
		description: 'List available GraphQL queries with pagination and optional name filter. Returns name, description (truncated), and return type as readable string. Use getQueryDetails() for full details of a specific query.',
	)]
	public function listQueries(int $offset = 0, int $limit = 20, string $filter = ''): array
	{
		return $this->listOperations(
			typeName: $this->registry->getQueryTypeName(),
			offset: $offset,
			limit: $limit,
			filter: $filter,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	#[McpTool(
		name: 'listMutations',
		description: 'List available GraphQL mutations with pagination and optional name filter. Returns name, description (truncated), and return type as readable string. Use getMutationDetails() for full details of a specific mutation.',
	)]
	public function listMutations(int $offset = 0, int $limit = 20, string $filter = ''): array
	{
		return $this->listOperations(
			typeName: $this->registry->getMutationTypeName(),
			offset: $offset,
			limit: $limit,
			filter: $filter,
		);
	}

	/**
	 * @return array<string, list<array<string, mixed>>>
	 */
	#[McpTool(
		name: 'search',
		description: 'Search types and fields by term (matches names and descriptions, case-insensitive). Returns up to 50 results per category. Internal types (__Type, __Schema, etc.) are excluded.',
	)]
	public function search(string $term = ''): array
	{
		$term = trim($term);

		if ($term === '') {
			return [];
		}

		$results = ['types' => [], 'fields' => []];

		foreach ($this->registry->getTypes() as $type) {
			$typeName = $this->extractString($type, 'name');

			if ($typeName === '' || str_starts_with($typeName, '__')) {
				continue;
			}

			$typeDescription = $this->extractString($type, 'description');

			if ($this->matches($typeName, $term) || $this->matches($typeDescription, $term)) {
				if (count($results['types']) < self::MAX_SEARCH_RESULTS) {
					$results['types'][] = [
						'name' => $typeName,
						'kind' => $type['kind'] ?? null,
						'description' => $this->truncate($typeDescription),
					];
				}
			}

			/** @var list<array<string, mixed>> $fields */
			$fields = $type['fields'] ?? [];

			foreach ($fields as $field) {
				$fieldName = $this->extractString($field, 'name');
				$fieldDescription = $this->extractString($field, 'description');

				if ($fieldName === '') {
					continue;
				}

				if (!$this->matches($fieldName, $term) && !$this->matches($fieldDescription, $term)) {
					continue;
				}

				if (count($results['fields']) >= self::MAX_SEARCH_RESULTS) {
					continue;
				}

				/** @var array<string, mixed> $fieldType */
				$fieldType = $field['type'] ?? [];

				$results['fields'][] = [
					'type' => $typeName,
					'name' => $fieldName,
					'description' => $this->truncate($fieldDescription),
					'returnType' => $this->registry->resolveTypeName($fieldType),
				];
			}
		}

		return $results;
	}

	/**
	 * @return array<string, string>
	 */
	#[McpTool(
		name: 'clearCache',
		description: 'Reload schema from the GraphQL endpoint or local file',
	)]
	public function clearCache(): array
	{
		$this->registry->reload();

		return [
			'status' => 'ok',
			'message' => 'Schema refreshed',
			'source' => $this->registry->getSource(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function listOperations(string|null $typeName, int $offset, int $limit, string $filter): array
	{
		if ($typeName === null) {
			return ['items' => [], 'total' => 0, 'offset' => $offset, 'limit' => $limit];
		}

		$type = $this->registry->findTypeByName($typeName);

		if ($type === null) {
			return ['items' => [], 'total' => 0, 'offset' => $offset, 'limit' => $limit];
		}

		/** @var list<array<string, mixed>> $fields */
		$fields = $type['fields'] ?? [];
		$filter = trim($filter);

		if ($filter !== '') {
			$fields = array_values(array_filter(
				$fields,
				fn (array $f): bool => $this->matches($this->extractString($f, 'name'), $filter),
			));
		}

		$total = count($fields);
		$sliced = array_slice($fields, max(0, $offset), max(1, $limit));

		$items = [];

		foreach ($sliced as $field) {
			/** @var array<string, mixed> $fieldType */
			$fieldType = $field['type'] ?? [];

			$items[] = [
				'name' => $field['name'] ?? null,
				'description' => $this->truncate($this->extractString($field, 'description')),
				'returnType' => $this->registry->resolveTypeName($fieldType),
			];
		}

		return [
			'items' => $items,
			'total' => $total,
			'offset' => $offset,
			'limit' => $limit,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function extractString(array $data, string $key): string
	{
		$value = $data[$key] ?? null;

		return is_string($value) ? $value : '';
	}

	private function matches(string $haystack, string $needle): bool
	{
		return $haystack !== '' && mb_stripos($haystack, $needle) !== false;
	}

	private function truncate(string $text): string
	{
		if ($text === '' || mb_strlen($text) <= self::MAX_DESCRIPTION_LENGTH) {
			return $text;
		}

		return mb_substr($text, 0, self::MAX_DESCRIPTION_LENGTH) . '...';
	}
}
