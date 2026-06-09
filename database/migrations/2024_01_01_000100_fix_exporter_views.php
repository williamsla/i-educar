<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {

        /*
        |--------------------------------------------------------------------------
        | Remove views antigas que podem quebrar migrations
        |--------------------------------------------------------------------------
        */

        $views = [
            'exporter_person',
            'exporter_student',
            'view_student_grouped_registration'
        ];

        foreach ($views as $view) {

            DB::statement("
                DO $$
                BEGIN
                    IF EXISTS (
                        SELECT 1
                        FROM pg_views
                        WHERE schemaname = 'public'
                        AND viewname = '{$view}'
                    ) THEN
                        EXECUTE 'DROP VIEW public.{$view} CASCADE';
                    END IF;
                END
                $$;
            ");
        }
    }

    public function down(): void
    {
        // não recriar automaticamente
    }
};