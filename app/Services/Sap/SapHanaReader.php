<?php

declare(strict_types=1);

namespace App\Services\Sap;

use App\Models\SapConnection;
use InvalidArgumentException;

/**
 * Reads product/principal rows from a live SAP Business One HANA database
 * through the ODBC driver registered on this machine.
 *
 * Uses PHP's raw odbc_* functions: PDO_ODBC's buffered-fetch path fails with
 * "No columns were bound prior to calling SQLFetch" against HDBODBC.
 */
class SapHanaReader
{
    private readonly SapConnection $sapConnection;

    /** @var resource|null */
    private $connection = null;

    public function __construct(SapConnection $connection, private readonly string $schema = 'MJG_LIVE')
    {
        if ($connection->driver !== 'hana') {
            throw new InvalidArgumentException('SapHanaReader only supports hana driver connections.');
        }

        $this->sapConnection = $connection;
    }

    /**
     * NVARCHAR columns are fetched as server-side hex (BINTOHEX(TO_VARBINARY(...)))
     * and decoded locally. HDBODBC's SQLExtendedFetch path fails mid-result-set
     * with -10427 (NVARCHAR to ASCII conversion) on some rows otherwise.
     *
     * @return array<int, array{sap_code: string, description: ?string, principal_code: string, uom: ?string}>
     */
    public function readProducts(): array
    {
        $rows = $this->fetchAll(
            sprintf(
                'SELECT "ItemCode", BINTOHEX(TO_VARBINARY("ItemName")) AS NAME_HEX,
                        "ItmsGrpCod", TO_VARCHAR("InvntryUom") AS UOM
                 FROM "%s"."OITM"',
                $this->schema
            )
        );

        return array_map(fn (array $row): array => [
            'sap_code' => trim((string) $row['ItemCode']),
            'description' => $this->hexDecode($row['NAME_HEX'] ?? null),
            'principal_code' => trim((string) $row['ItmsGrpCod']),
            'uom' => $row['UOM'] === null ? null : trim((string) $row['UOM']),
        ], $rows);
    }

    /**
     * @return array<string, string> principal_code => principal_name
     */
    public function readPrincipals(): array
    {
        $rows = $this->fetchAll(
            sprintf(
                'SELECT "ItmsGrpCod", BINTOHEX(TO_VARBINARY("ItmsGrpNam")) AS NAME_HEX FROM "%s"."OITB"',
                $this->schema
            )
        );

        $principals = [];
        foreach ($rows as $row) {
            $principals[trim((string) $row['ItmsGrpCod'])] = $this->hexDecode($row['NAME_HEX'] ?? null) ?? trim((string) $row['ItmsGrpCod']);
        }

        return $principals;
    }

    private function hexDecode(?string $hex): ?string
    {
        if ($hex === null || $hex === '') {
            return null;
        }

        $decoded = @hex2bin($hex);

        return $decoded === false ? null : trim($decoded);
    }

    public function __destruct()
    {
        if (is_resource($this->connection)) {
            odbc_close($this->connection);
        }
    }

    private function ensureConnected(): void
    {
        if (is_resource($this->connection)) {
            return;
        }

        $dsn = sprintf(
            'DRIVER={HDBODBC};SERVERNODE=%s:%d;CHARSET=utf8',
            $this->sapConnection->host,
            $this->sapConnection->port ?? 30015,
        );

        if (getenv('ODBCINST') === false && getenv('ODBCINI') === false) {
            putenv('ODBCINST='.(is_file('/etc/odbcinst.ini')
                ? '/etc/odbcinst.ini'
                : ($_SERVER['HOME'] ?? '/root').'/.odbcinst.ini'));
        }

        // HDBODBC needs a UTF-8 locale client-side or NVARCHAR fetches fail
        // with -10427 (NVARCHAR to ASCII conversion failed). setlocale affects
        // the current process immediately; the C.utf8 locale always exists.
        if (setlocale(LC_ALL, ['en_US.UTF-8', 'C.utf8', 'C.UTF-8']) === false) {
            putenv('LC_ALL=C.utf8');
        }

        $conn = odbc_connect($dsn, $this->sapConnection->username, $this->sapConnection->password);

        if ($conn === false) {
            throw new \RuntimeException('SAP HANA ODBC connect failed: '.odbc_errormsg());
        }

        $this->connection = $conn;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(string $sql): array
    {
        $this->ensureConnected();

        $result = odbc_exec($this->connection, $sql);

        if ($result === false) {
            throw new \RuntimeException('SAP HANA query failed: '.odbc_errormsg($this->connection));
        }

        $rows = [];
        while (($row = odbc_fetch_array($result)) !== false) {
            $rows[] = $row;
        }
        odbc_free_result($result);

        return $rows;
    }
}
