<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;

class NeonPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config)
    {
        $dsn = parent::getDsn($config);

        if (isset($config['endpoint_id']) && $config['endpoint_id'] !== '') {
            $dsn .= ";options='endpoint={$config['endpoint_id']}'";
        }

        return $dsn;
    }
}
