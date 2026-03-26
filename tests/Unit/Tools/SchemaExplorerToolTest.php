<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Config;
use App\SchemaRegistry;
use App\Tests\FakeGraphQLClient;
use App\Tools\SchemaExplorerTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaExplorerTool::class)]
final class SchemaExplorerToolTest extends TestCase
{
	private SchemaExplorerTool $tool;

	protected function setUp(): void
	{
		putenv('SHOPIFY_STORE=test.myshopify.com');
		putenv('SHOPIFY_ACCESS_TOKEN=shpat_test');
		putenv('SHOPIFY_API_VERSION=2025-01');

		$config = Config::fromEnv();
		$registry = new SchemaRegistry($config, new FakeGraphQLClient());
		$this->tool = new SchemaExplorerTool($registry);
	}

	#[Test]
	public function it_lists_queries_with_default_pagination(): void
	{
		$result = $this->tool->listQueries();

		self::assertArrayHasKey('items', $result);
		self::assertArrayHasKey('total', $result);
		self::assertArrayHasKey('offset', $result);
		self::assertArrayHasKey('limit', $result);
		self::assertGreaterThan(0, $result['total']);
		self::assertLessThanOrEqual(20, count($result['items']));
	}

	#[Test]
	public function it_respects_pagination_offset_and_limit(): void
	{
		$result = $this->tool->listQueries(offset: 0, limit: 5);

		self::assertCount(5, $result['items']);
		self::assertSame(0, $result['offset']);
		self::assertSame(5, $result['limit']);
	}

	#[Test]
	public function it_includes_name_and_return_type_in_query_items(): void
	{
		$result = $this->tool->listQueries(limit: 3);

		foreach ($result['items'] as $item) {
			self::assertArrayHasKey('name', $item);
			self::assertArrayHasKey('returnType', $item);
			self::assertArrayHasKey('description', $item);
			self::assertNotEmpty($item['name']);
			self::assertIsString($item['returnType']);
		}
	}

	#[Test]
	public function it_filters_queries_by_name(): void
	{
		$result = $this->tool->listQueries(filter: 'product');

		self::assertGreaterThan(0, count($result['items']));

		foreach ($result['items'] as $item) {
			self::assertStringContainsStringIgnoringCase('product', $item['name']);
		}
	}

	#[Test]
	public function it_lists_mutations_with_default_pagination(): void
	{
		$result = $this->tool->listMutations();

		self::assertGreaterThan(0, $result['total']);
		self::assertLessThanOrEqual(20, count($result['items']));
	}

	#[Test]
	public function it_filters_mutations_by_name(): void
	{
		$result = $this->tool->listMutations(filter: 'create');

		self::assertGreaterThan(0, count($result['items']));

		foreach ($result['items'] as $item) {
			self::assertStringContainsStringIgnoringCase('create', $item['name']);
		}
	}

	#[Test]
	public function it_returns_empty_for_blank_search(): void
	{
		$result = $this->tool->search('');

		self::assertSame([], $result);
	}

	#[Test]
	public function it_searches_types_by_name(): void
	{
		$result = $this->tool->search('Product');

		self::assertArrayHasKey('types', $result);
		self::assertGreaterThan(0, count($result['types']));
	}

	#[Test]
	public function it_searches_types_by_description(): void
	{
		$result = $this->tool->search('abandoned');

		self::assertArrayHasKey('types', $result);
		$found = false;

		foreach ($result['types'] as $type) {
			if (mb_stripos($type['name'], 'Abandoned') !== false) {
				$found = true;

				break;
			}
		}

		self::assertTrue($found, 'Should find AbandonedCheckout via description search');
	}

	#[Test]
	public function it_searches_fields_by_name(): void
	{
		$result = $this->tool->search('id');

		self::assertArrayHasKey('fields', $result);
		self::assertGreaterThan(0, count($result['fields']));

		$first = $result['fields'][0];

		self::assertArrayHasKey('type', $first);
		self::assertArrayHasKey('name', $first);
		self::assertArrayHasKey('returnType', $first);
	}

	#[Test]
	public function it_excludes_internal_types_from_search(): void
	{
		$result = $this->tool->search('Type');

		foreach ($result['types'] as $type) {
			self::assertStringStartsNotWith('__', $type['name']);
		}
	}

	#[Test]
	public function it_returns_empty_results_for_nonexistent_term(): void
	{
		$result = $this->tool->search('__ZZZ_NONEXISTENT_XYZ__');

		self::assertSame([], $result['types']);
		self::assertSame([], $result['fields']);
	}

	#[Test]
	public function it_clears_cache_and_returns_ok(): void
	{
		$result = $this->tool->clearCache();

		self::assertSame('ok', $result['status']);
		self::assertArrayHasKey('source', $result);
	}

	#[Test]
	public function it_truncates_long_descriptions(): void
	{
		$result = $this->tool->listQueries(limit: 50);

		foreach ($result['items'] as $item) {
			if ($item['description'] !== '') {
				self::assertLessThanOrEqual(123, mb_strlen($item['description']), 'Description should be truncated');
			}
		}
	}
}
