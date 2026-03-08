# MCP GraphQL Introspection Server

> **[Leia em Português](README.pt.md)**

A PHP MCP (Model Context Protocol) server for inspecting GraphQL schemas and retrieving information about available queries, mutations, and types.

## Project Structure

```
graphql/
├── schema/
│   ├── schema.json               # cached __schema JSON response
│   └── introspection.graphql     # standard introspection query
├── src/
│   ├── Cache/                    # cache layer (FileCache, NullCache, CacheFactory)
│   ├── Config.php                # environment-based configuration
│   └── MCPGraphQLServer.php      # MCP tool logic
├── tests/
│   └── MCPGraphQLServerTest.php  # public method tests
├── var/
│   ├── cache/                    # on-disk schema cache
│   └── logs/                     # runtime logs
├── .env.example
├── composer.json
└── server.php                    # STDIO entrypoint
```

## Installation

```bash
composer install
```

Copy `.env.example` to `.env` and fill in the required values:

```bash
cp .env.example .env
```

## Configuration

| Environment variable | Default | Description |
|---|---|---|
| `GRAPHQL_URL` | — | GraphQL endpoint URL for live introspection |
| `GRAPHQL_TOKEN` | — | Bearer token for authentication (optional) |
| `CACHE_ENABLED` | `true` | Set to `false` to disable schema caching |
| `CACHE_TTL` | `3600` | Cache time-to-live in seconds |
| `INTROSPECTION_FILE` | `schema/schema.json` | Static schema file used when `GRAPHQL_URL` is not set |

## Running

```bash
php server.php
# or via Composer script
composer serve
```

The server uses **STDIO** transport — it reads JSON-RPC messages from `stdin`.

## Available Tools

| Tool | Parameters | Description |
|---|---|---|
| `introspection` | — | Returns the full `__schema` object |
| `listQueries` | — | Lists fields available on the `Query` type |
| `listMutations` | — | Lists fields available on the `Mutation` type |
| `getType` | `name: string` (required) | Returns the full definition of a type by name |
| `search` | `term: string` | Searches types and fields whose name contains the term |
| `clearCache` | — | Clears the cached schema and reloads it |

## Tests

```bash
composer test
```

## VS Code / GitHub Copilot Integration

Add to `settings.json`:

```json
{
  "github.copilot.chat.mcp.servers": {
    "graphql": {
      "command": "php",
      "args": ["server.php"],
      "cwd": "/app/mcp/graphql",
      "env": {
        "GRAPHQL_URL": "https://your-api.example.com/graphql",
        "GRAPHQL_TOKEN": "your-bearer-token"
      }
    }
  }
}
```

Use `INTROSPECTION_FILE` in `env` to point to a different static schema:

```json
"env": {
  "INTROSPECTION_FILE": "/path/to/another-schema.json"
}
```

## Security

- The Bearer token is never logged or exposed in tool responses.
- Schema is fetched once at startup and cached; no live queries are executed during tool calls.
- Cache files are stored locally under `var/cache/` and excluded from version control.
