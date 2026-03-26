# MCP Shopify GraphQL Introspection Server

> 🇺🇸 [Read in English](../README.md)

Servidor MCP (Model Context Protocol) em PHP otimizado para uso por LLMs. Fornece ferramentas granulares e paginadas para inspecionar o schema da Shopify GraphQL Admin API, explorar tipos e construir queries — projetado para retornar dados compactos e legíveis em vez de dumps brutos de introspecção.

## Estrutura do Projeto

```
graphql/
├── src/
│   ├── Config.php                      # configuração via variáveis de ambiente
│   ├── GraphQLClient.php               # cliente HTTP Guzzle para GraphQL
│   ├── GraphQLClientInterface.php      # contrato do cliente
│   ├── SchemaRegistry.php              # carregamento de schema, resolução de tipos, helpers
│   └── Tools/
│       ├── SchemaExplorerTool.php      # listar queries/mutations, buscar, cache
│       ├── TypeInspectorTool.php       # inspecionar tipos, enums, inputs, conexões
│       └── QueryBuilderTool.php        # detalhes de query/mutation, gerador de esqueleto
├── tests/
│   ├── FakeGraphQLClient.php           # test double para GraphQLClientInterface
│   ├── fixtures/
│   │   └── schema.json                 # schema estático para testes
│   └── Unit/
│       ├── SchemaRegistryTest.php
│       └── Tools/
│           ├── SchemaExplorerToolTest.php
│           ├── TypeInspectorToolTest.php
│           └── QueryBuilderToolTest.php
├── var/
│   ├── cache/                          # cache de schema em disco (gitignored)
│   └── logs/                           # logs gerados em runtime (gitignored)
├── composer.json
└── server.php                          # entrypoint STDIO
```

## Requisitos

- PHP 8.5 ou superior
- Composer

## Instalação

```bash
composer install
```

Copie `.env.example` para `.env` e preencha os valores necessários:

```bash
cp .env.example .env
```

## Configuração

| Variável de ambiente | Padrão | Descrição |
|---|---|---|
| `SHOPIFY_STORE` | — | Domínio da loja Shopify (ex: `minha-loja.myshopify.com`) |
| `SHOPIFY_ACCESS_TOKEN` | — | Access token da Shopify Admin API |
| `SHOPIFY_API_VERSION` | `2025-01` | Versão da Shopify Admin API |

## Uso

### Iniciar Servidor (Stdio)

```bash
php server.php
# ou via script do Composer
composer serve
```

O servidor usa transporte **STDIO** — lê mensagens JSON-RPC do `stdin`.

### Configurar no Claude Desktop

```json
{
  "mcpServers": {
    "graphql": {
      "command": "php",
      "args": ["/caminho/absoluto/para/server.php"],
      "env": {
        "SHOPIFY_STORE": "minha-loja.myshopify.com",
        "SHOPIFY_ACCESS_TOKEN": "shpat_xxxxxxxxxxxxxxxxxxxx"
      }
    }
  }
}
```

### Configurar no VS Code

Adicione ao `.vscode/mcp.json`:

```json
{
  "servers": {
    "ShopifyGraphQLIntrospection": {
      "type": "stdio",
      "command": "php",
      "args": ["-dxdebug.mode=off", "server.php"],
      "cwd": "/caminho/absoluto/para/graphql",
      "env": {
        "SHOPIFY_STORE": "minha-loja.myshopify.com",
        "SHOPIFY_ACCESS_TOKEN": "shpat_xxxxxxxxxxxxxxxxxxxx"
      }
    }
  }
}
```

## Ferramentas disponíveis

### Schema Explorer

| Ferramenta | Parâmetros | Descrição |
|---|---|---|
| `listQueries` | `offset`, `limit`, `filter` | Lista queries com paginação e filtro opcional por nome |
| `listMutations` | `offset`, `limit`, `filter` | Lista mutations com paginação e filtro opcional por nome |
| `search` | `term: string` | Busca tipos e campos por nome ou descrição (máx. 50 resultados/categoria) |
| `clearCache` | — | Recarrega schema do endpoint ou arquivo |

### Type Inspector

| Ferramenta | Parâmetros | Descrição |
|---|---|---|
| `listTypes` | `kind`, `offset`, `limit` | Lista tipos filtrados por kind (OBJECT, ENUM, INPUT_OBJECT, etc.) |
| `getType` | `name: string` | Retorna definição completa do tipo com tipos de campo legíveis |
| `getEnumValues` | `name: string` | Retorna todos os valores de um tipo ENUM |
| `getInputFields` | `name: string` | Retorna todos os campos de um tipo INPUT_OBJECT |
| `getFieldDetails` | `typeName`, `fieldName` | Retorna detalhes completos de um campo específico com argumentos |
| `getConnections` | `typeName: string` | Lista campos de conexão/paginação (padrão Relay) |

### Query Builder

| Ferramenta | Parâmetros | Descrição |
|---|---|---|
| `getQueryDetails` | `name: string` | Detalhes completos de uma query incluindo args e campos do tipo de retorno |
| `getMutationDetails` | `name: string` | Detalhes completos de uma mutation incluindo args e campos do tipo de retorno |
| `buildQuerySkeleton` | `operationName`, `operationType` | Gera esqueleto de query/mutation GraphQL pronto para adaptar |
| `getRequiredArgs` | `operationName`, `operationType` | Lista apenas argumentos obrigatórios (non-null) de uma operação |

## Testes

```bash
composer test
```

## Análise Estática

```bash
composer analyse
```

## Segurança

- O access token nunca é registrado em logs nem exposto nas respostas das ferramentas.
- O schema é buscado uma única vez na inicialização e armazenado em cache; nenhuma query ao vivo é executada durante as chamadas de ferramenta.
- Os arquivos de cache são armazenados localmente em `var/cache/` e excluídos do controle de versão.
