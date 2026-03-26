<?php

declare(strict_types=1);

namespace App\Tools;

use App\SchemaRegistry;
use Mcp\Capability\Attribute\McpTool;

class QueryBuilderTool
{
	public function __construct(
		private readonly SchemaRegistry $registry,
	) {}

	/**
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	#[McpTool(
		name: 'getQueryDetails',
		description: 'Get full details of a specific query by name, including all arguments with types and the return type fields (1 level). Use this before building a query to know exactly what arguments are needed and what data is available.',
	)]
	public function getQueryDetails(string $name): array
	{
		return $this->getOperationDetails(name: $name, operationType: 'query');
	}

	/**
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	#[McpTool(
		name: 'getMutationDetails',
		description: 'Get full details of a specific mutation by name, including all arguments with types and the return type fields (1 level). Use this before building a mutation to know exactly what arguments are needed and what data is returned.',
	)]
	public function getMutationDetails(string $name): array
	{
		return $this->getOperationDetails(name: $name, operationType: 'mutation');
	}

	/**
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	#[McpTool(
		name: 'buildQuerySkeleton',
		description: 'Generate a GraphQL query/mutation skeleton string ready to adapt. Includes required variables, scalar fields expanded, and object fields marked with "{ ... }" for manual expansion. The operation type defaults to "query".',
	)]
	public function buildQuerySkeleton(string $name, string $operationType = 'query'): array
	{
		$name = trim($name);
		$operationType = strtolower(trim($operationType));

		if ($name === '') {
			throw new \InvalidArgumentException('Operation name is required');
		}

		$field = $this->findOperationField($name, $operationType);

		/** @var list<array<string, mixed>> $args */
		$args = $field['args'] ?? [];
		/** @var array<string, mixed> $returnTypeRef */
		$returnTypeRef = $field['type'] ?? [];
		$returnResolved = $this->registry->resolveTypeRef($returnTypeRef);

		$variableDeclarations = [];
		$fieldArguments = [];

		foreach ($args as $arg) {
			/** @var array<string, mixed> $argType */
			$argType = $arg['type'] ?? [];
			$argName = is_string($arg['name'] ?? null) ? $arg['name'] : '';
			$typeName = $this->registry->resolveTypeName($argType);

			$variableDeclarations[] = '$' . $argName . ': ' . $typeName;
			$fieldArguments[] = $argName . ': $' . $argName;
		}

		$returnFields = $this->buildFieldSelection($returnResolved['name']);

		$variablePart = $variableDeclarations !== []
			? '(' . implode(', ', $variableDeclarations) . ')'
			: '';

		$argsPart = $fieldArguments !== []
			? '(' . implode(', ', $fieldArguments) . ')'
			: '';

		$query = $operationType . ' ' . $name . $variablePart . " {\n"
			. '  ' . $name . $argsPart . " {\n"
			. $returnFields
			. "  }\n"
			. '}';

