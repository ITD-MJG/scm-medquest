<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read-only SQL (SELECT/SHOW/DESCRIBE/EXPLAIN only) with a hard row and cell-size limit. Use for aggregates, joins, and reports. Prefer record-lookup for simple row fetches.')]
#[IsReadOnly]
class DbQuery extends Tool
{
    protected int $maxRows = 100;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sql' => ['required', 'string', 'max:5000'],
        ]);

        $sql = trim($validated['sql']);

        if (! preg_match('/^\s*(select|show|describe|desc|explain)\b/i', $sql)) {
            return Response::error('Only SELECT, SHOW, DESCRIBE, and EXPLAIN statements are allowed. Use record-upsert for writes.');
        }

        if (preg_match('/\b(insert|update|delete|drop|alter|truncate|create|grant|set|call)\b/i', $sql)) {
            return Response::error('Data-changing keywords detected in query. Only read statements are allowed.');
        }

        $rows = collect(DB::select($sql))
            ->take($this->maxRows + 1)
            ->map(fn ($row) => (array) $row);

        $truncated = $rows->count() > $this->maxRows;
        $rows = $rows->take($this->maxRows);

        return Response::structured([
            'row_count' => $rows->count(),
            'truncated' => $truncated,
            'hint' => $truncated ? "Result truncated to {$this->maxRows} rows. Add WHERE/LIMIT or select fewer columns." : null,
            'rows' => $rows->all(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()
                ->description('A single read-only SQL statement against the default connection.')
                ->required(),
        ];
    }
}
