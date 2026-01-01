# i-Educar

# i-Educar – Módulo Intranet

_“Expandindo o i-Educar com um painel moderno, filtrado e inteligente.”_

Este módulo adiciona ao i-Educar um **dashboard interno avançado**, com dados filtrados automaticamente pela escola do usuário logado, fornecendo informações mais precisas, seguras e relevantes para cada unidade escolar.

---
## Sobre o arquivo do dashboard

O **O arquivo educar_index.php modificado** foi criado para oferecer um painel administrativo mais direto e contextualizado, exibindo informações baseadas **na escola associada ao usuário**.

Ele aprimora a experiência de diretores, coordenadores e servidores, permitindo acesso rápido a dados importantes como:

- alunos matriculados  
- turmas ativas  
- documentos pendentes  
- matrículas AEE  
- inconsistências de CPF  
- indicadores gerais por escola  

O módulo mantém **total compatibilidade** com o i-Educar original.

---

## Estrutura de Arquivos

A estrutura do módulo é simples, limpa e fácil de manter:

intranet/
├── educar_index.php          # Novo Painel de Controle (Dashboard)
├── verificar-cpf-aluno.php   # Endpoint de API para validação de CPF
└── styles/
    └── educar_index.css      # Estilos CSS para o Dashboard

## Principais Funcionalidades

| Funcionalidade                                   | Descrição |
|--------------------------------------------------|-----------|
| **Identificação do usuário logado**              | Usa Auth/Session para determinar a escola do usuário |
| **Filtro automático por escola**                 | Busca na tabela `escola_usuario` |
| **Exibição do nome da escola**                   | Mostrado no título do painel e nos cards |
| **Consultas filtradas por unidade escolar**      | Alunos, turmas, documentos, AEE |
| **Fallback seguro**                              | Se não houver escola vinculada → exibe dados gerais |
| **Totalmente compatível com administradores**    | Usuários sem vínculo continuam vendo dados globais |
| **CSS dedicado**                                 | Interface moderna e responsiva |

---

## Como Funciona

1. O módulo identifica o **usuário logado**  
2. Consulta a tabela:
3. Determina a escola vinculada ao usuário  
4. Aplica automaticamente filtros a todas as consultas do dashboard  
5. Exibe dados restritos à unidade escolar  
6. Caso não haja escola vinculada → usa consultas gerais  

Esse fluxo garante:

- Privacidade  
- Coerência das informações  
- Redução de erros  
- Relevância dos dados exibidos  

---

## Instalação

Copie a pasta `intranet` para dentro do diretório principal do i-Educar:

Permissões Necessárias

Para evitar erros de leitura/execução:

sudo chown -R www-data:www-data /var/www/ieducar/ieducar/intranet
sudo chmod -R 755 /var/www/ieducar/ieducar/intranet
sudo chmod 644 /var/www/ieducar/ieducar/intranet/*.php
sudo chmod 644 /var/www/ieducar/ieducar/intranet/styles/*.css


Manutenção e Expansão

O módulo foi projetado com arquitetura limpa e pode ser facilmente expandido:

novos indicadores por escola
gráficos estatísticos
widgets adicionais
APIs internas
integração com módulos oficiais

# OBS

O card que mostra o quantitativo das matrículas AEE  vai ser ajustado em produção no ambiente de desenvolvimento não da para verificar a busca correta e trazer a informação da unidade escolar corretamente.