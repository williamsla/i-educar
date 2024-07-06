<?php

namespace Tests\Unit\View\Exporter;

use App\Models\Exporter\Student;
use Illuminate\Support\Facades\DB;
use Tests\ViewTestCase;

class StudentTest extends ViewTestCase
{
    public function testStudent(): void
    {
        $found = Student::query()->get();
        $this->assertEquals(9, $found[1]->status);
        $this->assertEquals(10, $found[2]->status);
        $this->assertJsonStringEqualsJsonString($this->model, $found->first());
    }

    public function testRelationshipMother(): void
    {
        $found = Student::query()->mother([
            'id',
            'name',
            'email',
            'social_name',
            'cpf',
            'date_of_birth',
            'sus',
            'nis',
            'occupation',
            'organization',
            'monthly_income',
            'gender',
            'rg',
            'rg_issue_date',
            'rg_state_abbreviation',
        ])->first();
        $expected = [
            'ID da mãe',
            'Nome da mãe',
            'E-mail da mãe',
            'Nome social e/ou afetivo da mãe',
            'CPF da mãe',
            'Data de nascimento da mãe',
            'Número SUS da mãe',
            'NIS (PIS/PASEP) da mãe',
            'Ocupação da mãe',
            'Empresa da mãe',
            'Renda Mensal da mãe',
            'Gênero da mãe',
            'RG da mãe',
            'RG (Data Emissão) da mãe',
            'RG (Estado) da mãe',
        ];
        $this->assertInstanceOf(Student::class, $found);
        $this->assertEquals($expected, array_keys($found->getAttributes()));
    }

    public function testRelationshipFather(): void
    {
        $found = Student::query()->father([
            'id',
            'name',
            'email',
            'social_name',
            'cpf',
            'date_of_birth',
            'sus',
            'nis',
            'occupation',
            'organization',
            'monthly_income',
            'gender',
            'rg',
            'rg_issue_date',
            'rg_state_abbreviation',
        ])->first();
        $expected = [
            'ID do pai',
            'Nome do pai',
            'E-mail do pai',
            'Nome social e/ou afetivo do pai',
            'CPF do pai',
            'Data de nascimento do pai',
            'Número SUS do pai',
            'NIS (PIS/PASEP) do pai',
            'Ocupação do pai',
            'Empresa do pai',
            'Renda Mensal do pai',
            'Gênero do pai',
            'RG do pai',
            'RG (Data Emissão) do pai',
            'RG (Estado) do pai',
        ];
        $this->assertInstanceOf(Student::class, $found);
        $this->assertEquals($expected, array_keys($found->getAttributes()));
    }

    public function testRelationshipGuardian(): void
    {
        $found = Student::query()->guardian([
            'id',
            'name',
            'email',
            'social_name',
            'cpf',
            'date_of_birth',
            'sus',
            'nis',
            'occupation',
            'organization',
            'monthly_income',
            'gender',
            'rg',
            'rg_issue_date',
            'rg_state_abbreviation',
        ])->first();
        $expected = [
            'ID do responsável',
            'Nome do responsável',
            'E-mail do responsável',
            'Nome social e/ou afetivo do responsável',
            'CPF do responsável',
            'Data de nascimento do responsável',
            'Número SUS do responsável',
            'NIS (PIS/PASEP) do responsável',
            'Ocupação do responsável',
            'Empresa do responsável',
            'Renda Mensal do responsável',
            'Gênero do responsável',
            'RG do responsável',
            'RG (Data Emissão) do responsável',
            'RG (Estado) do responsável',
        ];
        $this->assertInstanceOf(Student::class, $found);
        $this->assertEquals($expected, array_keys($found->getAttributes()));
    }

    public function testRelationshipBenefits(): void
    {
        $found = Student::query()->benefits()->first();
        $this->assertInstanceOf(Student::class, $found);
        $expected = [
            'Benefícios',
        ];
        $this->assertEquals($expected, array_keys($found->getAttributes()));
    }

    public function testRelationshipDisabilities(): void
    {
        $found = Student::query()->disabilities()->first();
        $this->assertInstanceOf(Student::class, $found);
        $expected = [
            'Deficiências',
        ];
        $this->assertEquals($expected, array_keys($found->getAttributes()));
    }

    public function testRelationshipPhones(): void
    {
        $found = Student::query()->phones()->first();
        $this->assertInstanceOf(Student::class, $found);
        $expected = [
            'Telefones',
        ];
        $this->assertEquals($expected, array_keys($found->getAttributes()));
    }

