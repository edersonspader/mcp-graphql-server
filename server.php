<?php

require __DIR__ . '/vendor/autoload.php';

use App\Cache\CacheFactory;
use App\Config;
use App\MCPGraphQLServer;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

$config = new Config();
$cache = CacheFactory::create($config);

$logger = new Logger('mcp-graphql');
$logger->pushHandler(new StreamHandler(__DIR__ . '/var/logs/mcp-graphql.log', Level::Debug));

$serverInstance = new MCPGraphQLServer($config, $cache);

$server = Server::builder()
	->setServerInfo('graphql-introspection-mcp', '1.0')
	->setLogger($logger)
	->addTool(
		fn() => $serverInstance->introspection(),
		'introspection',
		'Return full GraphQL introspection schema JSON',
		null,
		null
	)
	->addTool(
		fn() => $serverInstance->listQueries(),
		'listQueries',
		'List available Query fields (name + description)',
		null,
		null
	)
	->addTool(
		fn() => $serverInstance->listMutations(),
		'listMutations',
		'List available Mutation fields (name + description)',
		null,
		null
	)
	->addTool(
		function (string $name) use ($serverInstance): array|string {
			try {
				return $serverInstance->getType($name);
			} catch (\InvalidArgumentException $e) {
				return 'Invalid argument: ' . $e->getMessage();
			} catch (\RuntimeException $e) {
				return $e->getMessage();
			}
		},
		'getType',
		'Get full type definition by name',
		null,
		[
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Type name to retrieve']
			],
			'required' => ['name']
		]
	)
	->addTool(
		fn(string $term = '') => $serverInstance->search($term),
		'search',
		'Search types and fields by term',
		null,
		[
			'type' => 'object',
			'properties' => [
				'term' => ['type' => 'string', 'description' => 'Search term (partial name)']
			]
		]
	)
	->addTool(
		fn() => $serverInstance->clearCache(),
		'clearCache',
		'Clear the cached schema and reload from the GraphQL endpoint or local file',
		null,
		null
	)
	->build();

$server->run(new StdioTransport());
