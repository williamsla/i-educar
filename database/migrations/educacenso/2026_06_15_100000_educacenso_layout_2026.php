<?php

use App\Models\LegacyInstitution;
use App\Support\Database\AsView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use AsView;

    public function up(): void
    {
        Schema::table('pmieducar.escola', function (Blueprint $table) {
            if (!Schema::hasColumn('pmieducar.escola', 'qtd_assistente_social')) {
                $table->smallInteger('qtd_assistente_social')->nullable();
            }
        });

        Schema::table('pmieducar.turma', function (Blueprint $table) {
            if (!Schema::hasColumn('pmieducar.turma', 'codigo_eixo_curso_profissional')) {
                $table->smallInteger('codigo_eixo_curso_profissional')->nullable();
            }

            if (!Schema::hasColumn('pmieducar.turma', 'carga_horaria_curso')) {
                $table->smallInteger('carga_horaria_curso')->nullable();
            }
        });

        Schema::table('pmieducar.matricula_turma', function (Blueprint $table) {
            if (!Schema::hasColumn('pmieducar.matricula_turma', 'carga_horaria_integralizada')) {
                $table->smallInteger('carga_horaria_integralizada')->nullable();
            }
        });

        $this->dropView('public.educacenso_record20');
        $this->dropView('public.educacenso_record60');

        $this->createView('public.educacenso_record20', '2026-06-15');
        $this->createView('public.educacenso_record60', '2026-06-15');

        LegacyInstitution::query()->update([
            'data_educacenso' => '2026-05-27',
        ]);

        DB::table('settings')->updateOrInsert(
            ['key' => 'legacy.educacenso.enable_export'],
            ['value' => '1', 'type' => 'boolean']
        );
    }

    public function down(): void
    {
        Schema::table('pmieducar.escola', function (Blueprint $table) {
            if (Schema::hasColumn('pmieducar.escola', 'qtd_assistente_social')) {
                $table->dropColumn('qtd_assistente_social');
            }
        });

        Schema::table('pmieducar.turma', function (Blueprint $table) {
            if (Schema::hasColumn('pmieducar.turma', 'codigo_eixo_curso_profissional')) {
                $table->dropColumn('codigo_eixo_curso_profissional');
            }

            if (Schema::hasColumn('pmieducar.turma', 'carga_horaria_curso')) {
                $table->dropColumn('carga_horaria_curso');
            }
        });

        Schema::table('pmieducar.matricula_turma', function (Blueprint $table) {
            if (Schema::hasColumn('pmieducar.matricula_turma', 'carga_horaria_integralizada')) {
                $table->dropColumn('carga_horaria_integralizada');
            }
        });

        $this->dropView('public.educacenso_record20');
        $this->dropView('public.educacenso_record60');

        $this->createView('public.educacenso_record20', '2025-06-13');
        $this->createView('public.educacenso_record60', '2025-06-13');

        LegacyInstitution::query()->update([
            'data_educacenso' => '2025-05-28',
        ]);
    }
};
