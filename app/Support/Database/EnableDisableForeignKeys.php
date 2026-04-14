<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

trait EnableDisableForeignKeys
{
    /**
     * Disable foreign keys check.
     *
     * @param string $table
     * @return void
     */
    protected function disableForeignKeys($table)
    {
        // "ALL" requires superuser in PostgreSQL because it includes system triggers.
        DB::statement("ALTER TABLE {$table} DISABLE TRIGGER USER;");
    }

    /**
     * Enable foreign keys check.
     *
     * @param string $table
     * @return void
     */
    protected function enableForeignKeys($table)
    {
        DB::statement("ALTER TABLE {$table} ENABLE TRIGGER USER;");
    }
}
