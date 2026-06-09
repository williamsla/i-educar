<?php

use App\Support\Database\AsView;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    use AsView;

    public function up()
    {
        $this->createView('relatorio.view_dados_escola', '2025-11-12');

    }

    public function down()
    {
        $this->createView('relatorio.view_dados_escola', '2020-08-27');
    }
};
