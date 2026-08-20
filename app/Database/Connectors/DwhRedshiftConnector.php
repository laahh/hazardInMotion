<?php

declare(strict_types=1);

namespace App\Database\Connectors;

/**
 * PDO connector Redshift: GSSAPI dimatikan lewat parent, SET NAMES di-skip.
 */
final class DwhRedshiftConnector extends GssSafePostgresConnector
{
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
