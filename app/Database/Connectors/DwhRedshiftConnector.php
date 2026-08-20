<?php

declare(strict_types=1);

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * PDO connector Redshift: matikan GSSAPI (penyebab timeout libpq)
 * dan jangan kirim SET NAMES (tidak didukung Redshift).
 */
final class DwhRedshiftConnector extends PostgresConnector
{
    /**
     * @param  array<string, mixed>  $config
     */
    protected function getDsn(array $config): string
    {
        $dsn = parent::getDsn($config);
        $gssencmode = $config['gssencmode'] ?? 'disable';
        $dsn .= ';gssencmode='.$gssencmode;

        $connectTimeout = (int) ($config['connect_timeout'] ?? 0);
        if ($connectTimeout > 0) {
            $dsn .= ';connect_timeout='.$connectTimeout;
        }

        return $dsn;
    }

    /**
     * Redshift tidak mendukung `SET NAMES`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function configureEncoding($connection, $config): void
    {
        // no-op
    }
}
