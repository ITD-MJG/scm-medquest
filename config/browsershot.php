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

    /*
    |--------------------------------------------------------------------------
    | Chrome binary
    |--------------------------------------------------------------------------
    | Leave null to let puppeteer resolve its own downloaded Chrome from the
    | cache dir (local machines). On hosts where puppeteer's cache is broken
    | or incomplete, point CHROME_PATH at a system Chrome instead — this skips
    | puppeteer's per-project browser download entirely.
    */
    'chrome_path' => env('CHROME_PATH'),

];
