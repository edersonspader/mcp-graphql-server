<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Config;
use App\SchemaRegistry;
use App\Tests\FakeGraphQLClient;
use App\Tools\TypeInspectorTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeInspectorTool::class)]
final class TypeInspectorToolTest extends TestCase
{
	private TypeInspectorTool $tool;

	protected function setUp(): void
	{
		putenv('SHOPIFY_STORE=test.myshopify.com');
		putenv('SHOPIFY_ACCESS_TOKEN=shpat_test');
		putenv('SHOPIFY_API_VERSION=2025-01');

		$config = Config::fromEnv();
		$registry = new SchemaRegistry($config, new FakeGraphQLClient());
		$this->tool = new TypeInspectorTool($registry);
	}

	#[Test]
	public function it_lists_all_types_with_pagination(): void
	{
		$result = $this->tool->listTypes();

		self::assertArrayHasKey('items', $result);
		self::assertArrayHasKey('total', $result);
		self::assertGreaterThan(0, $result['total']);
		self::assertLessThanOrEqual(30, count($result['items']));
	}

	#[Test]
	public function it_filters_types_by_kind(): void
	{
		$result = $this->tool->listTypes(kind: 'ENUM');

		self::assertGreaterThan(0, $result['total']);

		foreach ($result['items'] as $item) {
			self::assertSame('ENUM', $item['kind']);
		}
	}

	#[Test]
	public function it_filters_input_objects(): void
	{
		$result = $this->tool->listTypes(kind: 'INPUT_OBJECT');

		self::assertGreaterThan(0, $result['total']);

		foreach ($result['items'] as $item) {
			self::assertSame('INPUT_OBJECT', $item['kind']);
		}
	}

	#[Test]
	public function it_excludes_internal_types(): void
	{
		$result = $this->tool->listTypes(limit: 2000);

		foreach ($result['items'] as $item) {
			self::assertStringStartsNotWith('__', $item['name']);
		}
	}

	#[Test]
	public function it_respects_pagination(): void
	{
		$result = $this->tool->listTypes(offset: 0, limit: 5);

		self::assertCount(5, $result['items']);
		self::assertSame(0, $result['offset']);
		self::assertSame(5, $result['limit']);
	}

	#[Test]
	public function it_gets_type_by_name(): void
	{
		$result = $this->tool->getType('Product');

		self::assertSame('Product', $result['name']);
		self::assertSame('OBJECT', $result['kind']);
		self::assertArrayHasKey('fields', $result);
		self::assertGreaterThan(0, count($result['fields']));
	}

	#[Test]
	public function it_returns_flatten_field_types(): void
	{
		$result = $this->tool->getType('Product');

		foreach ($result['fields'] as $field) {
			self::assertIsString($field['type'], 'Field type should be a readable string');
			self::assertArrayHasKey('isDeprecated', $field);
		}
	}

	#[Test]
	public function it_throws_on_empty_type_name(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		$this->tool->getType('');
	}

	#[Test]
	public function it_throws_on_unknown_type(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->tool->getType('__NonExistentType__');
	}

	#[Test]
	public function it_gets_enum_values(): void
	{
		$result = $this->tool->getEnumValues('CountryCode');

		self::assertGreaterThan(0, count($result));

		$first = $result[0];

		self::assertArrayHasKey('name', $first);
		self::assertArrayHasKey('description', $first);
		self::assertArrayHasKey('isDeprecated', $first);
	}

	#[Test]
	public function it_throws_on_enum_values_for_non_enum(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('is not an ENUM');

		$this->tool->getEnumValues('Product');
	}

	#[Test]
	public function it_throws_on_enum_values_for_unknown_type(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->tool->getEnumValues('__NonExistent__');
	}

	#[Test]
	public function it_gets_input_fields(): void
	{
		$result = $this->tool->getInputFields('ProductInput');

		self::assertGreaterThan(0, count($result));

		$first = $result[0];

		self::assertArrayHasKey('name', $first);
		self::assertArrayHasKey('type', $first);
		self::assertIsString($first['type']);
		self::assertArrayHasKey('description', $first);
	}

	#[Test]
	public function it_throws_on_input_fields_for_non_input_object(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('is not an INPUT_OBJECT');

		$this->tool->getInputFields('Product');
	}

	#[Test]
	public function it_gets_field_details_with_args(): void
	{
		$result = $this->tool->getFieldDetails('QueryRoot', 'product');

		self::assertSame('product', $result['name']);
		self::assertIsString($result['type']);
		self::assertArrayHasKey('args', $result);
		self::assertArrayHasKey('description', $result);
		self::assertArrayHasKey('isDeprecated', $result);

		if (count($result['args']) > 0) {
			$firstArg = $result['args'][0];

			self::assertArrayHasKey('name', $firstArg);
			self::assertArrayHasKey('type', $firstArg);
			self::assertIsString($firstArg['type']);
		}
	}

	#[Test]
	public function it_throws_on_field_details_with_empty_names(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		$this->tool->getFieldDetails('', 'id');
	}

	#[Test]
	public function it_throws_on_field_details_for_unknown_field(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Field not found');

		$this->tool->getFieldDetails('Product', '__nonExistentField__');
	}

	#[Test]
	public function it_gets_connections_for_type(): void
	{
		$result = $this->tool->getConnections('QueryRoot');

		self::assertGreaterThan(0, count($result));

		$first = $result[0];

		self::assertArrayHasKey('fieldName', $first);
		self::assertArrayHasKey('connectionType', $first);
		self::assertArrayHasKey('nodeType', $first);
		self::assertArrayHasKey('args', $first);
		self::assertStringContainsString('Connection', $first['connectionType']);
	}

	#[Test]
	public function it_resolves_node_type_for_connections(): void
	{
		$result = $this->tool->getConnections('QueryRoot');
		$found = false;

		foreach ($result as $conn) {
			if ($conn['nodeType'] !== null) {
				$found = true;

				break;
			}
		}

		self::assertTrue($found, 'At least one connection should have a resolved nodeType');
	}

	#[Test]
	public function it_throws_on_connections_for_unknown_type(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->tool->getConnections('__NonExistent__');
	}

	#[Test]
	public function it_returns_interfaces_for_type(): void
	{
		$result = $this->tool->getType('Product');

		if (isset($result['interfaces'])) {
			self::assertIsArray($result['interfaces']);

			foreach ($result['interfaces'] as $iface) {
				self::assertIsString($iface);
			}
		}
	}
}
