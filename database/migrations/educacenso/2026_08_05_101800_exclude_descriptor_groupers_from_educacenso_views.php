<?php

use App\Support\Database\AsView;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    use AsView;

    public function up(): void
    {
        $this->dropView('public.educacenso_record20');
        $this->dropView('public.educacenso_record50');

        $this->createView('public.educacenso_record20', '2026-08-05');
        $this->createView('public.educacenso_record50', '2026-08-05');
    }

    public function down(): void
    {
        $this->dropView('public.educacenso_record20');
        $this->dropView('public.educacenso_record50');

        $this->createView('public.educacenso_record20', '2026-06-15');
        $this->createView('public.educacenso_record50', '2025-06-17');
    }
};
