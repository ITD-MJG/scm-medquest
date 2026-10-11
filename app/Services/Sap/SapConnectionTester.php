<?php

namespace App\Services\Sap;

use App\Models\SapConnection;
use Illuminate\Support\Facades\DB;

class SapConnectionTester
{
    /**
     * Attempt a real connection to the SAP database described by $connection.
     * Persists the test result on the record and returns the outcome message.
     */
    public function test(SapConnection $connection): string
    {
        $connection->forceFill([
            'last_tested_at' => now(),
        ]);

        try {
            config(['database.connections.sap_connection_test' => $this->configFor($connection)]);

            $pdo = DB::connection('sap_connection_test')->getPdo();

            DB::purge('sap_connection_test');

            $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) ?? 'unknown version';

            $connection->forceFill([
                'last_test_status' => 'success',
                'last_test_message' => "Connected. Server version: {$version}",
            ])->save();

            return $connection->last_test_message;
        } catch (\Throwable $e) {
            $connection->forceFill([
                'last_test_status' => 'failed',
                'last_test_message' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    private function configFor(SapConnection $connection): array
    {
        $config = collect([
            'driver' => $connection->driver,
            'host' => $connection->host,
            'port' => $connection->port,
            'database' => $connection->database,
            'username' => $connection->username,
            'password' => $connection->password,
            'prefix' => '',
        ])->filter(fn ($v) => $v !== null)->all();

        if ($connection->driver === 'sqlsrv') {
            $config['charset'] = 'utf8';
        } else {
            $config['charset'] = 'utf8mb4';
            $config['collation'] = 'utf8mb4_unicode_ci';
            $config['strict'] = true;
        }

        return $config;
    }
}
