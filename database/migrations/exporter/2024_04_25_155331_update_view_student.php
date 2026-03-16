<?php

use App\Support\Database\AsView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use AsView;

    public function up()
    {
        if (! DB::selectOne("SELECT to_regclass('public.exporter_person') as reg")?->reg) {
            $religionsExists = DB::selectOne("SELECT to_regclass('pmieducar.religions') as reg")?->reg;
            if ($religionsExists && ! Schema::hasColumn('pmieducar.religions', 'nm_religiao')) {
                DB::statement('ALTER TABLE pmieducar.religions ADD COLUMN nm_religiao VARCHAR(255)');
            }
            $this->createView('public.exporter_person', '2023-10-05');
        }

        $this->dropView('public.exporter_social_assistance');
        $this->dropView('public.exporter_student');
        $this->createView('public.exporter_student', '2024-04-25');
        $this->createView('public.exporter_social_assistance', '2020-05-07');

        $this->dropView('public.exporter_student_grouped_registration');
        $this->createView('public.exporter_student_grouped_registration', '2024-04-25');
    }

    public function down()
    {
        $this->dropView('public.exporter_student_grouped_registration');
        $this->createView('public.exporter_student_grouped_registration', '2024-03-12');

        $this->dropView('public.exporter_social_assistance');
        $this->dropView('public.exporter_student');
        $this->createView('public.exporter_student', '2024-01-29');
        $this->createView('public.exporter_social_assistance', '2020-05-07');

    }
};
