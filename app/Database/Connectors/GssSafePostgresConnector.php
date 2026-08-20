<?php

declare(strict_types=1);

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\PostgresConnector;
use PDO;

/**
 * Connector Postgres yang menonaktifkan GSSAPI di DSN.
 * libpq PHP 8+ mencoba GSS dulu; RDS/Redshift tidak support → SQLSTATE timeout expired.
 */
class GssSafePostgresConnector extends PostgresConnector
{
    /**
     * @param  array<string, mixed>  $config
     */
    protected function getDsn(array $config): string
    {
        $dsn = parent::getDsn($config);
        $gssencmode = (string) ($config['gssencmode'] ?? 'disable');
        $dsn .= ';gssencmode='.$gssencmode;

        $connectTimeout = (int) ($config['connect_timeout'] ?? 0);
        if ($connectTimeout < 1) {
            $options = $config['options'] ?? [];
            if (is_array($options) && isset($options[PDO::ATTR_TIMEOUT])) {
                $connectTimeout = (int) $options[PDO::ATTR_TIMEOUT];
            }
        }
        if ($connectTimeout > 0) {
            $dsn .= ';connect_timeout='.$connectTimeout;
        }

        return $dsn;
    }
}
