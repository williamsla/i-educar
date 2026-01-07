<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('favicon.ico') }}">
    <title>@if(isset($title)) {!! html_entity_decode($title) !!} - @endif {{ html_entity_decode(config('legacy.app.entity.name')) }} - i-Educar</title>

    <!-- Fontes e Ícones -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Open+Sans:wght@400;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Separado com caminho absoluto -->
    <link rel="stylesheet" href="/css/login_ieducar.css?v=<?php echo time(); ?>">
    
    @php
        // VALORES PADRÃO
        $defaultLogoUrl = 'https://static.wixstatic.com/media/3c2742_20c475c9572c41fd87dfe788357bf5d3~mv2.png/v1/fill/w_600,h_802,al_c,q_90,usm_0.66_1.00_0.01/verticaleducacao2_4x.webp';
        $defaultBackgroundUrl = 'https://images.unsplash.com/photo-1588072432836-e10032774350?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80';
        $defaultDiarioProfessor = 'https://diario.delmirogouveia.al.gov.br/usuarios/logar';
        $defaultWhatsapp = 'https://wa.me/5582981670619';
        
        // INICIALIZAR VARIÁVEIS
        $logoUrl = $defaultLogoUrl;
        $backgroundImageUrl = $defaultBackgroundUrl;
        $diarioProfessorUrl = $defaultDiarioProfessor;
        $whatsappUrl = $defaultWhatsapp;
        $mostrarBotoesAjuda = true;
        
        // FUNÇÃO SIMPLES PARA OBTER URL
        function getSimpleImageUrl($path) {
            if (!$path) return null;
            
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $path;
            }
            
            // Tentar storage público
            $cleanPath = preg_replace('/^public\//', '', $path);
            
            // Verificar se existe no storage
            if (Storage::disk('public')->exists($cleanPath)) {
                return Storage::disk('public')->url($cleanPath);
            }
            
            // Tentar caminho físico
            $publicPath = 'storage/' . $cleanPath;
            if (file_exists(public_path($publicPath))) {
                return asset($publicPath);
            }
            
            return null;
        }
        
        // TENTAR OBTER CONFIGURAÇÕES COM LOGS DE DEBUG
        try {
            // Tentar via DB direto (mais confiável)
            $configuracoes = DB::table('pmieducar.configuracoes_gerais')
                ->where('ref_cod_instituicao', 1)
                ->first();
            
            // DEBUG: Log para verificar o que foi lido
            \Log::info('=== CONFIGURAÇÕES LIDAS DO BANCO (PUBLIC.BLADE.PHP) ===', [
                'configuracoes_encontradas' => $configuracoes ? 'SIM' : 'NÃO',
                'url_diario_professor' => $configuracoes->url_diario_professor ?? 'NULL',
                'url_whatsapp' => $configuracoes->url_whatsapp ?? 'NULL',
                'mostrar_botoes_ajuda_login' => $configuracoes->mostrar_botoes_ajuda_login ?? 'NULL',
            ]);
            
            if ($configuracoes) {
                // LOGO
                if (!empty($configuracoes->ieducar_image)) {
                    $tempLogoUrl = getSimpleImageUrl($configuracoes->ieducar_image);
                    if ($tempLogoUrl) {
                        $logoUrl = $tempLogoUrl;
                    }
                }
                
                // BACKGROUND
                if (!empty($configuracoes->ieducar_background_image_url)) {
                    $backgroundImageUrl = $configuracoes->ieducar_background_image_url;
                } elseif (!empty($configuracoes->ieducar_background_image)) {
                    $bgUrl = getSimpleImageUrl($configuracoes->ieducar_background_image);
                    if ($bgUrl) {
                        $backgroundImageUrl = $bgUrl;
                    }
                }
                
                // URLS COM TRIM E VALIDAÇÃO
                if (!empty($configuracoes->url_diario_professor)) {
                    $urlTemp = trim($configuracoes->url_diario_professor);
                    if (!empty($urlTemp) && filter_var($urlTemp, FILTER_VALIDATE_URL)) {
                        $diarioProfessorUrl = $urlTemp;
                        \Log::info('✅ URL Diário Professor configurada: ' . $diarioProfessorUrl);
                    } else {
                        \Log::warning('⚠️ URL Diário Professor inválida: ' . $urlTemp);
                    }
                }
                
                if (!empty($configuracoes->url_whatsapp)) {
                    $urlTemp = trim($configuracoes->url_whatsapp);
                    if (!empty($urlTemp)) {
                        $whatsappUrl = $urlTemp;
                        \Log::info('✅ URL WhatsApp configurada: ' . $whatsappUrl);
                    }
                }
                
                // BOTÕES DE AJUDA
                if (isset($configuracoes->mostrar_botoes_ajuda_login)) {
                    $mostrarBotoesAjuda = (bool)$configuracoes->mostrar_botoes_ajuda_login;
                    \Log::info('✅ Mostrar botões de ajuda: ' . ($mostrarBotoesAjuda ? 'SIM' : 'NÃO'));
                }
            } else {
                \Log::warning('⚠️ Nenhuma configuração encontrada no banco de dados!');
            }
        } catch (\Exception $e) {
            // Em caso de erro, registrar e usar valores padrão
            \Log::error('❌ Erro ao carregar configurações do banco: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
        }
        
        // DEBUG FINAL
        \Log::info('=== VALORES FINAIS QUE SERÃO USADOS ===', [
            'logoUrl' => $logoUrl,
            'backgroundImageUrl' => $backgroundImageUrl,
            'diarioProfessorUrl' => $diarioProfessorUrl,
            'whatsappUrl' => $whatsappUrl,
            'mostrarBotoesAjuda' => $mostrarBotoesAjuda,
        ]);
    @endphp
    
    <!-- Estilo inline para a imagem de fundo configurável -->
    <style>
        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('{{ $backgroundImageUrl }}') center/cover no-repeat;
            opacity: 0.15;
            z-index: 1;
        }
        
        @media (max-width: 768px) {
            .welcome-section::before {
                opacity: 0.1;
            }
        }
    </style>
