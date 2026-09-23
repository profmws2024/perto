# Perto — divulgação de comércios locais

Portal em HTML, CSS, JavaScript, PHP e MySQL, voltado ao Alto Tietê.

## Recursos
- Busca por nome, serviço e bairro; filtros por cidade e categoria; ordenação por destaque ou nome.
- Página de cada comércio: descrição, endereço, horários, WhatsApp, site e acesso ao mapa.
- Formulário público para solicitar divulgação; todos os cadastros entram em análise. O visitante não pode se aprovar nem selecionar destaque.
- Painel com login, indicadores reais dos cadastros, fila de análise, criação/edição/exclusão, ocultação, publicação e destaques.
- O painel usa administradores com acesso completo. Não há contas individuais para lojistas, cobranças, avaliações ou envio de e-mails nesta versão. Cadastros aceitam links HTTPS de Instagram, Facebook, TikTok, YouTube e até três fotos escolhidas do computador.
- Categorias e cidades são fixas e podem ser alteradas em app.js e bootstrap.php. As imagens são escolhidas entre quatro fotos ilustrativas locais. Use fotos autorizadas antes da operação real.
- A prévia Sites usa negócios fictícios e alterações temporárias em memória. O pacote PHP usa MySQL real; o banco começa vazio, sem contas ou contatos fictícios.

## Requisitos
PHP 8.2+ (pdo_mysql, mbstring), MySQL 8.0+ ou MariaDB 10.6+, Apache/Nginx e HTTPS.

## Instalação nova
1. Crie um banco UTF-8 e importe `schema.sql` com uma conta de migração.
2. Crie uma conta de aplicação exclusiva, com apenas SELECT, INSERT, UPDATE e DELETE no banco. Não use root.
3. Configure a raiz pública para a pasta `public`. `app`, `bin`, migrations, schema e configurações devem ficar fora do acesso pela internet.
4. Configure no processo PHP as variáveis descritas em `.env.example`. O arquivo é apenas referência, não é carregado automaticamente. APP_ORIGIN é a origem exata do domínio, com HTTPS e sem barra final nem caminho. APP_ENV=production exige HTTPS; o servidor deve redirecionar HTTP para HTTPS.
5. Execute `php bin/create-admin.php` no terminal do servidor e informe nome, e-mail e senha exclusiva de 12+ caracteres. Não existe senha padrão nem instalador público.
6. Abra “Área administrativa”, entre e cadastre o primeiro comércio. Confirme autorização, telefone, endereço e horários antes de publicar.
7. Configure uma tarefa diária com `php bin/cleanup.php`, usando as variáveis de banco. Remove contadores com mais de um dia e logs com mais de 90 dias. Ajuste a retenção à política do portal.

### Se você já instalou a versão anterior de notícias
Faça backup do banco. Execute **somente** `migrations/002_comercios.sql` uma vez, usando a conta de migração. Não reimporte schema.sql no banco existente. A migração preserva usuários, notícias e logs antigos; cria a tabela businesses e adiciona business_id ao log. O novo código deixa de consultar notícias. Atualize o conteúdo de public e app juntos. Cadastre os comércios no painel: notícias não são convertidas automaticamente em estabelecimentos. Em caso de falha na atualização, restaure o backup e o código anterior.

Se `businesses` já existe, não execute novamente a migração 002: faça backup e execute apenas `migrations/003_redes_fotos.sql` para adicionar os links sociais e as fotos.

### cPanel / hospedagem compartilhada
Se não puder alterar DocumentRoot, copie o conteúdo de `public` para public_html (ou a subpasta do domínio). Coloque `app` fora de public_html e ajuste os require de index.php e api.php para o caminho privado. Configure variáveis pelo mecanismo recomendado pelo provedor e crie o administrador via Terminal/SSH ou processo privado do suporte. Não suba ZIP, SQL ou credenciais para a pasta pública.

### Teste local
As configurações desta instalação ficam em `app/config.local.php`, um arquivo privado ignorado pelo Git e carregado apenas em modo local. Não inclua esse arquivo em commits, ZIPs de distribuição ou na hospedagem. Variáveis de ambiente têm prioridade. Uma instalação obtida pelo Git deve configurar as variáveis abaixo; o arquivo local não acompanha o repositório.

Defina APP_ENV=local e APP_ORIGIN=http://localhost:8080, além das variáveis do banco. Inicie `php -S localhost:8080 -t public`. O modo local permite HTTP; não use em produção.

## Segurança implementada
- PDO com consultas parametrizadas e emulação desativada; validação de campos no servidor.
- Autorização em todos os recursos administrativos. Cadastros pendentes e ocultos não saem na API pública.
- CSRF, verificação exata de origem, métodos e tipo de conteúdo; limite de tamanho de requisição.
- Cookies HttpOnly, Secure e SameSite=Strict; ID de sessão renovado; 30 minutos de inatividade e máximo de 8 horas.
- password_hash/password_verify; troca de senha invalida outras sessões.
- Até 10 tentativas de login por IP/conta por janela de 15 minutos; até 5 envios públicos por IP por janela de 15 minutos, com contagem transacional.
- O servidor força status pending, sem destaque e sem imagem de capa em envios públicos, independentemente dos campos enviados; redes sociais passam por validação HTTPS e fotos passam por validação de MIME, tamanho e quantidade.
- Textos escapados e sem HTML arbitrário; WhatsApp validado; URLs de site somente HTTPS; imagem restrita à coleção local.
- CSP, HSTS, nosniff, bloqueio de iframe e política de referência.
- Registro de login, alterações, exclusões e troca de senha, sem armazenar senhas nos logs.

Essas medidas não substituem auditoria, atualizações, proteção antiabuso no servidor, backup e testes. Atrás de proxy, configure REMOTE_ADDR corretamente a partir apenas de proxies confiáveis. O PHP não confia indiscriminadamente em X-Forwarded-For. Adote rate limiting adicional no servidor para páginas autenticadas e troca de senha.

## Limites e publicação
- O backend passou por testes locais de integração e segurança em banco isolado. Consulte SEGURANCA.md e VALIDACAO.md antes de publicar: o XAMPP local ainda não está aprovado para exposição à internet.
- O catálogo é carregado em conjunto: acervos grandes exigem paginação no servidor. As URLs usam caminhos como /explorar e /admin; SEO avançado requer renderização por estabelecimento e sitemap.
- Complete os dados do responsável e o canal para correção/exclusão na política de privacidade. Revise autorização e direitos de fotografias e contatos.
- Todos os comércios, endereços e descrições da demonstração são fictícios. Os botões de WhatsApp desses exemplos ficam indisponíveis; nenhum telefone foi inventado.
- O formulário público não envia e-mails de confirmação. O administrador acompanha a fila no painel.

## Fotografias ilustrativas
Créditos e links em public/credits.json: Skyler Smith, Christopher Bill, František Čaník e Brandon Atchison, via Unsplash.

### Endereços de navegação
No XAMPP, acesse `http://localhost/perto/public/admin`. Com o domínio apontando para `public`, use `https://seudominio.com.br/admin`. A navegação usa History API, e o Apache precisa de mod_rewrite e das regras de `public/.htaccess` para abrir ou atualizar links diretamente. Hospedagens Nginx precisam de regras equivalentes para as rotas da aplicação. Links antigos com `#/` são convertidos automaticamente ao abrir.
