<?php

use App\Support\Database\AuditTrigger;
use Illuminate\Database\Migrations\Migration;

class AuditTriggers extends Migration
{
    use AuditTrigger;

    /**
     * @var bool
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->createAuditTriggers();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->dropAuditTriggers();
    }
}
