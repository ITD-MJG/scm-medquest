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
            if ($connection->driver === 'hana') {
                $pdo = $this->hanaPdo($connection);
            } else {
                config(['database.connections.sap_connection_test' => $this->configFor($connection)]);

                $pdo = DB::connection('sap_connection_test')->getPdo();

                DB::purge('sap_connection_test');
            }
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
        if ($connection->driver === 'hana') {
            return $this->hanaConfigFor($connection);
        }

        $config = collect([
            'driver' => $connection->driver,
            'host' => $connection->host,
            'port' => $connection->port,
            'database' => $connection->database ?? ($connection->driver === 'sqlsrv' ? 'master' : null),
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

    /**
     * SAP HANA over PDO_ODBC using a DSN-less connection string.
     * Created directly — Laravel's connection factory has no odbc connector.
     * Requires the HANA ODBC driver (libodbcHDB.so) installed on this host.
     */
    private function hanaPdo(SapConnection $connection): \PDO
    {
        $dsn = sprintf(
            'odbc:DRIVER={HDBODBC};SERVERNODE=%s:%d;UID=%s;PWD=%s;CHARSET=utf8',
            $connection->host,
            $connection->port ?? 30015,
            $connection->username,
            $connection->password,
        );

        // unixODBC needs ODBCINST to find the HDBODBC driver registration;
        // fall back to the HANA client's own location when unset.
        if (getenv('ODBCINST') === false && getenv('ODBCINI') === false) {
            putenv('ODBCINST='.(is_file('/etc/odbcinst.ini')
                ? '/etc/odbcinst.ini'
                : $_SERVER['HOME'].'/.odbcinst.ini'));
        }

        return new \PDO($dsn, $connection->username, $connection->password);
    }
}