</head>

<body>

<!-- CONTAINER PRINCIPAL -->
<div class="login-wrapper">
    <!-- SEÇÃO DE BOAS-VINDAS -->
    <div class="welcome-section">
        <div class="welcome-content">
            <!-- Logo da Instituição -->
            <div class="logo-left-container">
                <img src="{{ $logoUrl }}" 
                     alt="Logo da Instituição" 
                     class="institution-logo-left"
                     onerror="this.src='{{ $defaultLogoUrl }}'">
            </div>
            
            <h1 class="welcome-title">Bem-vindo ao Portal Educacional</h1>
            <p class="welcome-subtitle">Acesso exclusivo para professores, alunos e gestores da instituição</p>
            
            <ul class="features-list">
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span>Gestão acadêmica integrada</span>
                </li>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span>Acompanhamento pedagógico</span>
                </li>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span>Comunicação escolar</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- SEÇÃO DE LOGIN -->
    <div class="login-section">
        <!-- HEADER COM TUDO CENTRALIZADO -->
        <div class="login-header">
            <!-- Logo centralizada -->
            <div class="logo-center-container">
                <img src="{{ $logoUrl }}" 
                     alt="Logo" 
                     class="login-logo-center"
                     onerror="this.src='{{ $defaultLogoUrl }}'">
            </div>
            
            <!-- Título e subtítulo centralizados -->
            <h1 class="login-title">Acessar Sistema</h1>
            <p class="login-subtitle">Informe suas credenciais para continuar</p>
        </div>

        <!-- CONTAINER PARA FORMULÁRIO CENTRALIZADO -->
        <div class="login-form-container">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if($errors->count())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif

            <!-- FORMULÁRIO CENTRALIZADO -->
            <form class="login-form" method="POST" action="{{ route('login') }}">
                @csrf
                
                <div class="form-group">
                    <label for="login" class="form-label">Matrícula ou CPF</label>
                    <input type="text" id="login" name="login" class="form-control" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Senha</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                    <i class="fas fa-eye input-icon toggle-password"></i>
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <!-- <input type="checkbox" name="remember"> Lembrar-me -->
                    </label>
                    <a href="#" class="forgot-password">Esqueci minha senha</a>
                </div>
                
                <button type="submit" class="btn btn-primary" id="form-login-submit">
                    Entrar no Sistema
                </button>
            </form>

            <!-- LINKS DE SUPORTE -->
            @if($mostrarBotoesAjuda)
            <div class="support-section">
                <h3 class="support-title">Precisa de Ajuda?</h3>
                <div class="support-links">
                    <a href="{{ $whatsappUrl }}" class="support-link whatsapp" target="_blank">
                        <i class="fab fa-whatsapp"></i>
                        Suporte via WhatsApp
                    </a>
                    <a href="{{ $diarioProfessorUrl }}" class="support-link diary" target="_blank">
                        <i class="fas fa-book-open"></i>
                        Diário do Professor
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Log para debug no console do navegador
        console.log('=== DEBUG URLS ===');
        console.log('URL Diário Professor:', '{{ $diarioProfessorUrl }}');
        console.log('URL WhatsApp:', '{{ $whatsappUrl }}');
        console.log('Mostrar Botões:', {{ $mostrarBotoesAjuda ? 'true' : 'false' }});
        
        // Mostrar/ocultar senha
        const togglePassword = document.querySelector('.toggle-password');
        const passwordInput = document.getElementById('password');
        
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
        
        // Adicionar loading ao botão de login
        const loginButton = document.getElementById('form-login-submit');
        const loginForm = document.querySelector('.login-form');
        
        if (loginForm && loginButton) {
            loginForm.addEventListener('submit', function() {
                loginButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';
                loginButton.disabled = true;
            });
        }
    });
</script>

</body>
</html>