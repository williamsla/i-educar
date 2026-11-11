<?php

use App\Support\Database\AuditTrigger;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    use AuditTrigger;

    public function up()
    {
        $this->createAuditTriggers();
    }

    public function down()
    {
        $this->dropAuditTriggers();
    }
};
