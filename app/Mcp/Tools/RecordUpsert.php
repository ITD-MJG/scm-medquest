<?php

namespace App\Mcp\Tools;

use App\Models\IncomingReceipt;
use App\Models\Product;
use App\Models\TemperatureHumidity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a record on a supported domain model (Product, IncomingReceipt, TemperatureHumidity). Requires a reason. Not read-only: run model-schema first, and only pass columns from that schema.')]
class RecordUpsert extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'model' => ['required', 'string', 'in:Product,IncomingReceipt,TemperatureHumidity'],
            'id' => ['nullable', 'integer'],
            'attributes' => ['required', 'array', 'min:1', 'max:30'],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'model.in' => 'Supported models: Product, IncomingReceipt, TemperatureHumidity. Extend RecordUpsert::$writable to add more.',
            'reason.required' => 'A reason is required for every mutation. State why this write is being made, e.g. "data entry from paper form #123" or "correcting typo in product description".',
        ]);

        $modelClass = match ($validated['model']) {
            'Product' => Product::class,
            'IncomingReceipt' => IncomingReceipt::class,
            'TemperatureHumidity' => TemperatureHumidity::class,
        };

        /** @var Model $model */
        $model = new $modelClass;

        $record = isset($validated['id'])
            ? $modelClass::findOrFail($validated['id'])
            : new $modelClass;

        // Domain convention: natural-key UUID columns (productId, receiptId, ...) are
        // assigned by create pages, not the model. Replicate that for agent writes.
        if (! $record->exists) {

            $uuidCandidates = ['productId', 'receiptId', 'temperatureId', 'roomTemperatureId', 'temperatureDeviationId'];
            foreach ($uuidCandidates as $candidate) {
                if (\Schema::hasColumn($model->getTable(), $candidate)) {
                    $record->{$candidate} = Str::orderedUuid();

                    break;
                }
            }
        }
        $record->fill($validated['attributes']);
        $record->save();

        Log::info('MCP record-upsert', [
            'tool' => 'record-upsert',
            'model' => $validated['model'],
            'id' => $record->getKey(),
            'attributes' => $validated['attributes'],
            'reason' => $validated['reason'],
        ]);

        return Response::structured([
            'saved' => true,
            'was_created' => $record->wasRecentlyCreated,
            'model' => $validated['model'],
            'id' => $record->getKey(),
            'reason' => $validated['reason'],
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
                ->description('Model to write to: Product, IncomingReceipt, or TemperatureHumidity.')
                ->required(),
            'id' => $schema->integer()
                ->description('Primary key to update. Omit to create a new record.'),
            'attributes' => $schema->object()
                ->additionalProperties($schema->string())
                ->description('Column => value pairs. Only pass columns that exist on the model (see model-schema).'),
            'reason' => $schema->string()
                ->description('Why this mutation is being made. Logged for audit.')
                ->required(),
        ];
    }
}