    public function testRelationshipPlace(): void
    {
        $found = Student::query()->place([
            'address',
            'number',
            'complement',
            'neighborhood',
            'postal_code',
            'latitude',
            'longitude',
            'city',
            'state_abbreviation',
            'state',
            'country',
        ])->first();
        $this->assertInstanceOf(Student::class, $found);
        $expected = [
            'Logradouro',
            'Número',
            'Complemento',
            'Bairro',
            'CEP',
            'Latitude',
            'Longitude',
            'Cidade',
            'Sigla do Estado',
            'Estado',
            'País',
        ];
        $this->assertEquals($expected, array_keys($found->getAttributes()));
    }

    public function testGetAlias(): void
    {
        $aliases = collect($this->model->getExportedColumnsByGroup())->flatMap(fn ($item) => $item);
        $this->assertEquals('ID Aluno', $aliases->get('student_id'));
    }

    public function testGetLabel(): void
    {
        $this->assertEquals('Alunos', $this->model->getLabel());
    }

    public function testGetDescription(): void
    {
        $this->assertEquals('Os dados exportados serão contabilizados por quantidade de alunos(as), agrupando as informações de séries, cursos, turmas quando o(a) aluno(a) possuir mais de uma matrícula para a situação e ano filtrados.', $this->model->getDescription());
    }