		return [
			'skeleton' => $query,
			'variables' => $this->formatVariableHints($args),
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	#[McpTool(
		name: 'getRequiredArgs',
		description: 'List only the required (non-null) arguments for a query or mutation. Useful to know the minimum needed to call an operation. The operation type defaults to "query".',
	)]
	public function getRequiredArgs(string $name, string $operationType = 'query'): array
	{
		$name = trim($name);
		$operationType = strtolower(trim($operationType));

		if ($name === '') {
			throw new \InvalidArgumentException('Operation name is required');
		}

		$field = $this->findOperationField($name, $operationType);

		/** @var list<array<string, mixed>> $args */
		$args = $field['args'] ?? [];
		$required = [];

		foreach ($args as $arg) {
			/** @var array<string, mixed> $argType */
			$argType = $arg['type'] ?? [];

			if (($argType['kind'] ?? '') !== 'NON_NULL') {
				continue;
			}

			$required[] = [
				'name' => $arg['name'] ?? null,
				'type' => $this->registry->resolveTypeName($argType),
				'description' => $arg['description'] ?? null,
			];
		}

		return $required;
	}

	/**
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	private function getOperationDetails(string $name, string $operationType): array
	{
		$name = trim($name);
		$operationType = strtolower(trim($operationType));

		if ($name === '') {
			throw new \InvalidArgumentException('Operation name is required');
		}

		$field = $this->findOperationField($name, $operationType);

		/** @var array<string, mixed> $returnTypeRef */
		$returnTypeRef = $field['type'] ?? [];
		$resolved = $this->registry->resolveTypeRef($returnTypeRef);

		$returnTypeFields = null;
		$returnTypeObj = $this->registry->findTypeByName($resolved['name']);

		if ($returnTypeObj !== null && in_array($returnTypeObj['kind'] ?? '', ['OBJECT', 'INTERFACE'], true)) {
			/** @var list<array<string, mixed>> $fields */
			$fields = $returnTypeObj['fields'] ?? [];
			$returnTypeFields = [];

			foreach ($fields as $f) {
				/** @var array<string, mixed> $fType */
				$fType = $f['type'] ?? [];

				$returnTypeFields[] = [
					'name' => $f['name'] ?? null,
					'type' => $this->registry->resolveTypeName($fType),
					'description' => $f['description'] ?? null,
				];
			}
		}

		return [
			'name' => $field['name'] ?? null,
			'description' => $field['description'] ?? null,
			'args' => $this->formatArgs($field['args'] ?? []),
			'returnType' => $this->registry->resolveTypeName($returnTypeRef),
			'returnTypeFields' => $returnTypeFields,
		];
	}

	/**
	 * @return array<string, mixed>
	 *
	 * @throws \RuntimeException
	 */
	private function findOperationField(string $name, string $operationType): array
	{
		$rootTypeName = match ($operationType) {
			'query' => $this->registry->getQueryTypeName(),
			'mutation' => $this->registry->getMutationTypeName(),
			default => throw new \InvalidArgumentException('operationType must be "query" or "mutation"'),
		};

		if ($rootTypeName === null) {
			throw new \RuntimeException('No ' . $operationType . ' type defined in schema');
		}

		$rootType = $this->registry->findTypeByName($rootTypeName);

		if ($rootType === null) {
			throw new \RuntimeException('Root type not found: ' . $rootTypeName);
		}

		/** @var list<array<string, mixed>> $fields */
		$fields = $rootType['fields'] ?? [];

		foreach ($fields as $field) {
			if (($field['name'] ?? '') === $name) {
				return $field;
			}
		}

		throw new \RuntimeException(ucfirst($operationType) . ' not found: ' . $name);
	}

	private function buildFieldSelection(string $typeName): string
	{
		$type = $this->registry->findTypeByName($typeName);

		if ($type === null) {
			return "    # Unable to resolve type: {$typeName}\n";
		}

		$kind = $type['kind'] ?? '';

		if (!in_array($kind, ['OBJECT', 'INTERFACE'], true)) {
			return '';
		}

		/** @var list<array<string, mixed>> $fields */
		$fields = $type['fields'] ?? [];
		$lines = [];

		foreach ($fields as $f) {
			$fieldName = is_string($f['name'] ?? null) ? $f['name'] : '';

			if ($fieldName === '') {
				continue;
			}

			/** @var array<string, mixed> $fieldType */
			$fieldType = $f['type'] ?? [];
			$resolved = $this->registry->resolveTypeRef($fieldType);

			if (in_array($resolved['kind'], ['SCALAR', 'ENUM'], true)) {
				$lines[] = '    ' . $fieldName;
			} else {
				$lines[] = '    ' . $fieldName . ' { ... }';
			}
		}

		return implode("\n", $lines) . "\n";
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
	 * @param list<array<string, mixed>> $args
	 *
	 * @return list<array<string, string>>
	 */
	private function formatVariableHints(array $args): array
	{
		$hints = [];

		foreach ($args as $arg) {
			/** @var array<string, mixed> $argType */
			$argType = $arg['type'] ?? [];
			$resolved = $this->registry->resolveTypeRef($argType);

			$hints[] = [
				'name' => is_string($arg['name'] ?? null) ? $arg['name'] : '',
				'type' => $this->registry->resolveTypeName($argType),
				'required' => ($argType['kind'] ?? '') === 'NON_NULL' ? 'yes' : 'no',
				'underlyingType' => $resolved['kind'],
			];
		}

		return $hints;
	}
}
