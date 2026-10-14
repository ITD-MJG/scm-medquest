<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Browsershot node binary
    |--------------------------------------------------------------------------
    | The php-fpm worker process has no PATH (clear_env), so Browsershot's
    | bare "node" lookup fails with exit code 127. Resolve the binary
    | explicitly; override with NODE_BINARY in .env when the host differs.
    */
    'node_binary' => env('NODE_BINARY', '/home/linuxbrew/.linuxbrew/bin/node'),

];
