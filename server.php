#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Config;
use App\ShopifyClient;
use App\SchemaRegistry;
use Dotenv\Dotenv;
use Mcp\Capability\Registry\Container;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

if (is_file(__DIR__ . '/.env')) {
	Dotenv::createImmutable(__DIR__)->safeLoad();
}

$config = Config::fromEnv();
$client = new ShopifyClient($config);
$registry = new SchemaRegistry($config, $client);

$logger = new Logger('mcp');
$logger->pushHandler(new StreamHandler(__DIR__ . '/var/logs/mcp.log', Level::Debug));

$container = new Container();
$container->set(Config::class, $config);
$container->set(ShopifyClient::class, $client);
$container->set(SchemaRegistry::class, $registry);
$container->set(LoggerInterface::class, $logger);

$discoveryCache = new Psr16Cache(new FilesystemAdapter('mcp-discovery', 3600, __DIR__ . '/var/cache'));

$server = Server::builder()
	->setServerInfo('graphql-introspection-mcp', '1.0.0')
	->setLogger($logger)
	->setContainer($container)
	->setDiscovery(
		basePath: __DIR__,
		scanDirs: ['src/Tools'],
		cache: $discoveryCache,
	)
	->build();

$server->run(new StdioTransport());
