# MCP GraphQL Introspection Server

> **[Read in English](README.md)**

Servidor MCP (Model Context Protocol) em PHP para inspecionar schemas GraphQL e recuperar informações sobre consultas, mutações e tipos disponíveis.

## Estrutura do Projeto

```
graphql/
├── schema/
│   ├── schema.json               # resposta JSON do __schema em cache
│   └── introspection.graphql     # query de introspecção padrão
├── src/
│   ├── Cache/                    # camada de cache (FileCache, NullCache, CacheFactory)
│   ├── Config.php                # configuração via variáveis de ambiente
│   └── MCPGraphQLServer.php      # lógica das ferramentas MCP
├── tests/
│   └── MCPGraphQLServerTest.php  # testes dos métodos públicos
├── var/
│   ├── cache/                    # cache de schema em disco
│   └── logs/                     # logs gerados em runtime
├── .env.example
├── composer.json
└── server.php                    # entrypoint STDIO
```

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
| `GRAPHQL_URL` | — | URL do endpoint GraphQL para introspecção ao vivo |
| `GRAPHQL_TOKEN` | — | Bearer token para autenticação (opcional) |
| `CACHE_ENABLED` | `true` | `false` desativa o cache de schema |
| `CACHE_TTL` | `3600` | Tempo de vida do cache em segundos |
| `INTROSPECTION_FILE` | `schema/schema.json` | Schema estático (usado quando `GRAPHQL_URL` não está definida) |

## Execução

```bash
php server.php
# ou via script do Composer
composer serve
```

O servidor usa transporte **STDIO** — lê mensagens JSON-RPC do `stdin`.

## Ferramentas disponíveis

| Ferramenta | Parâmetros | Descrição |
|---|---|---|
| `introspection` | — | Retorna o objeto `__schema` completo |
| `listQueries` | — | Lista campos disponíveis no tipo `Query` |
| `listMutations` | — | Lista campos disponíveis no tipo `Mutation` |
| `getType` | `name: string` (obrigatório) | Retorna a definição completa de um tipo pelo nome |
| `search` | `term: string` | Busca tipos e campos cujo nome contenha o termo |
| `clearCache` | — | Limpa o cache e recarrega o schema |

## Testes

```bash
composer test
```

## Integração com VS Code / GitHub Copilot

Adicione ao `settings.json`:

```json
{
  "github.copilot.chat.mcp.servers": {
    "graphql": {
      "command": "php",
      "args": ["server.php"],
      "cwd": "/app/mcp/graphql",
      "env": {
        "GRAPHQL_URL": "https://sua-api.exemplo.com/graphql",
        "GRAPHQL_TOKEN": "seu-bearer-token"
      }
    }
  }
}
```

Use `INTROSPECTION_FILE` em `env` para apontar para um schema estático diferente:

```json
"env": {
  "INTROSPECTION_FILE": "/caminho/para/outro-schema.json"
}
```

## Segurança

- O Bearer token nunca é registrado em logs nem exposto nas respostas das ferramentas.
- O schema é buscado uma única vez na inicialização e armazenado em cache; nenhuma query ao vivo é executada durante as chamadas de ferramenta.
- Os arquivos de cache são armazenados localmente em `var/cache/` e excluídos do controle de versão.
