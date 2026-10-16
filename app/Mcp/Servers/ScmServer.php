<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\DbQuery;
use App\Mcp\Tools\ModelSchema;
use App\Mcp\Tools\RecordLookup;
use App\Mcp\Tools\RecordUpsert;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('scm')]
#[Version('0.1.0')]
#[Description('Supply chain management domain tools: schema, lookup, and mutations.')]
#[Instructions(<<<'TXT'
SCM domain server. Start with model-schema to learn a model's fields, casts, and relations
before calling other tools — never guess column names.

Read flow: model-schema -> record-lookup (or db-query for custom aggregates/reports).
Write flow: model-schema -> record-upsert. Writes bypass Filament policies; confirm with the
user before mutating. Use db-query only for SELECT/SHOW/DESCRIBE/EXPLAIN.
TXT)]
class ScmServer extends Server
{
    protected array $tools = [
        ModelSchema::class,
        RecordLookup::class,
        DbQuery::class,
        RecordUpsert::class,
    ];
}
