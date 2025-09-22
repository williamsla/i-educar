<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('public.uniform_distributions', 'cod_distribuicao_uniforme')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME cod_distribuicao_uniforme TO id;');
        }
        
        if (Schema::hasColumn('public.uniform_distributions', 'ref_cod_aluno')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME ref_cod_aluno TO student_id;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'ano')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME ano TO year;');
        }
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions ALTER COLUMN year TYPE smallint;');

        if (Schema::hasColumn('public.uniform_distributions', 'kit_completo')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME kit_completo TO complete_kit;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'agasalho_qtd')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME agasalho_qtd TO coat_pants_qty;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'camiseta_curta_qtd')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME camiseta_curta_qtd TO shirt_short_qty;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'camiseta_longa_qtd')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME camiseta_longa_qtd TO shirt_long_qty;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'meias_qtd')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME meias_qtd TO socks_qty;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'bermudas_tectels_qtd')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME bermudas_tectels_qtd TO shorts_tactel_qty;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'bermudas_coton_qtd')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME bermudas_coton_qtd TO shorts_coton_qty;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'tenis_qtd')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME tenis_qtd TO sneakers_qty;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'data')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME data TO distribution_date;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'agasalho_tm')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME agasalho_tm TO coat_pants_tm;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'camiseta_curta_tm')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME camiseta_curta_tm TO shirt_short_tm;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'camiseta_longa_tm')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME camiseta_longa_tm TO shirt_long_tm;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'meias_tm')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME meias_tm TO socks_tm;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'bermudas_tectels_tm')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME bermudas_tectels_tm TO shorts_tactel_tm;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'bermudas_coton_tm')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME bermudas_coton_tm TO shorts_coton_tm;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'tenis_tm')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME tenis_tm TO sneakers_tm;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'ref_cod_escola')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME ref_cod_escola TO school_id;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'camiseta_infantil_qtd')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME camiseta_infantil_qtd TO kids_shirt_qty;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'camiseta_infantil_tm')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME camiseta_infantil_tm TO kids_shirt_tm;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'calca_jeans_qtd')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME calca_jeans_qtd TO pants_jeans_qty;');
        }

        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions ALTER COLUMN pants_jeans_qty TYPE smallint;');

        if (Schema::hasColumn('public.uniform_distributions', 'calca_jeans_tm')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME calca_jeans_tm TO pants_jeans_tm;');
        }

        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions ALTER COLUMN pants_jeans_tm TYPE character varying(20) COLLATE pg_catalog."default";');

        if (Schema::hasColumn('public.uniform_distributions', 'saia_qtd')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME saia_qtd TO skirt_qty;');
        }

        if (Schema::hasColumn('public.uniform_distributions', 'saia_tm')) {
            DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME saia_tm TO skirt_tm;');
        }
    }

    public function down()
    {
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME id TO cod_distribuicao_uniforme;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME student_id TO ref_cod_aluno;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME year TO ano;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions ALTER COLUMN ano TYPE integer;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME complete_kit TO kit_completo;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME coat_pants_qty TO agasalho_qtd;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME shirt_short_qty TO camiseta_curta_qtd;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME shirt_long_qty TO camiseta_longa_qtd;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME socks_qty TO meias_qtd;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME shorts_tactel_qty TO bermudas_tectels_qtd;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME shorts_coton_qty TO bermudas_coton_qtd;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME sneakers_qty TO tenis_qtd;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME distribution_date TO data;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME coat_pants_tm TO agasalho_tm;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME shirt_short_tm TO camiseta_curta_tm;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME shirt_long_tm TO camiseta_longa_tm;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME socks_tm TO meias_tm;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME shorts_tactel_tm TO bermudas_tectels_tm;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME shorts_coton_tm TO bermudas_coton_tm;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME sneakers_tm TO tenis_tm;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME school_id TO ref_cod_escola;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME kids_shirt_qty TO camiseta_infantil_qtd;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME kids_shirt_tm TO camiseta_infantil_tm;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME pants_jeans_qty TO calca_jeans_qtd;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions ALTER COLUMN calca_jeans_qtd TYPE integer;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME pants_jeans_tm TO calca_jeans_tm;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions ALTER COLUMN calca_jeans_tm TYPE character varying(191) COLLATE pg_catalog."default";');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME skirt_qty TO saia_qtd;');
        DB::unprepared('ALTER TABLE IF EXISTS public.uniform_distributions RENAME skirt_tm TO saia_tm;');
    }
};
