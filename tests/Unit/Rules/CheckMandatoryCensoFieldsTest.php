<?php

namespace Tests\Unit\Rules;

use App\Rules\CheckMandatoryCensoFields;
use App_Model_TipoMediacaoDidaticoPedagogico;
use iEducar\Modules\Educacenso\Model\EtapaAgregada;
use iEducar\Modules\Educacenso\Model\OrganizacaoCurricular;
use iEducar\Modules\Educacenso\Model\TipoAtendimentoTurma;
use Tests\TestCase;

class CheckMandatoryCensoFieldsTest extends TestCase
{
    private CheckMandatoryCensoFields $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new CheckMandatoryCensoFields;
    }

    private function createDefaultParams(): \stdClass
    {
        $params = new \stdClass;
        $params->ref_cod_instituicao = 1;
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO;
        $params->organizacao_curricular = null;
        $params->etapa_educacenso = null;

        return $params;
    }

    public function test_organizacao_curricular_null()
    {
        $params = $this->createDefaultParams();
        $params->organizacao_curricular = null;

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_organizacao_curricular_array_vazio()
    {
        $params = $this->createDefaultParams();
        $params->organizacao_curricular = '{}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_organizacao_curricular_null_entre_chaves()
    {
        $params = $this->createDefaultParams();
        $params->organizacao_curricular = '{null}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_formacao_geral_basica_valida_com_ensino_medio()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::FORMACAO_GERAL_BASICA . '}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_formacao_geral_basica_valida_com_normal_magisterio()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO_NORMAL_MAGISTERIO;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::FORMACAO_GERAL_BASICA . '}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_formacao_geral_basica_invalida_com_etapa_agregada_incorreta()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_FUNDAMENTAL;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::FORMACAO_GERAL_BASICA . '}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertFalse($result);
        $this->assertStringContainsString('Formação geral básica', $this->rule->message());
        $this->assertStringContainsString('304 ou 305', $this->rule->message());
    }

    public function test_itinerario_aprofundamento_valido_com_ensino_medio()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::ITINERARIO_FORMATIVO_APROFUNDAMENTO . '}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_itinerario_aprofundamento_valido_com_normal_magisterio()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO_NORMAL_MAGISTERIO;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::ITINERARIO_FORMATIVO_APROFUNDAMENTO . '}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_itinerario_aprofundamento_invalido_com_etapa_agregada_incorreta()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_FUNDAMENTAL;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::ITINERARIO_FORMATIVO_APROFUNDAMENTO . '}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertFalse($result);
        $this->assertStringContainsString('Itinerário formativo de aprofundamento', $this->rule->message());
        $this->assertStringContainsString('304 ou 305', $this->rule->message());
    }

    public function test_itinerario_tecnico_valido_com_ensino_medio()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::ITINERARIO_FORMACAO_TECNICA_PROFISSIONAL . '}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_itinerario_tecnico_valido_com_normal_magisterio()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO_NORMAL_MAGISTERIO;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::ITINERARIO_FORMACAO_TECNICA_PROFISSIONAL . '}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_itinerario_tecnico_invalido_com_etapa_agregada_incorreta()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_FUNDAMENTAL;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::ITINERARIO_FORMACAO_TECNICA_PROFISSIONAL . '}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertFalse($result);
        $this->assertStringContainsString('Itinerário de formação técnica e profissional', $this->rule->message());
        $this->assertStringContainsString('304 ou 305', $this->rule->message());
    }

    public function test_todas_organizacoes_validas_com_ensino_medio()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO;
        $organizations = OrganizacaoCurricular::FORMACAO_GERAL_BASICA . ',' . OrganizacaoCurricular::ITINERARIO_FORMATIVO_APROFUNDAMENTO . ',' . OrganizacaoCurricular::ITINERARIO_FORMACAO_TECNICA_PROFISSIONAL;
        $params->organizacao_curricular = '{' . $organizations . '}';

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_etapa_ensino_valida_com_formacao_geral_basica_ensino_medio()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::FORMACAO_GERAL_BASICA . '}';
        $params->etapa_educacenso = 25;

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_etapa_ensino_invalida_com_formacao_geral_basica_ensino_medio()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::FORMACAO_GERAL_BASICA . '}';
        $params->etapa_educacenso = 30;

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertFalse($result);
        $this->assertStringContainsString('25, 26, 27, 28 ou 29', $this->rule->message());
    }

    public function test_etapa_ensino_valida_com_formacao_geral_basica_normal_magisterio()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO_NORMAL_MAGISTERIO;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::FORMACAO_GERAL_BASICA . '}';
        $params->etapa_educacenso = 35;

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertTrue($result);
    }

    public function test_etapa_ensino_invalida_com_formacao_geral_basica_normal_magisterio()
    {
        $params = $this->createDefaultParams();
        $params->etapa_agregada = EtapaAgregada::ENSINO_MEDIO_NORMAL_MAGISTERIO;
        $params->organizacao_curricular = '{' . OrganizacaoCurricular::FORMACAO_GERAL_BASICA . '}';
        $params->etapa_educacenso = 30;

        $result = $this->rule->validaCampoOrganizacaoCurricularDaTurma($params);

        $this->assertFalse($result);
        $this->assertStringContainsString('35, 36, 37 ou 38', $this->rule->message());
    }

    private function createParams2026(): \stdClass
    {
        $params = new \stdClass;
        $params->ano = 2026;
        $params->etapa_educacenso = null;
        $params->etapa_agregada = null;
        $params->codigo_eixo_curso_profissional = null;
        $params->carga_horaria_curso = null;
        $params->tipo_atendimento = null;
        $params->tipo_mediacao_didatico_pedagogico = App_Model_TipoMediacaoDidaticoPedagogico::PRESENCIAL;

        return $params;
    }

    public function test_eixo_obrigatorio_para_etapa_68_sem_eixo()
    {
        $params = $this->createParams2026();
        $params->etapa_educacenso = 68;
        $params->codigo_eixo_curso_profissional = null;

        $this->assertFalse($this->rule->validaCampoEixoCursoProfissional($params));
        $this->assertStringContainsString('67, 68, 73 ou 75', $this->rule->message());
    }

    public function test_eixo_valido_para_etapa_68_com_eixo()
    {
        $params = $this->createParams2026();
        $params->etapa_educacenso = 68;
        $params->codigo_eixo_curso_profissional = 4;

        $this->assertTrue($this->rule->validaCampoEixoCursoProfissional($params));
    }

    public function test_eixo_nao_exigido_para_etapa_sem_qualificacao()
    {
        $params = $this->createParams2026();
        $params->etapa_educacenso = 25;
        $params->codigo_eixo_curso_profissional = null;

        $this->assertTrue($this->rule->validaCampoEixoCursoProfissional($params));
    }

    public function test_eixo_nao_validado_fora_do_layout_2026()
    {
        $params = $this->createParams2026();
        $params->ano = 2025;
        $params->etapa_educacenso = 68;
        $params->codigo_eixo_curso_profissional = null;

        $this->assertTrue($this->rule->validaCampoEixoCursoProfissional($params));
    }

    public function test_carga_horaria_nula_e_valida()
    {
        $params = $this->createParams2026();
        $params->etapa_educacenso = 68;
        $params->carga_horaria_curso = null;

        $this->assertTrue($this->rule->validaCampoCargaHorariaCurso($params));
    }

    public function test_carga_horaria_abaixo_do_minimo_da_etapa_67()
    {
        $params = $this->createParams2026();
        $params->etapa_educacenso = 67;
        $params->carga_horaria_curso = 800;

        $this->assertFalse($this->rule->validaCampoCargaHorariaCurso($params));
        $this->assertStringContainsString('1200', $this->rule->message());
    }

    public function test_carga_horaria_no_minimo_da_etapa_68()
    {
        $params = $this->createParams2026();
        $params->etapa_educacenso = 68;
        $params->carga_horaria_curso = 160;

        $this->assertTrue($this->rule->validaCampoCargaHorariaCurso($params));
    }

    public function test_carga_horaria_invalida_quando_zero()
    {
        $params = $this->createParams2026();
        $params->etapa_educacenso = 40;
        $params->carga_horaria_curso = 0;

        $this->assertFalse($this->rule->validaCampoCargaHorariaCurso($params));
        $this->assertStringContainsString('maior que 0', $this->rule->message());
    }

    public function test_mediacao_presencial_curricular_com_complementar_etapa_invalida()
    {
        $params = $this->createParams2026();
        $params->tipo_mediacao_didatico_pedagogico = App_Model_TipoMediacaoDidaticoPedagogico::PRESENCIAL;
        $params->tipo_atendimento = '{' . TipoAtendimentoTurma::CURRICULAR_ETAPA_ENSINO . ',' . TipoAtendimentoTurma::ATIVIDADE_COMPLEMENTAR . '}';
        $params->etapa_educacenso = 1;

        $this->assertFalse($this->rule->validaCorrespondenciaMediacaoTipoTurmaEtapa($params));
        $this->assertStringContainsString('Anexo 7', $this->rule->message());
    }

    public function test_mediacao_presencial_curricular_com_complementar_etapa_valida()
    {
        $params = $this->createParams2026();
        $params->tipo_mediacao_didatico_pedagogico = App_Model_TipoMediacaoDidaticoPedagogico::PRESENCIAL;
        $params->tipo_atendimento = '{' . TipoAtendimentoTurma::CURRICULAR_ETAPA_ENSINO . ',' . TipoAtendimentoTurma::ATIVIDADE_COMPLEMENTAR . '}';
        $params->etapa_educacenso = 14;

        $this->assertTrue($this->rule->validaCorrespondenciaMediacaoTipoTurmaEtapa($params));
    }
}