    public function testGetExportedColumnsByGroup(): void
    {
        $expected = [
            'Códigos' => [
                'id' => 'ID Pessoa',
                'student_id' => 'ID Aluno',
                'school_id' => 'ID Escola',
                'school_class_id' => 'ID Turma',
                'grade_id' => 'ID Série',
                'course_id' => 'ID Curso',
                'inep_id' => 'Código INEP (Aluno)',
            ],
            'Aluno' => [
                'name' => 'Nome',
                'social_name' => 'Nome social e/ou afetivo',
                'cpf' => 'CPF',
                'rg' => 'RG',
                'rg_issue_date' => 'RG (Data Emissão)',
                'rg_state_abbreviation' => 'RG (Estado)',
                'date_of_birth' => 'Data de nascimento',
                'email' => 'E-mail',
                'sus' => 'Número SUS',
                'nis' => 'NIS (PIS/PASEP)',
                'registration_code_id' => 'Código da Rede Estadual (RA)',
                'occupation' => 'Ocupação',
                'organization' => 'Empresa',
                'monthly_income' => 'Renda Mensal',
                'gender' => 'Gênero',
                'race' => 'Raça',
                'religion' => 'Religião',
                'height' => 'Altura',
                'weight' => 'Peso',
            ],
            'Escola' => [
                'school' => 'Escola',
                'school_inep' => 'Código INEP',
                'school_class' => 'Turma',
                'grade' => 'Série',
                'course' => 'Curso',
                'year' => 'Ano',
                'period' => 'Turno',
            ],
            'Informações' => [
                'nationality' => 'Nacionalidade',
                'birthplace' => 'Naturalidade',
                'phones.phones' => 'Telefones',
                'benefits.benefits' => 'Benefícios',
                'projects.projects' => 'Projetos',
                'disabilities.disabilities' => 'Deficiências',
                'modalidade_ensino' => 'Modalidade de ensino cursada',
                'technological_resources' => 'Recursos tecnológicos',
                'transport.tipo_transporte' => 'Transporte escolar público',
                'transport_route' => 'Rota',
                'transport.veiculo_transporte_escolar' => 'Veículo utilizado',
            ],
            'Endereço' => [
                'place.address' => 'Logradouro',
                'place.number' => 'Número',
                'place.complement' => 'Complemento',
                'place.neighborhood' => 'Bairro',
                'place.postal_code' => 'CEP',
                'place.latitude' => 'Latitude',
                'place.longitude' => 'Longitude',
                'place.city' => 'Cidade',
                'place.state_abbreviation' => 'Sigla do Estado',
                'place.state' => 'Estado',
                'place.country' => 'País',
            ],
            'Mãe' => [
                'mother.id' => 'ID da mãe',
                'mother.name' => 'Nome da mãe',
                'mother.social_name' => 'Nome social e/ou afetivo da mãe',
                'mother.cpf' => 'CPF da mãe',
                'mother.rg' => 'RG da mãe',
                'mother.rg_issue_date' => 'RG (Data Emissão) da mãe',
                'mother.rg_state_abbreviation' => 'RG (Estado) da mãe',
                'mother.date_of_birth' => 'Data de nascimento da mãe',
                'mother.email' => 'E-mail da mãe',
                'mother.sus' => 'Número SUS da mãe',
                'mother.nis' => 'NIS (PIS/PASEP) da mãe',
                'mother.occupation' => 'Ocupação da mãe',
                'mother.organization' => 'Empresa da mãe',
                'mother.monthly_income' => 'Renda Mensal da mãe',
                'mother.gender' => 'Gênero da mãe',
                'mother.phone' => 'Telefones da mãe',
            ],
            'Pai' => [
                'father.id' => 'ID do pai',
                'father.name' => 'Nome do pai',
                'father.social_name' => 'Nome social e/ou afetivo do pai',
                'father.cpf' => 'CPF do pai',
                'father.rg' => 'RG do pai',
                'father.rg_issue_date' => 'RG (Data Emissão) do pai',
                'father.rg_state_abbreviation' => 'RG (Estado) do pai',
                'father.date_of_birth' => 'Data de nascimento do pai',
                'father.email' => 'E-mail do pai',
                'father.sus' => 'Número SUS do pai',
                'father.nis' => 'NIS (PIS/PASEP) do pai',
                'father.occupation' => 'Ocupação do pai',
                'father.organization' => 'Empresa do pai',
                'father.monthly_income' => 'Renda Mensal do pai',
                'father.gender' => 'Gênero do pai',
                'father.phone' => 'Telefones do pai',
            ],
            'Responsável' => [
                'guardian.id' => 'ID do responsável',
                'guardian.name' => 'Nome do responsável',
                'guardian.social_name' => 'Nome social e/ou afetivo do responsável',
                'guardian.cpf' => 'CPF do responsável',
                'guardian.rg' => 'RG do responsável',
                'guardian.rg_issue_date' => 'RG (Data Emissão) do responsável',
                'guardian.rg_state_abbreviation' => 'RG (Estado) do responsável',
                'guardian.date_of_birth' => 'Data de nascimento do responsável',
                'guardian.email' => 'E-mail do responsável',
                'guardian.sus' => 'Número SUS do responsável',
                'guardian.nis' => 'NIS (PIS/PASEP) do responsável',
                'guardian.occupation' => 'Ocupação do responsável',
                'guardian.organization' => 'Empresa do responsável',
                'guardian.monthly_income' => 'Renda Mensal do responsável',
                'guardian.gender' => 'Gênero do responsável',
                'guardian.phone' => 'Telefones do responsável',
            ],
            'Uniforme' => [
                'uniform_distributions.type' => 'Tipo',
                'uniform_distributions.distribution_date' => 'Data de Distribuição',
                'uniform_distributions.complete_kit' => 'Kit Completo',
                'uniform_distributions.coat_pants_qty' => 'Agasalho Calça (Quantidade)',
                'uniform_distributions.coat_pants_tm' => 'Agasalho Calça (Tamanho)',
                'uniform_distributions.coat_jacket_qty' => 'Agasalho Jaqueta (Quantidade)',
                'uniform_distributions.coat_jacket_tm' => 'Agasalho Jaqueta (Tamanho)',
                'uniform_distributions.shirt_short_qty' => 'Camiseta Curta (Quantidade)',
                'uniform_distributions.shirt_short_tm' => 'Camiseta Curta (Tamanho)',
                'uniform_distributions.shirt_long_qty' => 'Camiseta Longa (Quantidade)',
                'uniform_distributions.shirt_long_tm' => 'Camiseta Longa (Tamanho)',
                'uniform_distributions.socks_qty' => 'Meias (Quantidade)',
                'uniform_distributions.socks_tm' => 'Meias (Tamanho)',
                'uniform_distributions.shorts_tactel_qty' => 'Bermuda masculina (tecidos diversos) (Quantidade)',
                'uniform_distributions.shorts_tactel_tm' => 'Bermuda masculina (tecidos diversos) (Tamanho)',
                'uniform_distributions.shorts_coton_qty' => 'Bermuda feminina (tecidos diversos) (Quantidade)',
                'uniform_distributions.shorts_coton_tm' => 'Bermuda feminina (tecidos diversos) (Tamanho)',
                'uniform_distributions.sneakers_qty' => 'Tênis (Quantidade)',
                'uniform_distributions.sneakers_tm' => 'Tênis (Tamanho)',
                'uniform_distributions.kids_shirt_qty' => 'Camiseta Infantil (Quantidade)',
                'uniform_distributions.kids_shirt_tm' => 'Camiseta Infantil (Tamanho)',
                'uniform_distributions.pants_jeans_qty' => 'Calça Jeans (Quantidade)',
                'uniform_distributions.pants_jeans_tm' => 'Calça Jeans (Tamanho)',
                'uniform_distributions.skirt_qty' => 'Saia (Quantidade)',
                'uniform_distributions.skirt_tm' => 'Saia (Tamanho)',
            ],
        ];

        $this->assertJsonStringEqualsJsonString(collect($expected), collect($this->model->getExportedColumnsByGroup()));
    }

