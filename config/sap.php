<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | SAP connection
    |--------------------------------------------------------------------------
    | Name of the database connection (config/database.php) used to reach the
    | client's SAP instance. Defaults to the app's default connection for now.
    */
    'connection' => env('SAP_DB_CONNECTION', env('DB_CONNECTION')),

    /*
    |--------------------------------------------------------------------------
    | Import enabled
    |--------------------------------------------------------------------------
    | The import only runs when explicitly enabled. When disabled the service
    | returns an empty result and the artisan command exits 0 — a disabled
    | import is a normal state, not an error.
    */
    'enabled' => env('SAP_IMPORT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Rows (DELIBERATE PLACEHOLDER — outstanding decision)
    |--------------------------------------------------------------------------
    | OUTSTANDING: the client's SAP table list has not been provided yet
    | (spec §10 item 5), so the mapping from SAP tables/columns to the
    | product rows below is EMPTY on purpose. Once the table list arrives,
    | populate `mapping` and have production code query `connection` with it.
    | Until then `import()` reads `rows` directly (tests inject rows here).
    */
    'mapping' => [],

    'rows' => [],

];
