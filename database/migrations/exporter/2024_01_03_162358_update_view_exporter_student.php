<?php

use App\Support\Database\AsView;
use App\Support\Database\PmieducarReligionsSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    use AsView;

    public function up(): void
    {
        $this->ensureExporterPersonExists();

        $this->dropView('public.exporter_social_assistance');
        $this->dropView('public.exporter_student');
        $this->createView('public.exporter_student', '2024-01-03');
        $this->createView('public.exporter_social_assistance', '2020-05-07');
    }

    public function down(): void
    {
        $this->ensureExporterPersonExists();

        $this->dropView('public.exporter_social_assistance');
        $this->dropView('public.exporter_student');
        $this->createView('public.exporter_student', '2022-06-15');
        $this->createView('public.exporter_social_assistance', '2020-05-07');
    }

    private function ensureExporterPersonExists(): void
    {
        if (DB::selectOne("SELECT to_regclass('public.exporter_person') as reg")?->reg) {
            return;
        }

        PmieducarReligionsSchema::ensureForExporterPersonView();

        $this->createView('public.exporter_person', '2023-10-05');
    }
};
