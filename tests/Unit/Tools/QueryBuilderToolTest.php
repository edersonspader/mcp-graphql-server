<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Config;
use App\SchemaRegistry;
use App\Tests\FakeGraphQLClient;
use App\Tools\QueryBuilderTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryBuilderTool::class)]
final class QueryBuilderToolTest extends TestCase
{
	private QueryBuilderTool $tool;

	protected function setUp(): void
	{
		putenv('SHOPIFY_STORE=test.myshopify.com');
		putenv('SHOPIFY_ACCESS_TOKEN=shpat_test');
		putenv('SHOPIFY_API_VERSION=2025-01');

		$config = Config::fromEnv();
		$registry = new SchemaRegistry($config, new FakeGraphQLClient());
		$this->tool = new QueryBuilderTool($registry);
	}

	#[Test]
	public function it_gets_query_details(): void
	{
		$result = $this->tool->getQueryDetails('product');

		self::assertSame('product', $result['name']);
		self::assertArrayHasKey('description', $result);
		self::assertArrayHasKey('args', $result);
		self::assertArrayHasKey('returnType', $result);
		self::assertIsString($result['returnType']);
	}

	#[Test]
	public function it_includes_return_type_fields_in_query_details(): void
	{
		$result = $this->tool->getQueryDetails('product');

		self::assertArrayHasKey('returnTypeFields', $result);

		if ($result['returnTypeFields'] !== null) {
			self::assertGreaterThan(0, count($result['returnTypeFields']));

			$first = $result['returnTypeFields'][0];

			self::assertArrayHasKey('name', $first);
			self::assertArrayHasKey('type', $first);
			self::assertIsString($first['type']);
		}
	}

	#[Test]
	public function it_includes_args_with_flat_types_in_query_details(): void
	{
		$result = $this->tool->getQueryDetails('product');

		foreach ($result['args'] as $arg) {
			self::assertArrayHasKey('name', $arg);
			self::assertArrayHasKey('type', $arg);
			self::assertIsString($arg['type']);
			self::assertArrayHasKey('description', $arg);
		}
	}

	#[Test]
	public function it_throws_on_unknown_query(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Query not found');

		$this->tool->getQueryDetails('__nonExistentQuery__');
	}

	#[Test]
	public function it_throws_on_empty_query_name(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		$this->tool->getQueryDetails('');
	}

	#[Test]
	public function it_gets_mutation_details(): void
	{
		$result = $this->tool->getMutationDetails('productCreate');

		self::assertSame('productCreate', $result['name']);
		self::assertArrayHasKey('args', $result);
		self::assertArrayHasKey('returnType', $result);
		self::assertIsString($result['returnType']);
	}

	#[Test]
	public function it_throws_on_unknown_mutation(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Mutation not found');

		$this->tool->getMutationDetails('__nonExistentMutation__');
	}

	#[Test]
	public function it_builds_query_skeleton(): void
	{
		$result = $this->tool->buildQuerySkeleton('product');

		self::assertArrayHasKey('skeleton', $result);
		self::assertArrayHasKey('variables', $result);
		self::assertIsString($result['skeleton']);
		self::assertStringContainsString('query product', $result['skeleton']);
		self::assertStringContainsString('{', $result['skeleton']);
		self::assertStringContainsString('}', $result['skeleton']);
	}

	#[Test]
	public function it_builds_mutation_skeleton(): void
	{
		$result = $this->tool->buildQuerySkeleton('productCreate', 'mutation');

		self::assertStringContainsString('mutation productCreate', $result['skeleton']);
	}

	#[Test]
	public function it_includes_variables_in_skeleton(): void
	{
		$result = $this->tool->buildQuerySkeleton('product');

		self::assertIsArray($result['variables']);

		if ($result['variables'] !== []) {
			$first = $result['variables'][0];

			self::assertArrayHasKey('name', $first);
			self::assertArrayHasKey('type', $first);
			self::assertArrayHasKey('required', $first);
		}
	}

	#[Test]
	public function it_shows_scalar_fields_expanded_and_objects_collapsed(): void
	{
		$result = $this->tool->buildQuerySkeleton('product');

		self::assertStringContainsString('{ ... }', $result['skeleton']);
	}

	#[Test]
	public function it_throws_on_empty_operation_name_for_skeleton(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		$this->tool->buildQuerySkeleton('');
	}

	#[Test]
	public function it_throws_on_invalid_operation_type(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('operationType must be');

		$this->tool->buildQuerySkeleton('product', 'subscription');
	}

	#[Test]
	public function it_gets_required_args_for_query(): void
	{
		$result = $this->tool->getRequiredArgs('product');

		self::assertIsArray($result);

		foreach ($result as $arg) {
			self::assertArrayHasKey('name', $arg);
			self::assertArrayHasKey('type', $arg);
			self::assertIsString($arg['type']);
			self::assertStringContainsString('!', $arg['type']);
		}
	}

	#[Test]
	public function it_gets_required_args_for_mutation(): void
	{
		$result = $this->tool->getRequiredArgs('productCreate', 'mutation');

		self::assertIsArray($result);

		foreach ($result as $arg) {
			self::assertStringContainsString('!', $arg['type']);
		}
	}

	#[Test]
	public function it_throws_on_empty_operation_name_for_required_args(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		$this->tool->getRequiredArgs('');
	}

	#[Test]
	public function it_throws_on_unknown_operation_for_required_args(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->tool->getRequiredArgs('__nonExistent__');
	}
}
