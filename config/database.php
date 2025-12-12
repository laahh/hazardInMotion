<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public, datamart',
            'sslmode' => 'prefer',
        ],

        'pgsql_ssh' => [
            'driver' => 'pgsql',
            'host' => env('PG_SSH_HOST', '127.0.0.1'),
            'port' => env('PG_SSH_LOCAL_PORT', '5433'),
            'database' => env('PG_SSH_DATABASE', 'hse_automation'),
            'username' => env('PG_SSH_USER', 'safety_evaluator_2'),
            'password' => env('PG_SSH_PASSWORD', 'safety123'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            // SSH Configuration
            'ssh_host' => env('SSH_HOST', '13.212.87.127'),
            'ssh_port' => env('SSH_PORT', 22),
            'ssh_user' => env('SSH_USER', 'ubuntu'),
            'ssh_password' => env('SSH_PASSWORD'),
            'ssh_pkey' => env('SSH_PKEY'),
            'pg_host' => env('PG_HOST', 'postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com'),
            'pg_port' => env('PG_PORT', 5432),
            'local_port' => env('PG_SSH_LOCAL_PORT', 5433),
        ],

        'pgsql_direct' => [
            'driver' => 'pgsql',
            'host' => env('PG_HOST', 'postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com'),
            'port' => env('PG_PORT', '5432'),
            'database' => env('PG_DATABASE', 'hse_automation'),
            'username' => env('PG_USER', 'safety_evaluator_2'),
            'password' => env('PG_PASSWORD', 'safety123'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public, datamart',
            'sslmode' => 'prefer',
        ],

        'clickhouse' => [
            'driver' => 'clickhouse',
            'host' => env('CLICKHOUSE_HOST'),
            'port' => env('CLICKHOUSE_PORT'),
            'database' => env('CLICKHOUSE_DATABASE'),
            'username' => env('CLICKHOUSE_USERNAME'),
            'password' => env('CLICKHOUSE_PASSWORD'),
            'options' => [
                'timeout' => 30,
                'protocol' => env('CLICKHOUSE_PROTOCOL', 'http'),
            ],
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

        'besigma_db' => [
            'driver' => 'mysql',
            'host' => env('BESIGMA_DB_HOST', '127.0.0.1'),
            'port' => env('BESIGMA_DB_PORT', '3307'),
            'database' => env('BESIGMA_DB_DATABASE', 'besigma_db'),
            'username' => env('BESIGMA_DB_USERNAME', 'safety_evaluator'),
            'password' => env('BESIGMA_DB_PASSWORD', 'Safety_EV2025'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
            // SSH Tunnel Configuration
            'ssh_host' => env('BESIGMA_SSH_HOST', '13.250.29.29'),
            'ssh_port' => env('BESIGMA_SSH_PORT', 22),
            'ssh_user' => env('BESIGMA_SSH_USER', 'ubuntu'),
            'ssh_pkey' => env('BESIGMA_SSH_PKEY', public_path('bsigma-jumpserver.pem')),
            'remote_host' => env('BESIGMA_REMOTE_HOST', '10.11.58.139'),
            'remote_port' => env('BESIGMA_REMOTE_PORT', 3306),
            'local_port' => env('BESIGMA_LOCAL_PORT', 3307),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
