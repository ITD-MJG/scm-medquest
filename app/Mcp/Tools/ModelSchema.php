<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use ReflectionClass;

#[Description('Introspect a project model class: table, fillable/guarded fields, casts, enums, and relation methods. Call before any query or write against that model.')]
#[IsReadOnly]
class ModelSchema extends Tool
{
    /** @var array<int, string> */
    protected array $allowedModels = [
        'ApprovalFlow', 'ApprovalFlowInstance', 'ApprovalFlowStep', 'Batch',
        'Carrier', 'Department', 'DocumentSequence', 'DocumentSignoff',
        'IncomingReceipt', 'IncomingReceiptLine', 'Location', 'NotificationLog',
        'NotificationSetting', 'Principal', 'Product', 'Room',
        'RoomTemperature', 'SapConnection', 'SerialNumber', 'Supplier',
        'TemperatureDeviation', 'TemperatureHumidity', 'User',
    ];

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'model' => ['required', 'string', 'in:'.implode(',', $this->allowedModels)],
        ], [
            'model.in' => 'Unknown model. Allowed: '.implode(', ', $this->allowedModels),
        ]);

        $class = "App\\Models\\{$validated['model']}";

        $model = new $class;
        $reflection = new ReflectionClass($model);

        $enums = [];
        foreach ($model->getCasts() as $field => $cast) {
            if (enum_exists($cast)) {
                $enums[$field] = array_column($cast::cases(), 'value');
            }
        }

        $relations = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $return = (string) $method->getReturnType();
            if (str_contains($return, 'Relation')) {
                $relations[$method->getName()] = $return;
            }
        }

        return Response::structured([
            'model' => $class,
            'table' => $model->getTable(),
            'fillable' => $model->getFillable(),
            'guarded' => $model->getGuarded(),
            'casts' => $model->getCasts(),
            'enum_values' => $enums,
            'relations' => $relations,
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
                ->description('Model class name, e.g. "IncomingReceipt" or "TemperatureHumidity".')
                ->required(),
        ];
    }
}
