<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupplierTable extends Migration
{

    public function up(): void
    {
        Schema::create('cadastro.fornecedor', function (Blueprint $table) {
            $table->bigIncrements('cod_fornecedor');
            $table->bigInteger('ref_idpes');
            $table->foreign('ref_idpes')->references('idpes')->on('cadastro.juridica')->onDelete('cascade')->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedor');
    }
}