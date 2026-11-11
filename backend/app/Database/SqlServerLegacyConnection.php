<?php

declare(strict_types=1);

namespace App\Database;

use App\Database\Query\Grammars\SqlServerLegacyGrammar;
use Illuminate\Database\SqlServerConnection;

/**
 * SQL Server connection that swaps in the pre-2012 query grammar when the
 * connection is configured with `legacy_pagination`.
 */
class SqlServerLegacyConnection extends SqlServerConnection
{
    /**
     * {@inheritdoc}
     */
    protected function getDefaultQueryGrammar()
    {
        if ($this->getConfig('legacy_pagination')) {
            return new SqlServerLegacyGrammar($this);
        }

        return parent::getDefaultQueryGrammar();
    }
}
