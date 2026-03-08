<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Cache\FileCache;
use App\Cache\NullCache;
use App\Config;
use App\MCPGraphQLServer;

$pass = 0;
$fail = 0;

function assert_test(string $label, callable $fn): void
{
	global $pass, $fail;
	try {
		$result = $fn();
		if ($result === false) {
			echo "[FAIL] $label\n";
			$fail++;
		} else {
			echo "[OK]   $label\n";
			$pass++;
		}
	} catch (\Throwable $e) {
		echo "[FAIL] $label — " . $e->getMessage() . "\n";
		$fail++;
	}
}

// ── helpers ───────────────────────────────────────────────────────────────────
function makeServer(): MCPGraphQLServer
{
	// NullCache → no disk writes during tests; falls back to local introspection file
	return new MCPGraphQLServer(new Config(), new NullCache());
}

$server = makeServer();
$queryTypeName = $server->introspection()['queryType']['name'];

// ── introspection() ───────────────────────────────────────────────────────────
assert_test('introspection() returns array', fn() => is_array($server->introspection()));
assert_test('introspection() has queryType', fn() => !empty($server->introspection()['queryType']['name']));
assert_test('introspection() has types array', fn() => count($server->introspection()['types']) > 0);
assert_test('introspection() has directives', fn() => isset($server->introspection()['directives']));

// ── listQueries() ─────────────────────────────────────────────────────────────
assert_test('listQueries() returns non-empty array', fn() => count($server->listQueries()) > 0);
assert_test('listQueries() each item has name', function () use ($server) {
	foreach ($server->listQueries() as $q) {
		if (empty($q['name'])) return false;
	}
	return true;
});
assert_test('listQueries() each item has args key', function () use ($server) {
	foreach ($server->listQueries() as $q) {
		if (!array_key_exists('args', $q)) return false;
	}
	return true;
});
assert_test('listQueries() each item has type key', function () use ($server) {
	foreach ($server->listQueries() as $q) {
		if (!array_key_exists('type', $q)) return false;
	}
	return true;
});

// ── listMutations() ───────────────────────────────────────────────────────────
assert_test('listMutations() returns non-empty array', fn() => count($server->listMutations()) > 0);
assert_test('listMutations() each item has name', function () use ($server) {
	foreach ($server->listMutations() as $m) {
		if (empty($m['name'])) return false;
	}
	return true;
});

// ── getType() ─────────────────────────────────────────────────────────────────
assert_test("getType('$queryTypeName') returns correct type", function () use ($server, $queryTypeName) {
	$type = $server->getType($queryTypeName);
	return isset($type['name']) && $type['name'] === $queryTypeName;
});
assert_test('getType() result has fields', function () use ($server, $queryTypeName) {
	return count($server->getType($queryTypeName)['fields'] ?? []) > 0;
});
assert_test('getType() throws InvalidArgumentException on empty name', function () use ($server) {
	try {
		$server->getType('');
		return false;
	} catch (\InvalidArgumentException) {
		return true;
	}
});
assert_test('getType() throws RuntimeException on unknown type', function () use ($server) {
	try {
		$server->getType('__NonExistentType__');
		return false;
	} catch (\RuntimeException) {
		return true;
	}
});

// ── search() ──────────────────────────────────────────────────────────────────
assert_test("search('') returns []", fn() => $server->search('') === []);
assert_test("search() result has types+fields keys", fn() => array_key_exists('types', $server->search('id')) && array_key_exists('fields', $server->search('id')));
assert_test("search('String') finds types", fn() => count($server->search('String')['types']) > 0);
assert_test("search('id') finds fields named 'id'", function () use ($server) {
	foreach ($server->search('id')['fields'] as $f) {
		if (mb_stripos($f['name'], 'id') !== false) return true;
	}
	return false;
});
assert_test("search('__NonExistentXYZ__') is empty", function () use ($server) {
	$r = $server->search('__NonExistentXYZ__');
	return $r['types'] === [] && $r['fields'] === [];
});

// ── clearCache() — file cache round-trip ─────────────────────────────────────
$cacheDir = sys_get_temp_dir() . '/mcp_graphql_test_' . uniqid();
$fileCache = new FileCache($cacheDir);
$serverFC = new MCPGraphQLServer(new Config(), $fileCache);

assert_test('clearCache() returns ok status', function () use ($serverFC) {
	$r = $serverFC->clearCache();
	return ($r['status'] ?? '') === 'ok';
});
assert_test('clearCache() schema still valid after clear', function () use ($serverFC) {
	$serverFC->clearCache();
	return count($serverFC->listQueries()) > 0;
});
assert_test('FileCache: schema is written to disk', function () use ($cacheDir) {
	return count(glob($cacheDir . '/*.cache')) > 0;
});
assert_test('FileCache: cached schema is read on second instantiation', function () use ($cacheDir) {
	// Force a second server that reads from file cache (no URL, file should be hit)
	$config = new Config();
	$cache = new FileCache($cacheDir);
	$s2 = new MCPGraphQLServer($config, $cache);
	return count($s2->listQueries()) > 0;
});

// cleanup temp dir
array_map('unlink', glob($cacheDir . '/*.cache') ?: []);
@rmdir($cacheDir);

// ── summary ───────────────────────────────────────────────────────────────────
$total = $pass + $fail;
echo "\n$pass/$total passed" . ($fail > 0 ? " — {$fail} FAILED" : ' ✓') . "\n";
exit($fail > 0 ? 1 : 0);
