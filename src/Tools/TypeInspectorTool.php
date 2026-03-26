<?php

declare(strict_types=1);

namespace App\Tools;

use App\SchemaRegistry;
use Mcp\Capability\Attribute\McpTool;

class TypeInspectorTool
{
	private const int MAX_DESCRIPTION_LENGTH = 100;

	public function __construct(
		private readonly SchemaRegistry $registry,
	) {}

	/**
	 * @return array<string, mixed>
	 */
	#[McpTool(
		name: 'listTypes',
		description: 'List GraphQL types with optional kind filter (OBJECT, ENUM, INPUT_OBJECT, INTERFACE, SCALAR, UNION) and pagination. Internal types (__Type, etc.) are excluded. Use getType() for full details. For Shopify, the root query type is "QueryRoot" and the root mutation type is "Mutation".',
	)]
	public function listTypes(string $kind = '', int $offset = 0, int $limit = 30): array
	{
		$kind = strtoupper(trim($kind));
		$filtered = [];

		foreach ($this->registry->getTypes() as $type) {
			$name = $this->extractString($type, 'name');

			if ($name === '' || str_starts_with($name, '__')) {
				continue;
			}

			if ($kind !== '' && ($type['kind'] ?? '') !== $kind) {
				continue;
			}

			$filtered[] = [
				'name' => $name,
				'kind' => $type['kind'] ?? null,
				'description' => $this->truncate($this->extractString($type, 'description')),
			];
		}

		$total = count($filtered);
		$sliced = array_slice($filtered, max(0, $offset), max(1, $limit));

		return [
			'items' => $sliced,
			'total' => $total,
			'offset' => $offset,
			'limit' => $limit,
		];
	}

	/**
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	#[McpTool(
		name: 'getType',
		description: 'Get full type definition by name. Fields and input fields have readable type strings (e.g. "[Product!]!" instead of nested objects). Use this to inspect any OBJECT, ENUM, INPUT_OBJECT, INTERFACE, SCALAR, or UNION. For Shopify, the root query type is "QueryRoot" and the root mutation type is "Mutation".',
	)]
	public function getType(string $name): array
	{
		$name = trim($name);

		if ($name === '') {
			throw new \InvalidArgumentException('Type name is required');
		}

		$type = $this->registry->findTypeByName($name);

		if ($type === null) {
			throw new \RuntimeException('Type not found: ' . $name);
		}

		return $this->formatType($type);
	}

	/**
	 * @return list<array<string, mixed>>
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	#[McpTool(
		name: 'getEnumValues',
		description: 'Get all values of a GraphQL ENUM type. Returns name, description, and deprecation info for each value.',
	)]
	public function getEnumValues(string $name): array
	{
		$name = trim($name);

		if ($name === '') {
			throw new \InvalidArgumentException('Enum name is required');
		}

		$type = $this->registry->findTypeByName($name);

		if ($type === null) {
			throw new \RuntimeException('Type not found: ' . $name);
		}

		$typeKind = is_string($type['kind'] ?? null) ? $type['kind'] : 'unknown';

		if ($typeKind !== 'ENUM') {
			throw new \InvalidArgumentException($name . ' is not an ENUM type (kind: ' . $typeKind . ')');
		}

		/** @var list<array<string, mixed>> $values */
		$values = $type['enumValues'] ?? [];
		$out = [];

		foreach ($values as $v) {
			$out[] = [
				'name' => $v['name'] ?? null,
				'description' => $v['description'] ?? null,
				'isDeprecated' => $v['isDeprecated'] ?? false,
				'deprecationReason' => $v['deprecationReason'] ?? null,
			];
		}

		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	#[McpTool(
		name: 'getInputFields',
		description: 'Get all fields of a GraphQL INPUT_OBJECT type. Returns name, type (readable string), description, and default value.',
	)]
	public function getInputFields(string $name): array
	{
		$name = trim($name);

		if ($name === '') {
			throw new \InvalidArgumentException('Input type name is required');
		}

		$type = $this->registry->findTypeByName($name);

		if ($type === null) {
			throw new \RuntimeException('Type not found: ' . $name);
		}

		$typeKind = is_string($type['kind'] ?? null) ? $type['kind'] : 'unknown';

		if ($typeKind !== 'INPUT_OBJECT') {
			throw new \InvalidArgumentException($name . ' is not an INPUT_OBJECT type (kind: ' . $typeKind . ')');
		}

		/** @var list<array<string, mixed>> $inputFields */
		$inputFields = $type['inputFields'] ?? [];
		$out = [];

		foreach ($inputFields as $f) {
			/** @var array<string, mixed> $fieldType */
			$fieldType = $f['type'] ?? [];

			$out[] = [
				'name' => $f['name'] ?? null,
				'type' => $this->registry->resolveTypeName($fieldType),
				'description' => $f['description'] ?? null,
				'defaultValue' => $f['defaultValue'] ?? null,
			];
		}

		return $out;
	}

	/**
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	#[McpTool(
		name: 'getFieldDetails',
		description: 'Get full details of a specific field on a type, including arguments with readable type strings. Use this when you need argument details for a field.',
	)]
	public function getFieldDetails(string $typeName, string $fieldName): array
	{
		$typeName = trim($typeName);
		$fieldName = trim($fieldName);

		if ($typeName === '' || $fieldName === '') {
			throw new \InvalidArgumentException('Both typeName and fieldName are required');
		}

		$type = $this->registry->findTypeByName($typeName);

		if ($type === null) {
			throw new \RuntimeException('Type not found: ' . $typeName);
		}

		/** @var list<array<string, mixed>> $fields */
		$fields = $type['fields'] ?? [];

		foreach ($fields as $field) {
			if (($field['name'] ?? '') !== $fieldName) {
				continue;
			}

			/** @var array<string, mixed> $fieldType */
			$fieldType = $field['type'] ?? [];

			return [
				'name' => $field['name'],
				'type' => $this->registry->resolveTypeName($fieldType),
				'description' => $field['description'] ?? null,
				'args' => $this->formatArgs($field['args'] ?? []),
				'isDeprecated' => $field['isDeprecated'] ?? false,
				'deprecationReason' => $field['deprecationReason'] ?? null,
			];
		}

		throw new \RuntimeException('Field not found: ' . $typeName . '.' . $fieldName);
	}

	/**
	 * @return list<array<string, mixed>>
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	#[McpTool(
		name: 'getConnections',
		description: 'List all connection/pagination fields on a type (Shopify uses the Relay Connection pattern). Detects fields whose return type ends with "Connection" and resolves the node type inside. For Shopify, the root query type is "QueryRoot" (not "Query").',
	)]
	public function getConnections(string $typeName): array
	{
		$typeName = trim($typeName);

		if ($typeName === '') {
			throw new \InvalidArgumentException('Type name is required');
		}

		$type = $this->registry->findTypeByName($typeName);

		if ($type === null) {
			throw new \RuntimeException('Type not found: ' . $typeName);
		}

		/** @var list<array<string, mixed>> $fields */
		$fields = $type['fields'] ?? [];
		$connections = [];

		foreach ($fields as $field) {
			/** @var array<string, mixed> $fieldType */
			$fieldType = $field['type'] ?? [];
			$resolved = $this->registry->resolveTypeRef($fieldType);

			if (!str_ends_with($resolved['name'], 'Connection')) {
				continue;
			}

			$nodeType = $this->resolveConnectionNodeType($resolved['name']);

			$connections[] = [
				'fieldName' => $field['name'] ?? null,
				'connectionType' => $resolved['name'],
				'nodeType' => $nodeType,
				'args' => $this->formatArgs($field['args'] ?? []),
			];
		}

		return $connections;
	}

	private function resolveConnectionNodeType(string $connectionTypeName): string|null
	{
		$connectionType = $this->registry->findTypeByName($connectionTypeName);

		if ($connectionType === null) {
			return null;
		}

		/** @var list<array<string, mixed>> $fields */
		$fields = $connectionType['fields'] ?? [];

		foreach ($fields as $field) {
			if (($field['name'] ?? '') !== 'edges') {
				continue;
			}

			/** @var array<string, mixed> $edgesType */
			$edgesType = $field['type'] ?? [];
			$edgesResolved = $this->registry->resolveTypeRef($edgesType);
			$edgeType = $this->registry->findTypeByName($edgesResolved['name']);

			if ($edgeType === null) {
				return null;
			}

			/** @var list<array<string, mixed>> $edgeFields */
			$edgeFields = $edgeType['fields'] ?? [];

			foreach ($edgeFields as $edgeField) {
				if (($edgeField['name'] ?? '') !== 'node') {
					continue;
				}

				/** @var array<string, mixed> $nodeTypeRef */
				$nodeTypeRef = $edgeField['type'] ?? [];

				return $this->registry->resolveTypeRef($nodeTypeRef)['name'];
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $type
	 *
	 * @return array<string, mixed>
	 */
	private function formatType(array $type): array
	{
		$out = [
			'name' => $type['name'] ?? null,
			'kind' => $type['kind'] ?? null,
			'description' => $type['description'] ?? null,
		];

		/** @var list<array<string, mixed>> $fields */
		$fields = $type['fields'] ?? [];

		if ($fields !== []) {
			$out['fields'] = [];

			foreach ($fields as $f) {
				/** @var array<string, mixed> $fieldType */
				$fieldType = $f['type'] ?? [];

				$out['fields'][] = [
					'name' => $f['name'] ?? null,
					'type' => $this->registry->resolveTypeName($fieldType),
					'description' => $f['description'] ?? null,
					'isDeprecated' => $f['isDeprecated'] ?? false,
				];
			}
		}

		/** @var list<array<string, mixed>> $inputFields */
		$inputFields = $type['inputFields'] ?? [];

		if ($inputFields !== []) {
			$out['inputFields'] = [];

			foreach ($inputFields as $f) {
				/** @var array<string, mixed> $fieldType */
				$fieldType = $f['type'] ?? [];

				$out['inputFields'][] = [
					'name' => $f['name'] ?? null,
					'type' => $this->registry->resolveTypeName($fieldType),
					'description' => $f['description'] ?? null,
					'defaultValue' => $f['defaultValue'] ?? null,
				];
			}
		}

		/** @var list<array<string, mixed>>|null $enumValues */
		$enumValues = $type['enumValues'] ?? null;

		if ($enumValues !== null) {
			$out['enumValues'] = [];

			foreach ($enumValues as $v) {
				$out['enumValues'][] = [
					'name' => $v['name'] ?? null,
					'description' => $v['description'] ?? null,
					'isDeprecated' => $v['isDeprecated'] ?? false,
				];
			}
		}

		/** @var list<array<string, mixed>>|null $interfaces */
		$interfaces = $type['interfaces'] ?? null;

		if (is_array($interfaces) && $interfaces !== []) {
			$out['interfaces'] = array_map(
				fn(array $i): string => is_string($i['name'] ?? null) ? $i['name'] : 'Unknown',
				$interfaces,
			);
		}

		/** @var list<array<string, mixed>>|null $possibleTypes */
		$possibleTypes = $type['possibleTypes'] ?? null;

		if (is_array($possibleTypes) && $possibleTypes !== []) {
			$out['possibleTypes'] = array_map(
				fn(array $p): string => is_string($p['name'] ?? null) ? $p['name'] : 'Unknown',
				$possibleTypes,
			);
		}

		return $out;
	}

	/**
	 * @param mixed $args
	 *
	 * @return list<array<string, mixed>>
	 */
	private function formatArgs(mixed $args): array
	{
		if (!is_array($args)) {
			return [];
		}

		$out = [];

		/** @var array<string, mixed> $arg */
		foreach ($args as $arg) {
			/** @var array<string, mixed> $argType */
			$argType = $arg['type'] ?? [];

			$out[] = [
				'name' => $arg['name'] ?? null,
				'type' => $this->registry->resolveTypeName($argType),
				'description' => $arg['description'] ?? null,
				'defaultValue' => $arg['defaultValue'] ?? null,
			];
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function extractString(array $data, string $key): string
	{
		$value = $data[$key] ?? null;

		return is_string($value) ? $value : '';
	}

	private function truncate(string $text): string
	{
		if ($text === '' || mb_strlen($text) <= self::MAX_DESCRIPTION_LENGTH) {
			return $text;
		}

		return mb_substr($text, 0, self::MAX_DESCRIPTION_LENGTH) . '...';
	}
}
