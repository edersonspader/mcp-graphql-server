<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Config;
use App\SchemaRegistry;
use App\Tests\FakeGraphQLClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaRegistry::class)]
final class SchemaRegistryTest extends TestCase
{
	private SchemaRegistry $registry;

	protected function setUp(): void
	{
		putenv('SHOPIFY_STORE=test.myshopify.com');
		putenv('SHOPIFY_ACCESS_TOKEN=shpat_test');
		putenv('SHOPIFY_API_VERSION=2025-01');

		$config = Config::fromEnv();
		$this->registry = new SchemaRegistry($config, new FakeGraphQLClient());
	}

	#[Test]
	public function it_loads_schema_successfully(): void
	{
		$schema = $this->registry->getSchema();

		self::assertArrayHasKey('types', $schema);
		self::assertArrayHasKey('queryType', $schema);
	}

	#[Test]
	public function it_returns_types_as_non_empty_list(): void
	{
		$types = $this->registry->getTypes();

		self::assertGreaterThan(0, count($types));
	}

	#[Test]
	public function it_finds_type_by_name(): void
	{
		$type = $this->registry->findTypeByName('String');

		self::assertNotNull($type);
		self::assertSame('String', $type['name']);
		self::assertSame('SCALAR', $type['kind']);
	}

	#[Test]
	public function it_returns_null_for_unknown_type(): void
	{
		$type = $this->registry->findTypeByName('__NonExistent__');

		self::assertNull($type);
	}

	#[Test]
	public function it_returns_query_type_name(): void
	{
		$name = $this->registry->getQueryTypeName();

		self::assertNotNull($name);
		self::assertNotSame('', $name);
	}

	#[Test]
	public function it_returns_mutation_type_name(): void
	{
		$name = $this->registry->getMutationTypeName();

		self::assertNotNull($name);
		self::assertNotSame('', $name);
	}

	#[Test]
	public function it_resolves_scalar_type_name(): void
	{
		$result = $this->registry->resolveTypeName([
			'kind' => 'SCALAR',
			'name' => 'String',
			'ofType' => null,
		]);

		self::assertSame('String', $result);
	}

	#[Test]
	public function it_resolves_non_null_type_name(): void
	{
		$result = $this->registry->resolveTypeName([
			'kind' => 'NON_NULL',
			'name' => null,
			'ofType' => ['kind' => 'SCALAR', 'name' => 'ID', 'ofType' => null],
		]);

		self::assertSame('ID!', $result);
	}

	#[Test]
	public function it_resolves_list_type_name(): void
	{
		$result = $this->registry->resolveTypeName([
			'kind' => 'LIST',
			'name' => null,
			'ofType' => ['kind' => 'OBJECT', 'name' => 'Product', 'ofType' => null],
		]);

		self::assertSame('[Product]', $result);
	}

	#[Test]
	public function it_resolves_non_null_list_of_non_null_type_name(): void
	{
		$result = $this->registry->resolveTypeName([
			'kind' => 'NON_NULL',
			'name' => null,
			'ofType' => [
				'kind' => 'LIST',
				'name' => null,
				'ofType' => [
					'kind' => 'NON_NULL',
					'name' => null,
					'ofType' => ['kind' => 'OBJECT', 'name' => 'Product', 'ofType' => null],
				],
			],
		]);

		self::assertSame('[Product!]!', $result);
	}

	#[Test]
	public function it_resolves_type_ref_with_flags(): void
	{
		$result = $this->registry->resolveTypeRef([
			'kind' => 'NON_NULL',
			'name' => null,
			'ofType' => [
				'kind' => 'LIST',
				'name' => null,
				'ofType' => [
					'kind' => 'NON_NULL',
					'name' => null,
					'ofType' => ['kind' => 'OBJECT', 'name' => 'Product', 'ofType' => null],
				],
			],
		]);

		self::assertSame('Product', $result['name']);
		self::assertSame('OBJECT', $result['kind']);
		self::assertTrue($result['isList']);
		self::assertTrue($result['isNonNull']);
	}

	#[Test]
	public function it_resolves_nullable_scalar_ref(): void
	{
		$result = $this->registry->resolveTypeRef([
			'kind' => 'SCALAR',
			'name' => 'String',
			'ofType' => null,
		]);

		self::assertSame('String', $result['name']);
		self::assertSame('SCALAR', $result['kind']);
		self::assertFalse($result['isList']);
		self::assertFalse($result['isNonNull']);
	}

	#[Test]
	public function it_reloads_schema(): void
	{
		$this->registry->reload();

		$types = $this->registry->getTypes();

		self::assertGreaterThan(0, count($types));
	}

	#[Test]
	public function it_returns_source(): void
	{
		$source = $this->registry->getSource();

		self::assertStringContainsString('test.myshopify.com', $source);
		self::assertStringContainsString('2025-01', $source);
	}
}