    public function testGetLegacyColumns(): void
    {
        $expected = [
            'mother.person' => [
                'id' => 'm.idpes as ID da mãe',
                'name' => 'm.nome as Nome da mãe',
                'email' => 'm.email as E-mail da mãe',
            ],
            'mother.individual' => [
                'social_name' => 'mf.nome_social as Nome social e/ou afetivo da mãe',
                'cpf' => DB::raw('trim(to_char(mf.cpf, \'000"."000"."000"-"00\')) as "CPF da mãe"'),
                'date_of_birth' => 'mf.data_nasc as Data de nascimento da mãe',
                'sus' => 'mf.sus as Número SUS da mãe',
                'nis' => 'mf.nis_pis_pasep as NIS (PIS/PASEP) da mãe',
                'occupation' => 'mf.ocupacao as Ocupação da mãe',
                'organization' => 'mf.empresa as Empresa da mãe',
                'monthly_income' => 'mf.renda_mensal as Renda Mensal da mãe',
                'gender' => 'mf.sexo as Gênero da mãe',
            ],
            'mother.document' => [
                'rg' => 'md.rg as RG da mãe',
                'rg_issue_date' => 'md.data_exp_rg as RG (Data Emissão) da mãe',
                'rg_state_abbreviation' => 'md.sigla_uf_exp_rg as RG (Estado) da mãe',
            ],
            'mother.phone' => [
                'phone' => 'mep.phones as Telefones da mãe',
            ],
            'father.person' => [
                'id' => 'f.idpes as ID do pai',
                'name' => 'f.nome as Nome do pai',
                'email' => 'f.email as E-mail do pai',
            ],
            'father.individual' => [
                'social_name' => 'ff.nome_social as Nome social e/ou afetivo do pai',
                'cpf' => DB::raw('trim(to_char(ff.cpf, \'000"."000"."000"-"00\')) as "CPF do pai"'),
                'date_of_birth' => 'ff.data_nasc as Data de nascimento do pai',
                'sus' => 'ff.sus as Número SUS do pai',
                'nis' => 'ff.nis_pis_pasep as NIS (PIS/PASEP) do pai',
                'occupation' => 'ff.ocupacao as Ocupação do pai',
                'organization' => 'ff.empresa as Empresa do pai',
                'monthly_income' => 'ff.renda_mensal as Renda Mensal do pai',
                'gender' => 'ff.sexo as Gênero do pai',
            ],
            'father.document' => [
                'rg' => 'fd.rg as RG do pai',
                'rg_issue_date' => 'fd.data_exp_rg as RG (Data Emissão) do pai',
                'rg_state_abbreviation' => 'fd.sigla_uf_exp_rg as RG (Estado) do pai',
            ],
            'father.phone' => [
                'phone' => 'fep.phones as Telefones do pai',
            ],
            'guardian.person' => [
                'id' => 'g.idpes as ID do responsável',
                'name' => 'g.nome as Nome do responsável',
                'email' => 'g.email as E-mail do responsável',
            ],
            'guardian.individual' => [
                'social_name' => 'gf.nome_social as Nome social e/ou afetivo do responsável',
                'cpf' => DB::raw('trim(to_char(gf.cpf, \'000"."000"."000"-"00\')) as "CPF do responsável"'),
                'date_of_birth' => 'gf.data_nasc as Data de nascimento do responsável',
                'sus' => 'gf.sus as Número SUS do responsável',
                'nis' => 'gf.nis_pis_pasep as NIS (PIS/PASEP) do responsável',
                'occupation' => 'gf.ocupacao as Ocupação do responsável',
                'organization' => 'gf.empresa as Empresa do responsável',
                'monthly_income' => 'gf.renda_mensal as Renda Mensal do responsável',
                'gender' => 'gf.sexo as Gênero do responsável',
            ],
            'guardian.document' => [
                'rg' => 'gd.rg as RG do responsável',
                'rg_issue_date' => 'gd.data_exp_rg as RG (Data Emissão) do responsável',
                'rg_state_abbreviation' => 'gd.sigla_uf_exp_rg as RG (Estado) do responsável',
            ],
            'guardian.phone' => [
                'phone' => 'gep.phones as Telefones do responsável',
            ],
            'place' => [
                'address' => 'p.address as Logradouro',
                'number' => 'p.number as Número',
                'complement' => 'p.complement as Complemento',
                'neighborhood' => 'p.neighborhood as Bairro',
                'postal_code' => 'p.postal_code as CEP',
                'latitude' => 'p.latitude as Latitude',
                'longitude' => 'p.longitude as Longitude',
                'city' => 'c.name as Cidade',
                'state_abbreviation' => 's.abbreviation as Sigla do Estado',
                'state' => 's.name as Estado',
                'country' => 'cn.name as País',
            ],
        ];

        $this->assertJsonStringEqualsJsonString(collect($expected), collect($this->model->getLegacyColumns()));
    }

    protected function getViewModelName(): string
    {
        return Student::class;
    }
}
