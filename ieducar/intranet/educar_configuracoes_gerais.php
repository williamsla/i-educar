<?php

use iEducar\Reports\Contracts\TeacherReportCard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Helpers\ImageUploadHelper;

return new class extends clsCadastro
{
    public $pessoa_logada;
    public $ref_cod_instituicao;
    public $permite_relacionamento_posvendas;
    public $url_novo_educacao;
    public $token_novo_educacao;
    public $mostrar_codigo_inep_aluno;
    public $justificativa_falta_documentacao_obrigatorio;
    public $tamanho_min_rede_estadual;
    public $modelo_boletim_professor;
    public $url_cadastro_usuario;
    public $active_on_ieducar;
    public $ieducar_image;
    public $ieducar_background_image;
    public $ieducar_background_image_url;
    public $ieducar_entity_name;
    public $ieducar_login_footer;
    public $ieducar_external_footer;
    public $ieducar_internal_footer;
    public $facebook_url;
    public $twitter_url;
    public $linkedin_url;
    public $ieducar_suspension_message;
    public $bloquear_cadastro_aluno;
    public $situacoes_especificas_atestados;
    public $emitir_ato_autorizativo;
    public $emitir_ato_criacao_credenciamento;
    public $url_diario_professor;
    public $mostrar_botoes_ajuda_login;
    public $url_whatsapp;

    private $maxLogoSize = 4 * 1024 * 1024;
    private $maxBackgroundSize = 4 * 1024 * 1024;

    public function Inicializar()
    {
        if (!headers_sent()) {
            @ini_set('post_max_size', '10M');
            @ini_set('upload_max_filesize', '5M');
            @ini_set('max_execution_time', '300');
        }

        $obj_permissoes = new clsPermissoes;
        $nivel = $obj_permissoes->nivel_acesso(int_idpes_usuario: $this->pessoa_logada);

        if ($nivel != 1) {
            $this->simpleRedirect(url: 'educar_index.php');
        }

        $obj_permissoes->permissao_cadastra(
            int_processo_ap: 999873,
            int_idpes_usuario: $this->pessoa_logada,
            int_soma_nivel_acesso: 7,
            str_pagina_redirecionar: 'educar_index.php'
        );
        $this->ref_cod_instituicao = $obj_permissoes->getInstituicao(int_idpes_usuario: $this->pessoa_logada);

        $this->breadcrumb(currentPage: 'Configurações gerais', breadcrumbs: [
            url(path: 'intranet/educar_configuracoes_index.php') => 'Configurações',
        ]);

        return 'Editar';
    }

    public function Gerar()
    {
        $obj_permissoes = new clsPermissoes;
        $ref_cod_instituicao = $obj_permissoes->getInstituicao(int_idpes_usuario: $this->pessoa_logada);

        $configuracoes = new clsPmieducarConfiguracoesGerais(ref_cod_instituicao: $ref_cod_instituicao);
        $configuracoes = $configuracoes->detalhe();

        $this->permite_relacionamento_posvendas = $configuracoes['permite_relacionamento_posvendas'];
        $this->bloquear_cadastro_aluno = dbBool(val: $configuracoes['bloquear_cadastro_aluno']);
        $this->situacoes_especificas_atestados = dbBool(val: $configuracoes['situacoes_especificas_atestados']);
        $this->url_novo_educacao = $configuracoes['url_novo_educacao'];
        $this->token_novo_educacao = $configuracoes['token_novo_educacao'];
        $this->mostrar_codigo_inep_aluno = $configuracoes['mostrar_codigo_inep_aluno'];
        $this->justificativa_falta_documentacao_obrigatorio = $configuracoes['justificativa_falta_documentacao_obrigatorio'];
        $this->tamanho_min_rede_estadual = $configuracoes['tamanho_min_rede_estadual'];
        $this->modelo_boletim_professor = $configuracoes['modelo_boletim_professor'];
        $this->url_cadastro_usuario = $configuracoes['url_cadastro_usuario'];
        $this->active_on_ieducar = $configuracoes['active_on_ieducar'];
        $this->ieducar_image = $configuracoes['ieducar_image'];
        $this->ieducar_background_image = $configuracoes['ieducar_background_image'] ?? null;
        $this->ieducar_background_image_url = $configuracoes['ieducar_background_image_url'] ?? null;
        $this->ieducar_entity_name = $configuracoes['ieducar_entity_name'];
        $this->ieducar_login_footer = $configuracoes['ieducar_login_footer'];
        $this->ieducar_external_footer = $configuracoes['ieducar_external_footer'];
        $this->ieducar_internal_footer = $configuracoes['ieducar_internal_footer'];
        $this->facebook_url = $configuracoes['facebook_url'];
        $this->twitter_url = $configuracoes['twitter_url'];
        $this->linkedin_url = $configuracoes['linkedin_url'];
        $this->ieducar_suspension_message = $configuracoes['ieducar_suspension_message'];
        $this->emitir_ato_autorizativo = dbBool(val: $configuracoes['emitir_ato_autorizativo']);
        $this->emitir_ato_criacao_credenciamento = dbBool(val: $configuracoes['emitir_ato_criacao_credenciamento']);
        $this->url_diario_professor = $configuracoes['url_diario_professor'] ?? '';
        $this->mostrar_botoes_ajuda_login = dbBool(val: $configuracoes['mostrar_botoes_ajuda_login'] ?? true);
        $this->url_whatsapp = $configuracoes['url_whatsapp'] ?? '';

        $this->campoRotulo(
            'upload_warning',
            '⚠️ Aviso importante:',
            '<div style="background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                <strong>Limite de tamanho de arquivos:</strong> Para evitar erros, use imagens com até 4MB cada.<br>
                <strong>Dica:</strong> Para melhor performance, comprima suas imagens antes de enviar usando ferramentas como:
                <a href="https://tinypng.com" target="_blank">TinyPNG</a> ou 
                <a href="https://compressjpeg.com" target="_blank">CompressJPEG</a>
            </div>'
        );

        $this->inputsHelper()->checkbox(attrName: 'permite_relacionamento_posvendas', inputOptions: [
            'label' => 'Permite relacionamento direto no pós-venda?',
            'value' => $this->permite_relacionamento_posvendas ? 'on' : '',
        ]);

        $this->inputsHelper()->checkbox(attrName: 'bloquear_cadastro_aluno', inputOptions: [
            'label' => 'Bloquear o cadastro de novos alunos',
            'value' => $this->bloquear_cadastro_aluno ? 'on' : '',
        ]);

        $this->inputsHelper()->checkbox(attrName: 'situacoes_especificas_atestados', inputOptions: [
            'label' => 'Exibir apenas matrículas em situações específicas para os atestados',
            'value' => $this->situacoes_especificas_atestados ? 'on' : '',
        ]);

        $this->inputsHelper()->checkbox(attrName: 'emitir_ato_autorizativo', inputOptions: [
            'label' => 'Emite ato autorizativo nos cabeçalhos de histórico escolar (modelos padrão)',
            'value' => $this->emitir_ato_autorizativo ? 'on' : '',
        ]);

        $this->inputsHelper()->checkbox(attrName: 'emitir_ato_criacao_credenciamento', inputOptions: [
            'label' => 'Emite lei de criação e credenciamento nos cabeçalhos de histórico escolar (modelos padrão)',
            'value' => $this->emitir_ato_criacao_credenciamento ? 'on' : '',
        ]);

        $this->inputsHelper()->text(attrNames: 'url_novo_educacao', inputOptions: [
            'label' => 'URL de integração (API)',
            'size' => 100,
            'max_length' => 100,
            'required' => false,
            'placeholder' => 'Ex: http://cliente.provedor.com.br/api/v1/',
            'value' => $this->url_novo_educacao,
        ]);

        $this->inputsHelper()->text(attrNames: 'token_novo_educacao', inputOptions: [
            'label' => 'Token de integração (API)',
            'size' => 100,
            'max_length' => 100,
            'required' => false,
            'value' => $this->token_novo_educacao,
        ]);

        $options = [
            'label' => 'Mostrar código INEP nas telas de cadastro de aluno?',
            'value' => $this->mostrar_codigo_inep_aluno,
            'required' => true,
        ];
        $this->inputsHelper()->booleanSelect(attrName: 'mostrar_codigo_inep_aluno', inputOptions: $options);

        $options = [
            'label' => 'Campo "Justificativa para a falta de documentação" no cadastro de alunos deve ser obrigatório?',
            'value' => $this->justificativa_falta_documentacao_obrigatorio,
            'required' => true,
        ];
        $this->inputsHelper()->booleanSelect(attrName: 'justificativa_falta_documentacao_obrigatorio', inputOptions: $options);

        $this->inputsHelper()->integer(attrName: 'tamanho_min_rede_estadual', inputOptions: [
            'label' => 'Tamanho mínimo do campo "Código rede estadual" no cadastro de alunos ',
            'label_hint' => 'Deixe vazio no caso de não ter limite mínino',
            'max_length' => 3,
            'required' => false,
            'placeholder' => '',
            'value' => $this->tamanho_min_rede_estadual,
        ]);

        $teacherReporcCard = app(TeacherReportCard::class);
        $options = [
            'label' => 'Modelo do boletim do professor',
            'resources' => $teacherReporcCard->getOptions(),
            'value' => $this->modelo_boletim_professor,
        ];

        $this->inputsHelper()->select(attrName: 'modelo_boletim_professor', inputOptions: $options);

        $this->inputsHelper()->text(attrNames: 'url_cadastro_usuario', inputOptions: [
            'label' => 'URL da ferramenta de cadastro de usuários',
            'label_hint' => 'Deixe vazio para desabilitar a ferramenta',
            'size' => 100,
            'max_length' => 255,
            'required' => false,
            'placeholder' => 'Ex: http://login.ieducar.com.br/cliente',
            'value' => $this->url_cadastro_usuario,
        ]);

        $this->inputsHelper()->booleanSelect(attrName: 'active_on_ieducar', inputOptions: [
            'label' => 'Ativo no i-educar?',
            'value' => $this->active_on_ieducar,
            'required' => true,
        ]);

        $this->inputsHelper()->text(attrNames: 'ieducar_suspension_message', inputOptions: [
            'label' => 'Mensagem de suspensão',
            'size' => 100,
            'max_length' => 255,
            'required' => false,
            'value' => $this->ieducar_suspension_message,
        ]);

        $this->campoRotulo('logo_section', 'Logo do sistema', '<hr style="margin: 20px 0;">');
        
        $this->inputsHelper()->text(attrNames: 'ieducar_image', inputOptions: [
            'label' => 'URL da logo (opcional)',
            'size' => 100,
            'max_length' => 500,
            'required' => false,
            'placeholder' => 'Ex: https://exemplo.com/logo.jpg ou https://exemplo.com/logo.png',
            'value' => $this->ieducar_image,
            'help' => 'Insira uma URL externa da logo (aceita .jpg, .jpeg, .png, .gif, .webp, .svg)'
        ]);

        $this->campoArquivo(
            'ieducar_image_file', 
            'OU faça upload de uma imagem:', 
            null, 
            'Selecione uma imagem para o logo (JPG, PNG, WebP - Máx. 4MB)', 
            40, 
            false
        );

        if ($this->ieducar_image) {
            $logoUrl = ImageUploadHelper::getImageUrl($this->ieducar_image);
            if ($logoUrl) {
                $this->campoRotulo(
                    'preview_logo', 
                    'Preview da logo:', 
                    '<div style="margin-top: 15px; padding: 15px; border: 1px solid #ddd; background-color: #f9f9f9; border-radius: 5px;">
                        <div style="margin-bottom: 10px; font-weight: bold;">Logo atual:</div>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="flex: 0 0 120px;">
                                <img src="' . $logoUrl . '" 
                                     style="max-height: 80px; max-width: 120px; border: 1px solid #ddd; padding: 5px; background: white;">
                            </div>
                            <div style="flex: 1;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 5px;">
                                    <a href="' . $logoUrl . '" target="_blank" style="color: #3498db;">🔗 Ver imagem completa</a>
                                </div>
                                <div style="font-size: 12px; color: #666;">
                                    <em>Dica: Para alterar, faça novo upload ou insira nova URL.</em>
                                </div>
                            </div>
                        </div>
                    </div>'
                );
            }
        }

        $this->campoRotulo('background_section', 'Imagem de fundo da tela de login', '<hr style="margin: 20px 0;">');
        
        $this->campoRotulo(
            'background_info', 
            '', 
            '<div style="background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px;">
                <p style="margin: 5px 0;"><strong>Instruções:</strong> A imagem aparecerá atrás da logo branca com 15% de opacidade.</p>
                <p style="margin: 5px 0;"><strong>Dica:</strong> Para substituir uma imagem, basta fazer um novo upload ou alterar a URL.</p>
            </div>'
        );

        $this->inputsHelper()->text(attrNames: 'ieducar_background_image_url', inputOptions: [
            'label' => 'URL da imagem de fundo',
            'size' => 100,
            'max_length' => 500,
            'required' => false,
            'placeholder' => 'Ex: https://exemplo.com/fundo.jpg ou https://exemplo.com/fundo.png',
            'value' => $this->ieducar_background_image_url,
            'help' => 'Insira uma URL externa da imagem de fundo'
        ]);

        $this->campoArquivo(
            'ieducar_background_image_file', 
            'OU faça upload de uma imagem:', 
            $this->ieducar_background_image, 
            'Selecione uma imagem (JPG, PNG, WebP - Recomendado: 1920x1080px, Máx. 4MB)', 
            40, 
            false
        );

        $this->campoRotulo(
            'technical_info', 
            'Informações:', 
            '<div style="background-color: #e8f4fd; padding: 10px; margin: 10px 0; font-size: 13px;">
                <strong>Formatos:</strong> .jpg, .jpeg, .png, .gif, .webp<br>
                <strong>Dimensões recomendadas:</strong> 1920x1080 pixels<br>
                <strong>Tamanho máximo:</strong> 4MB<br>
                <strong>Prioridade:</strong> URL > Upload > Imagem padrão
            </div>'
        );

        $currentBackgroundUrl = null;
        $sourceType = '';
        
        if (!empty($this->ieducar_background_image_url)) {
            $currentBackgroundUrl = $this->ieducar_background_image_url;
            $sourceType = 'URL externa';
        } elseif (!empty($this->ieducar_background_image)) {
            $currentBackgroundUrl = ImageUploadHelper::getImageUrl($this->ieducar_background_image);
            $sourceType = 'Upload local';
        }
        
        if ($currentBackgroundUrl) {
            $previewHtml = '<div style="margin-top: 15px; padding: 15px; border: 1px solid #ddd; background-color: #f9f9f9; border-radius: 5px;">
                <div style="margin-bottom: 10px; font-weight: bold;">Preview da imagem de fundo:</div>
                <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                    <div style="flex: 0 0 250px;">
                        <div style="width: 250px; height: 140px; border: 1px solid #ccc; overflow: hidden; background-color: #2c3e50;">
                            <img src="' . htmlspecialchars($currentBackgroundUrl) . '" 
                                 style="width: 100%; height: 100%; object-fit: cover; opacity: 0.15;" 
                                 alt="Preview"
                                 onerror="this.style.opacity=\'1\'; this.src=\'https://via.placeholder.com/250x140/2c3e50/ffffff?text=Imagem+não+carregada\'">
                        </div>
                        <div style="text-align: center; margin-top: 5px;">
                            <a href="' . htmlspecialchars($currentBackgroundUrl) . '" target="_blank" style="font-size: 12px; color: #3498db;">🔗 Ver imagem original</a>
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <div style="margin-bottom: 10px;">
                            <strong>Como aparecerá:</strong> Fundo atrás da logo branca com 15% de opacidade
                        </div>
                        <div style="display: inline-block; position: relative; width: 200px; height: 112px; border: 1px solid #ccc; background-color: #2c3e50; overflow: hidden; border-radius: 4px;">
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: url(' . htmlspecialchars($currentBackgroundUrl) . ') center/cover no-repeat; opacity: 0.15;"></div>
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 40px; height: 40px; background-color: white; border-radius: 50%; opacity: 0.8;"></div>
                        </div>
                        <div style="margin-top: 10px; font-size: 12px; color: #666;">
                            <div><strong>Fonte:</strong> ' . $sourceType . '</div>
                            <div><em>Para alterar, faça novo upload ou insira nova URL.</em></div>
                        </div>
                    </div>
                </div>
            </div>';
            
            $this->campoRotulo('preview_background', '', $previewHtml);
        } else {
            $this->campoRotulo(
                'no_background', 
                'Preview:', 
                '<div style="margin-top: 10px; padding: 15px; background-color: #f5f5f5; border: 1px dashed #ccc; border-radius: 5px; text-align: center; color: #666;">
                    <i class="fas fa-image" style="font-size: 24px; margin-bottom: 5px; display: block; color: #999;"></i>
                    Nenhuma imagem de fundo configurada.<br>
                    <span style="font-size: 13px;">Será usada a imagem padrão.</span>
                </div>'
            );
        }

        $this->campoRotulo('help_buttons_section', 'Configurações dos Botões de Ajuda no Login', '<hr style="margin: 20px 0;">');
        
        $this->inputsHelper()->checkbox(attrName: 'mostrar_botoes_ajuda_login', inputOptions: [
            'label' => 'Mostrar botões "Precisa de Ajuda?" na tela de login',
            'value' => $this->mostrar_botoes_ajuda_login ? 'on' : '',
            'help' => 'Se marcado, mostra os botões de ajuda na tela de login'
        ]);

        $this->inputsHelper()->text(attrNames: 'url_diario_professor', inputOptions: [
            'label' => 'URL do Diário do Professor',
            'size' => 100,
            'max_length' => 500,
            'required' => false,
            'placeholder' => 'Ex: https://diario.professor.com.br ou http://seu-sistema.com/diario',
            'value' => $this->url_diario_professor,
            'help' => 'Esta URL será usada no botão "Diário do Professor" na tela de login'
        ]);

        $this->inputsHelper()->text(attrNames: 'url_whatsapp', inputOptions: [
            'label' => 'URL do WhatsApp',
            'size' => 100,
            'max_length' => 500,
            'required' => false,
            'placeholder' => 'Ex: https://wa.me/5582999999999 ou https://api.whatsapp.com/send?phone=5582999999999',
            'value' => $this->url_whatsapp,
            'help' => 'Esta URL será usada no botão "Suporte via WhatsApp" na tela de login'
        ]);

        $this->inputsHelper()->text(attrNames: 'ieducar_entity_name', inputOptions: [
            'label' => 'Nome da entidade',
            'size' => 100,
            'max_length' => 255,
            'required' => false,
            'value' => $this->ieducar_entity_name,
        ]);

        $this->inputsHelper()->textArea(attrName: 'ieducar_login_footer', inputOptions: [
            'label' => 'Rodapé do login',
            'size' => 100,
            'rows' => 3,
            'required' => false,
            'value' => $this->ieducar_login_footer,
        ]);

        $this->inputsHelper()->textArea(attrName: 'ieducar_external_footer', inputOptions: [
            'label' => 'Rodapé externo',
            'size' => 100,
            'rows' => 3,
            'required' => false,
            'value' => $this->ieducar_external_footer,
        ]);

        $this->inputsHelper()->textArea(attrName: 'ieducar_internal_footer', inputOptions: [
            'label' => 'Rodapé interno',
            'size' => 100,
            'rows' => 3,
            'required' => false,
            'value' => $this->ieducar_internal_footer,
        ]);

        $this->inputsHelper()->text(attrNames: 'facebook_url', inputOptions: [
            'label' => 'Facebook',
            'size' => 100,
            'max_length' => 255,
            'required' => false,
            'placeholder' => 'Ex: http://www.facebook.com/nome',
            'value' => $this->facebook_url,
        ]);

        $this->inputsHelper()->text(attrNames: 'twitter_url', inputOptions: [
            'label' => 'Twitter',
            'size' => 100,
            'max_length' => 255,
            'required' => false,
            'placeholder' => 'Ex: http://twitter.com/nome',
            'value' => $this->twitter_url,
        ]);

        $this->inputsHelper()->text(attrNames: 'linkedin_url', inputOptions: [
            'label' => 'LinkedIn',
            'size' => 100,
            'max_length' => 255,
            'required' => false,
            'placeholder' => 'Ex: https://www.linkedin.com/company/nome/',
            'value' => $this->linkedin_url,
        ]);
    }

    public function Editar()
    {
        // *** DEBUG: REMOVER APÓS RESOLVER ***
        error_log("=== INÍCIO EDITAR ===");
        error_log("POST recebido: " . print_r(array_keys($_POST), true));
        error_log("FILES recebido: " . print_r(array_keys($_FILES), true));
        
        if (isset($_FILES['ieducar_background_image_file'])) {
            error_log("Background file - Nome: " . ($_FILES['ieducar_background_image_file']['name'] ?? 'N/A'));
            error_log("Background file - Erro: " . ($_FILES['ieducar_background_image_file']['error'] ?? 'N/A'));
            error_log("Background file - Tamanho: " . ($_FILES['ieducar_background_image_file']['size'] ?? 'N/A'));
        }
        
        $obj_permissoes = new clsPermissoes;
        $ref_cod_instituicao = $obj_permissoes->getInstituicao(int_idpes_usuario: $this->pessoa_logada);
        
        $this->permite_relacionamento_posvendas = $_POST['permite_relacionamento_posvendas'] ?? '';
        $this->bloquear_cadastro_aluno = $_POST['bloquear_cadastro_aluno'] ?? '';
        $this->situacoes_especificas_atestados = $_POST['situacoes_especificas_atestados'] ?? '';
        $this->url_novo_educacao = $_POST['url_novo_educacao'] ?? '';
        $this->token_novo_educacao = $_POST['token_novo_educacao'] ?? '';
        $this->mostrar_codigo_inep_aluno = $_POST['mostrar_codigo_inep_aluno'] ?? '';
        $this->justificativa_falta_documentacao_obrigatorio = $_POST['justificativa_falta_documentacao_obrigatorio'] ?? '';
        $this->tamanho_min_rede_estadual = $_POST['tamanho_min_rede_estadual'] ?? '';
        $this->modelo_boletim_professor = $_POST['modelo_boletim_professor'] ?? '';
        $this->url_cadastro_usuario = $_POST['url_cadastro_usuario'] ?? '';
        $this->active_on_ieducar = $_POST['active_on_ieducar'] ?? '';
        $this->ieducar_entity_name = $_POST['ieducar_entity_name'] ?? '';
        $this->ieducar_login_footer = $_POST['ieducar_login_footer'] ?? '';
        $this->ieducar_external_footer = $_POST['ieducar_external_footer'] ?? '';
        $this->ieducar_internal_footer = $_POST['ieducar_internal_footer'] ?? '';
        $this->facebook_url = $_POST['facebook_url'] ?? '';
        $this->twitter_url = $_POST['twitter_url'] ?? '';
        $this->linkedin_url = $_POST['linkedin_url'] ?? '';
        $this->ieducar_suspension_message = $_POST['ieducar_suspension_message'] ?? '';
        $this->emitir_ato_autorizativo = $_POST['emitir_ato_autorizativo'] ?? '';
        $this->emitir_ato_criacao_credenciamento = $_POST['emitir_ato_criacao_credenciamento'] ?? '';
        $this->mostrar_botoes_ajuda_login = $_POST['mostrar_botoes_ajuda_login'] ?? '';
        $this->url_diario_professor = $_POST['url_diario_professor'] ?? '';
        $this->url_whatsapp = $_POST['url_whatsapp'] ?? '';
        
        $permiteRelacionamentoPosvendas = ($this->permite_relacionamento_posvendas == 'on' ? 1 : 0);
        $bloquearCadastroAluno = $this->bloquear_cadastro_aluno == 'on' ? 1 : 0;
        $situacoesEspecificasAtestados = $this->situacoes_especificas_atestados == 'on' ? 1 : 0;
        $emitir_ato_autorizativo = $this->emitir_ato_autorizativo == 'on' ? 1 : 0;
        $emitir_ato_criacao_credenciamento = $this->emitir_ato_criacao_credenciamento == 'on' ? 1 : 0;
        $mostrarBotoesAjudaLogin = $this->mostrar_botoes_ajuda_login == 'on' ? 1 : 0;

        if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
            $this->mensagem = 'Erro: O tamanho total dos dados enviados excede o limite permitido pelo servidor.<br>
                              <strong>Solução:</strong> Reduza o tamanho das imagens antes de enviar (máx 4MB cada).<br>
                              Use ferramentas online para comprimir: 
                              <a href="https://tinypng.com" target="_blank">TinyPNG</a> ou 
                              <a href="https://compressjpeg.com" target="_blank">CompressJPEG</a>';
            return false;
        }

        // Obter valores atuais do banco
        $configuracoes = new clsPmieducarConfiguracoesGerais(ref_cod_instituicao: $ref_cod_instituicao);
        $detalhe = $configuracoes->detalhe();
        
        error_log("Valores atuais no banco:");
        error_log("ieducar_image: " . ($detalhe['ieducar_image'] ?? 'NULL'));
        error_log("ieducar_background_image: " . ($detalhe['ieducar_background_image'] ?? 'NULL'));
        error_log("ieducar_background_image_url: " . ($detalhe['ieducar_background_image_url'] ?? 'NULL'));
        
        $ieducar_image = $detalhe['ieducar_image'] ?? null;
        $ieducar_background_image = $detalhe['ieducar_background_image'] ?? null;
        $ieducar_background_image_url = $detalhe['ieducar_background_image_url'] ?? null;

        // Processar LOGO
        if (isset($_FILES['ieducar_image_file']['error']) && $_FILES['ieducar_image_file']['error'] == 0) {
            try {
                error_log("Processando upload de logo...");
                $ieducar_image = ImageUploadHelper::uploadImage(
                    $_FILES['ieducar_image_file'], 
                    $ieducar_image, 
                    'logos', 
                    $this->maxLogoSize
                );
                error_log("Logo salvo: " . $ieducar_image);
            } catch (\Exception $e) {
                error_log("ERRO ao processar logo: " . $e->getMessage());
                $this->mensagem = 'Erro ao processar logo: ' . $e->getMessage() . '<br>';
                return false;
            }
        }
        elseif (isset($_POST['ieducar_image']) && !empty(trim($_POST['ieducar_image']))) {
            $url = trim($_POST['ieducar_image']);
            if (ImageUploadHelper::validateImageUrl($url)) {
                $ieducar_image = $url;
                if ($detalhe['ieducar_image'] && !filter_var($detalhe['ieducar_image'], FILTER_VALIDATE_URL)) {
                    ImageUploadHelper::deleteImage($detalhe['ieducar_image']);
                }
            }
        }

        // Processar IMAGEM DE FUNDO - CORREÇÃO CRÍTICA
        error_log("=== PROCESSANDO BACKGROUND ===");
        
        if (isset($_FILES['ieducar_background_image_file']['error']) && $_FILES['ieducar_background_image_file']['error'] == 0) {
            try {
                error_log("Upload de background detectado!");
                error_log("Nome: " . $_FILES['ieducar_background_image_file']['name']);
                error_log("Tamanho: " . $_FILES['ieducar_background_image_file']['size']);
                error_log("Tipo: " . $_FILES['ieducar_background_image_file']['type']);
                
                $ieducar_background_image = ImageUploadHelper::uploadImage(
                    $_FILES['ieducar_background_image_file'], 
                    $ieducar_background_image, 
                    'backgrounds', 
                    $this->maxBackgroundSize
                );
                $ieducar_background_image_url = null; // Limpa URL quando faz upload
                
                error_log("Upload concluído com sucesso!");
                error_log("Caminho salvo: " . $ieducar_background_image);
                
            } catch (\Exception $e) {
                error_log("ERRO FATAL ao processar background: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $this->mensagem = 'Erro ao processar imagem de fundo: ' . $e->getMessage() . '<br>';
                return false;
            }
        }
        elseif (isset($_POST['ieducar_background_image_url']) && !empty(trim($_POST['ieducar_background_image_url']))) {
            error_log("URL de background detectada: " . $_POST['ieducar_background_image_url']);
            $url = trim($_POST['ieducar_background_image_url']);
            if (ImageUploadHelper::validateImageUrl($url)) {
                $ieducar_background_image_url = $url;
                // Se estava usando upload local e agora usa URL, remove o arquivo antigo
                if ($ieducar_background_image) {
                    ImageUploadHelper::deleteImage($ieducar_background_image);
                    $ieducar_background_image = null;
                }
                error_log("URL validada e salva: " . $ieducar_background_image_url);
            } else {
                error_log("URL inválida!");
            }
        }

        error_log("=== VALORES FINAIS PARA SALVAR ===");
        error_log("ieducar_image: " . ($ieducar_image ?? 'NULL'));
        error_log("ieducar_background_image: " . ($ieducar_background_image ?? 'NULL'));
        error_log("ieducar_background_image_url: " . ($ieducar_background_image_url ?? 'NULL'));

        // Preparar dados para atualização
        $campos = [
            'permite_relacionamento_posvendas' => $permiteRelacionamentoPosvendas,
            'bloquear_cadastro_aluno' => $bloquearCadastroAluno,
            'situacoes_especificas_atestados' => $situacoesEspecificasAtestados,
            'url_novo_educacao' => $this->url_novo_educacao,
            'token_novo_educacao' => $this->token_novo_educacao,
            'mostrar_codigo_inep_aluno' => $this->mostrar_codigo_inep_aluno,
            'justificativa_falta_documentacao_obrigatorio' => $this->justificativa_falta_documentacao_obrigatorio,
            'tamanho_min_rede_estadual' => $this->tamanho_min_rede_estadual,
            'modelo_boletim_professor' => $this->modelo_boletim_professor,
            'url_cadastro_usuario' => $this->url_cadastro_usuario,
            'active_on_ieducar' => $this->active_on_ieducar,
            'ieducar_entity_name' => $this->ieducar_entity_name,
            'ieducar_login_footer' => $this->ieducar_login_footer,
            'ieducar_external_footer' => $this->ieducar_external_footer,
            'ieducar_internal_footer' => $this->ieducar_internal_footer,
            'facebook_url' => $this->facebook_url,
            'twitter_url' => $this->twitter_url,
            'linkedin_url' => $this->linkedin_url,
            'ieducar_suspension_message' => $this->ieducar_suspension_message,
            'emitir_ato_autorizativo' => $emitir_ato_autorizativo,
            'emitir_ato_criacao_credenciamento' => $emitir_ato_criacao_credenciamento,
            'mostrar_botoes_ajuda_login' => $mostrarBotoesAjudaLogin,
            'url_diario_professor' => $this->url_diario_professor,
            'url_whatsapp' => $this->url_whatsapp,
            'ieducar_image' => $ieducar_image,
            'ieducar_background_image' => $ieducar_background_image,
            'ieducar_background_image_url' => $ieducar_background_image_url,
        ];

        error_log("Array campos preparado: " . print_r($campos, true));

        $configuracoes = new clsPmieducarConfiguracoesGerais(
            ref_cod_instituicao: $ref_cod_instituicao, 
            campos: $campos
        );

        $editou = $configuracoes->edita();

        error_log("Resultado da edição: " . ($editou ? 'SUCESSO' : 'FALHOU'));

        if ($editou) {
            // Limpar cache
            if (class_exists('Illuminate\Support\Facades\Cache')) {
                \Illuminate\Support\Facades\Cache::forget('configurations');
                \Illuminate\Support\Facades\Cache::flush();
            }
            
            error_log("Cache limpo com sucesso");
            $this->mensagem .= 'Edição efetuada com sucesso.<br>';
            
            // Redirecionar para atualizar
            echo '<script>
                console.log("Salvamento concluído! Redirecionando...");
                window.location.href = "educar_configuracoes_gerais.php";
            </script>';
            exit();
        }

        error_log("=== FIM EDITAR (ERRO) ===");
        $this->mensagem = 'Edição não realizada.<br>';
        return false;
    }

    public function Formular()
    {
        $this->title = 'Configurações gerais';
        $this->processoAp = 999873;
    }
};
