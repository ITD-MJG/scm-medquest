<?php

use App\Mcp\Servers\ScmServer;
use App\Mcp\Tools\DbQuery;
use App\Mcp\Tools\ModelSchema;
use App\Mcp\Tools\RecordUpsert;
use App\Models\Product;

test('model-schema returns structured schema', function () {
    $response = ScmServer::tool(ModelSchema::class, ['model' => 'IncomingReceipt']);

    $response->assertOk();
    $response->assertStructuredContent(fn ($json) => $json->hasAll(['model', 'table', 'casts', 'enum_values', 'relations'])->etc());
});

test('model-schema rejects unknown model', function () {
    $response = ScmServer::tool(ModelSchema::class, ['model' => 'NotReal']);

    $response->assertHasErrors();
});

test('db-query blocks writes', function () {
    $response = ScmServer::tool(DbQuery::class, ['sql' => 'DELETE FROM products']);

    $response->assertHasErrors();
});

test('db-query runs select', function () {
    Product::factory()->count(2)->create();

    $response = ScmServer::tool(DbQuery::class, ['sql' => 'select count(*) as c from products']);

    $response->assertOk();
    $response->assertStructuredContent(fn ($json) => $json->where('row_count', 1)->etc());
});

test('record-upsert creates and requires reason', function () {
    $response = ScmServer::tool(RecordUpsert::class, ['model' => 'Product', 'attributes' => ['internal_code' => 'X1', 'description' => 'test', 'uom' => 'pcs'], 'reason' => 'unit test']);

    $response->assertOk();
    $response->assertStructuredContent(fn ($json) => $json->where('was_created', true)->etc());

    $noReason = ScmServer::tool(RecordUpsert::class, ['model' => 'Product', 'attributes' => ['internal_code' => 'X2']]);

    $noReason->assertHasErrors();
});
