# MCP Shopify GraphQL Introspection Server

> 🇧🇷 [Leia em Português](docs/README.pt-BR.md)

A PHP MCP (Model Context Protocol) server optimized for LLM usage. Provides granular, paginated tools for inspecting Shopify's GraphQL Admin API schema, exploring types, and building queries — designed to return compact, readable data instead of raw introspection dumps.

## Project Structure

```
graphql/
├── src/
│   ├── Config.php                      # environment-based configuration
│   ├── GraphQLClient.php               # Guzzle HTTP client for GraphQL
│   ├── GraphQLClientInterface.php      # client contract
│   ├── SchemaRegistry.php              # schema loading, type resolution, flatten helpers
│   └── Tools/
│       ├── SchemaExplorerTool.php      # list queries/mutations, search, cache
│       ├── TypeInspectorTool.php       # inspect types, enums, inputs, connections
│       └── QueryBuilderTool.php        # query/mutation details, skeleton builder
├── tests/
│   ├── FakeGraphQLClient.php           # test double for GraphQLClientInterface
│   ├── fixtures/
│   │   └── schema.json                 # static schema for tests
│   └── Unit/
│       ├── SchemaRegistryTest.php
│       └── Tools/
│           ├── SchemaExplorerToolTest.php
│           ├── TypeInspectorToolTest.php
│           └── QueryBuilderToolTest.php
├── var/
│   ├── cache/                          # on-disk schema cache (gitignored)
│   └── logs/                           # runtime logs (gitignored)
├── composer.json
└── server.php                          # STDIO entrypoint
```

## Requirements

- PHP 8.5 or higher
- Composer

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
| `SHOPIFY_STORE` | — | Shopify store domain (e.g. `my-store.myshopify.com`) |
| `SHOPIFY_ACCESS_TOKEN` | — | Shopify Admin API access token |
| `SHOPIFY_API_VERSION` | `2025-01` | Shopify Admin API version |

## Usage

### Start Server (Stdio)

```bash
php server.php
# or via Composer script
composer serve
```

The server uses **STDIO** transport — it reads JSON-RPC messages from `stdin`.

### Configure in Claude Desktop

```json
{
  "mcpServers": {
    "graphql": {
      "command": "php",
      "args": ["/absolute/path/to/server.php"],
      "env": {
        "SHOPIFY_STORE": "my-store.myshopify.com",
        "SHOPIFY_ACCESS_TOKEN": "shpat_xxxxxxxxxxxxxxxxxxxx"
      }
    }
  }
}
```

### Configure in VS Code

Add to `.vscode/mcp.json`:

```json
{
  "servers": {
    "ShopifyGraphQLIntrospection": {
      "type": "stdio",
      "command": "php",
      "args": ["-dxdebug.mode=off", "server.php"],
      "cwd": "/absolute/path/to/graphql",
      "env": {
        "SHOPIFY_STORE": "my-store.myshopify.com",
        "SHOPIFY_ACCESS_TOKEN": "shpat_xxxxxxxxxxxxxxxxxxxx"
      }
    }
  }
}
```

## Available Tools

### Schema Explorer

| Tool | Parameters | Description |
|---|---|---|
| `listQueries` | `offset`, `limit`, `filter` | List queries with pagination and optional name filter |
| `listMutations` | `offset`, `limit`, `filter` | List mutations with pagination and optional name filter |
| `search` | `term: string` | Search types and fields by name or description (max 50 results/category) |
| `clearCache` | — | Reload schema from endpoint or file |

### Type Inspector

| Tool | Parameters | Description |
|---|---|---|
| `listTypes` | `kind`, `offset`, `limit` | List types filtered by kind (OBJECT, ENUM, INPUT_OBJECT, etc.) |
| `getType` | `name: string` | Get full type definition with readable field types |
| `getEnumValues` | `name: string` | Get all values of an ENUM type |
| `getInputFields` | `name: string` | Get all fields of an INPUT_OBJECT type |
| `getFieldDetails` | `typeName`, `fieldName` | Get full details of a specific field with arguments |
| `getConnections` | `typeName: string` | List connection/pagination fields (Relay pattern) |

### Query Builder

| Tool | Parameters | Description |
|---|---|---|
| `getQueryDetails` | `name: string` | Get full details of a query including args and return type fields |
| `getMutationDetails` | `name: string` | Get full details of a mutation including args and return type fields |
| `buildQuerySkeleton` | `operationName`, `operationType` | Generate a GraphQL query/mutation skeleton ready to adapt |
| `getRequiredArgs` | `operationName`, `operationType` | List only required (non-null) arguments for an operation |

## Testing

```bash
composer test
```

## Static Analysis

```bash
composer analyse
```

## Security

- The access token is never logged or exposed in tool responses.
- Schema is fetched once at startup and cached; no live queries are executed during tool calls.
- Cache files are stored locally under `var/cache/` and excluded from version control.
