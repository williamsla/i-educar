<?php

use App\Models\Fornecedor;
use App\Process;
use Illuminate\Support\Facades\DB;

return new class extends clsListagem
{
    public function Gerar()
    {
        $this->titulo = 'Fornecedores';
        
        $par_nome = str_replace(['[', ']', '{', '}', '(', ')', '\\', '/'], '', $this->getQueryString(name: 'fantasia')) ?: false;
        
        $this->addCabecalhos(
            coluna: [
                'Nome',
            ]
        );
        
        $this->campoTexto(
            nome: 'fantasia',
            campo: 'Nome',
            valor: $par_nome,
            tamanhovisivel: '50',
            tamanhomaximo: '255'
        );
        
        // Paginador
        $limite = 10;
        $iniciolimit = ($this->getQueryString(name: "pagina_{$this->nome}")) ? $this->getQueryString(name: "pagina_{$this->nome}") * $limite - $limite : 0;

        $lista = DB::table('cadastro.fornecedor')
                    ->select('cadastro.juridica.fantasia')
                    ->join('cadastro.juridica', 'idpes', 'ref_idpes')
                    ->orderBy('cadastro.juridica.fantasia')
                    ->paginate(
                        perPage: $limite,
                        pageName: "pagina_{$this->nome}",
                    );

        $total = $lista->total();

        if ($lista->isNotEmpty()) {
            foreach ($lista as $pessoa) {
                $nome = $pessoa->fantasia;

                if ($pessoa->social_name) {
                    $nome = $pessoa->social_name . '<br> <i>Nome de registro: </i>' . $pessoa->name;
                }

                $this->addLinhas(linha: [
                    "<img src='imagens/noticia.jpg' border=0><a href='educar_fornecedor_detalhe.php?cod_fornecedor={$cod}'>$nome</a>"
                ]);
            }
        }

        $obj_permissao = new clsPermissoes;

        if ($obj_permissao->permissao_cadastra(
            int_processo_ap: Process::FORNECEDORES,
            int_idpes_usuario: $this->pessoa_logada,
            int_soma_nivel_acesso: 7,
            super_usuario: true
        )) {
            $this->acao = 'go("educar_fornecedor_cadastro.php")';
            $this->nome_acao = 'Novo';
        }

        $this->largura = '100%';
        $this->addPaginador2(
            strUrl: 'educar_fornecedor.php',
            intTotalRegistros: $total,
            mixVariaveisMantidas: $_GET,
            nome: $this->nome,
            intResultadosPorPagina: $limite
        );

        /*
        $this->breadcrumb(
            currentPage: 'Listagem de fornecedores',
            breadcrumbs: ['educar_fornecedor.php' => 'Fornecedores']
        );
        */
    }

    public function Formular()
    {
        $this->title = 'Fornecedores';
        $this->processoAp = Process::FORNECEDORES;
    }
};
