<?php

namespace App\Console\Commands;

use App\Exceptions\Console\MissingSchoolCourseException;
use App\Exceptions\Console\MissingSchoolGradeException;
use App\Models\LegacyEnrollment;
use App\Models\LegacyGrade;
use App\Models\LegacySchoolClass;
use App\Models\LegacySchoolCourse;
use App\Models\LegacySchoolGrade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateSchoolClassGrade extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:school-class-grade
                            {schoolclass : Código da turma}
                            {grade : Código da série destino}
                            {--desfazer-multisseriada : Converte turma multisseriada em série única}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atualiza a série da turma e, opcionalmente, desfaz a multisseriada';

    /**
     * @var LegacyGrade
     */
    private $grade;

    /**
     * @var LegacySchoolClass
     */
    private $schoolClass;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->grade = LegacyGrade::findOrFail($this->argument('grade'));
        $this->schoolClass = LegacySchoolClass::findOrFail($this->argument('schoolclass'));

        $this->validateSchoolGrade();
        $this->validateSchoolCourse();

        DB::beginTransaction();

        $this->schoolClass->update([
            'ref_ref_cod_serie' => $this->grade->getKey(),
            'ref_cod_curso' => $this->grade->course->id,
        ]);

        $this->schoolClass->enrollments->map(function ($enrollment) {
            /** @var LegacyEnrollment $enrollment */
            $enrollment->registration->update([
                'ref_ref_cod_serie' => $this->grade->getKey(),
                'ref_cod_curso' => $this->grade->course->id,
            ]);
        });

        $this->updateScheduleGrade();

        if ($this->option('desfazer-multisseriada')) {
            $this->disableMultigrade();
        }

        DB::commit();

        $this->info("Turma {$this->schoolClass->getKey()} atualizada para a série {$this->grade->getKey()}.");
    }

    /**
     * Converte a turma multisseriada em série única.
     */
    private function disableMultigrade(): void
    {
        $this->schoolClass->load('multigrades');

        if (!(bool) $this->schoolClass->multiseriada && $this->schoolClass->multigrades->isEmpty()) {
            $this->info('A turma já não é multisseriada.');

            return;
        }

        $reportCard = $this->schoolClass->multigrades
            ->firstWhere('serie_id', $this->grade->getKey())
            ?? $this->schoolClass->multigrades->first();

        $this->schoolClass->update([
            'multiseriada' => 0,
            'ref_ref_cod_serie_mult' => null,
            'ref_ref_cod_escola_mult' => null,
            'tipo_boletim' => $this->schoolClass->tipo_boletim ?: $reportCard?->boletim_id,
            'tipo_boletim_diferenciado' => $this->schoolClass->tipo_boletim_diferenciado ?: $reportCard?->boletim_diferenciado_id,
        ]);

        $this->schoolClass->multigrades()->delete();

        $this->info('Multisseriada desfeita: série única e registros extras em turma_serie removidos.');
    }

    /**
     * Atualiza a série dos horários do quadro da turma.
     */
    private function updateScheduleGrade(): void
    {
        $gradeId = $this->grade->getKey();
        $schoolClassId = $this->schoolClass->getKey();

        $scheduleIds = DB::table('pmieducar.quadro_horario')
            ->where('ref_cod_turma', $schoolClassId)
            ->pluck('cod_quadro_horario');

        if ($scheduleIds->isEmpty()) {
            return;
        }

        DB::table('pmieducar.quadro_horario_horarios')
            ->whereIn('ref_cod_quadro_horario', $scheduleIds)
            ->update(['ref_cod_serie' => $gradeId]);

        DB::table('pmieducar.quadro_horario_horarios_aux')
            ->whereIn('ref_cod_quadro_horario', $scheduleIds)
            ->update(['ref_cod_serie' => $gradeId]);
    }

    /**
     * Valida se existe registro em escola_serie
     *
     * @throws MissingSchoolGradeException
     */
    private function validateSchoolGrade()
    {
        $existsSchoolGrade = LegacySchoolGrade::where('ref_cod_escola', $this->schoolClass->school_id)
            ->where('ref_cod_serie', $this->grade->getKey())
            ->exists();

        if ($existsSchoolGrade) {
            return;
        }

        throw new MissingSchoolGradeException($this->schoolClass->school, $this->grade);
    }

    /**
     * Valida se existe registro em escola_curso
     *
     * @throws MissingSchoolCourseException
     */
    private function validateSchoolCourse()
    {
        $existsSchoolCourse = LegacySchoolCourse::where('ref_cod_escola', $this->schoolClass->school_id)
            ->where('ref_cod_curso', $this->grade->course->id)
            ->exists();

        if ($existsSchoolCourse) {
            return;
        }

        throw new MissingSchoolCourseException($this->schoolClass->school, $this->grade->course);
    }
}
