<?php

namespace App\Mcp\Tools;

use App\Models\IncomingReceipt;
use App\Models\Product;
use App\Models\TemperatureHumidity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Fetch records from a domain model with optional filters, ordering, and eager-loaded relations. Returns compact rows (id + requested columns). Use model-schema first to learn valid columns and relations.')]
#[IsReadOnly]
class RecordLookup extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'model' => ['required', 'string', 'in:IncomingReceipt,TemperatureHumidity,Product'],
            'id' => ['nullable', 'integer'],
            'filters' => ['nullable', 'array', 'max:10'],
            'columns' => ['nullable', 'array', 'max:20'],
            'with' => ['nullable', 'array', 'max:5'],
            'order_by' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'model.in' => 'Supported models: IncomingReceipt, TemperatureHumidity, Product. Extend RecordLookup::$models to add more.',
        ]);

        /** @var Model $model */
        $model = match ($validated['model']) {
            'IncomingReceipt' => IncomingReceipt::class,
            'TemperatureHumidity' => TemperatureHumidity::class,
            'Product' => Product::class,
        };

        $query = $model::query()->select($validated['columns'] ?? ['*']);

        foreach ($validated['filters'] ?? [] as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);

                continue;
            }

            $query->where($column, is_array($value) ? $value : '=', $value);
        }

        if (isset($validated['id'])) {
            $query->whereKey($validated['id']);
        }

        if (! empty($validated['with'])) {
            $query->with($validated['with']);
        }

        $rows = $query
            ->orderBy($validated['order_by'] ?? $query->getModel()->getQualifiedKeyName(), $validated['direction'] ?? 'asc')
            ->limit($validated['limit'] ?? 50)
            ->get();

        return Response::structured([
            'count' => $rows->count(),
            'records' => $rows->toArray(),
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
            'model' => $schema->string()
                ->description('Model to query: IncomingReceipt, TemperatureHumidity, or Product.')
                ->required(),
            'id' => $schema->integer()
                ->description('Primary key of a single record to fetch. Omits filters when set.'),
            'filters' => $schema->object()
                ->additionalProperties($schema->string())
                ->description('Column => value equality filters. Use JSON null for WHERE NULL. Arrays become WHERE IN.'),
            'columns' => $schema->array()
                ->items($schema->string())
                ->description('Columns to select. Default: all. Prefer explicit lists to save tokens.'),
            'with' => $schema->array()
                ->items($schema->string())
                ->description('Relation names to eager load.'),
            'order_by' => $schema->string()
                ->description('Column to order by.'),
            'direction' => $schema->string()
                ->enum(['asc', 'desc']),
            'limit' => $schema->integer()
                ->description('Max rows (default 50, hard cap 100).'),
        ];
    }
}
