# Revisão de segurança — 22/09/2026

**Resultado: endurecimento aplicado e testes locais aprovados; ambiente atual não aprovado para exposição à internet.** Esta revisão não certifica invulnerabilidade nem substitui auditoria independente. Não houve teste visual no navegador, teste de carga/DDoS ou avaliação da hospedagem pública.

## Falhas confirmadas e correções

| Constatação | Correção e evidência |
| --- | --- |
| `/perto/.git/config`, `/perto/schema.sql` e `/perto/.env.example` respondiam HTTP 200 | Regra na raiz restringe acesso a `public/`; os três caminhos passaram a responder 403. `app/` e `bin/` também retornam 403. Não há evidência nesta revisão de acesso por terceiros. |
| Configuração sobrescrevia variáveis da hospedagem com modo local e credenciais root | Variáveis externas prevalecem; modo local bloqueia clientes fora do loopback. Produção rejeita conta root, senha de banco vazia e transporte HTTP. Não confiar em cabeçalhos de proxy enviados diretamente pelo cliente. |
| Limite público de cadastros não era chamado | Até 5 tentativas por IP/15 minutos, inclusive inválidas, usando transação e bloqueio de linha. |
| Upload público sem limite de frequência e sem vínculo ao cadastro | Até 5 requisições por IP/15 minutos; 20 para usuários autenticados. Cadastro público aceita somente fotos enviadas pela mesma sessão na última hora. O vínculo é consumido ao cadastrar. |
| Troca de senha permitia tentativas repetidas | Até 5 tentativas por usuário/IP em 15 minutos. Login mantém limite por IP e conta. |
| Armazenamento de fotos sem teto global | Cota padrão de 512 MiB, ajustável com `UPLOAD_STORAGE_LIMIT_MB`, conferida sob bloqueio de arquivo. Limite de 5 arquivos, 5 MiB por arquivo, 8.000 pixels por dimensão e 24 megapixels. Todo o lote é validado antes de gravar. Falha de gravação remove somente arquivos recém-gravados daquele lote. |
| Proteção de uploads dependia apenas de uma lista de extensões proibidas | Apache permite somente nomes aleatórios de 32 caracteres hexadecimais com extensões de imagem; sem execução CGI, sem PATH_INFO, com handler estático, `nosniff` e CSP restritiva. Imagem existente continuou respondendo 200 com os cabeçalhos esperados. |
| Redes sociais aceitavam qualquer domínio HTTPS | Instagram, Facebook, TikTok e YouTube agora verificam domínios correspondentes, sem credenciais embutidas ou porta alternativa. |

Consultas continuam parametrizadas; saída HTML usa escape; sessões usam HttpOnly, SameSite=Strict e renovação no login. Em produção, o cookie exige Secure. Cadastros públicos continuam pendentes, sem destaque, mesmo quando o cliente tenta forçar publicação. Campos opcionais continuam opcionais.

## Verificações reproduzíveis

```powershell
node tests/security.cjs C:/xampp1/php/php.exe
node --test tests/performance.cjs
& C:/xampp1/php/php.exe bin/security-check.php
```

A suíte de integração executa 51 verificações HTTP com PHP/MySQL: CSRF, origem, autenticação/autorização, método HTTP, tamanho de JSON, domínio social falso, travessia de caminho, associação e reutilização de fotos, publicação indevida, SQL como entrada, limites de tentativas, upload legítimo/inválido e invalidação de sessão. Cria um banco aleatório `perto_security_*` e diretório temporário exclusivos e os remove ao final. Não grava cadastros nem imagens no banco/pasta reais. Exige privilégio para criar e remover esse banco de teste; não execute usando a conta restrita de produção.

Os 2 testes de regressão de desempenho também passaram. Sintaxe PHP e JavaScript foi verificada. Os testes do servidor PHP embutido **não** validam `.htaccess`: as respostas 403 e os cabeçalhos dos uploads foram conferidos separadamente no Apache local.

## Pendências obrigatórias antes de publicar

1. Atualizar PHP 8.0.30 para uma versão suportada, preferencialmente 8.4 ou superior com patches atuais. Atualizar também Apache, OpenSSL e banco. A versão 8.0 não recebe suporte oficial: https://www.php.net/supported-versions.php.
2. Servir somente `public/` como DocumentRoot, com HTTPS e `APP_ENV=production`. Definir `APP_ORIGIN` exata, sem caminho ou barra final. Aplicar as regras de upload no servidor real; Nginx ignora `.htaccess` e precisa de regras equivalentes. Não usar o servidor PHP embutido em produção.
3. Criar usuário SQL exclusivo com apenas SELECT, INSERT, UPDATE e DELETE no banco da aplicação e senha exclusiva. Restringir a porta do banco; não expor phpMyAdmin/XAMPP à internet. A conta local permanece root sem senha para não interromper o desenvolvimento.
4. Configurar limites de corpo/tempo/conexões no proxy/servidor, proteção contra tráfego distribuído e alertas de uso de disco. O limite por IP da aplicação não impede botnets ou DDoS. Configurar IP real apenas a partir de proxies confiáveis. O modo local não deve ser usado atrás de proxy público; produção requer que o servidor informe HTTPS corretamente.
5. Manter backups fora da pasta pública e testar restauração. Agendar `php bin/cleanup.php` para expirar contadores/logs. A rotina não remove fotos órfãs: monitorar a cota e revisar arquivos antes de remover qualquer imagem.
6. Restringir o painel com MFA no proxy/provedor de identidade ou VPN. **MFA ainda não está implementado na aplicação.** Trocar a senha inicial do administrador que foi entregue pelo chat e armazená-la em gerenciador de senhas.
7. Configurar PHP com `upload_max_filesize=5M`, `post_max_size=26M`, `max_file_uploads=5`, erros não exibidos, logs privados e permissões mínimas de escrita. Instalar monitoramento e revisão de logs sem registrar senhas/tokens.

## Limitações específicas

- Configuração local foi separada em `app/config.local.php`, ignorada pelo Git e bloqueada via HTTP (403 verificado). Não há credenciais administrativas ou chaves privadas identificadas nos arquivos públicos pela busca realizada. `.gitignore` não apaga versões antigas do histórico; qualquer segredo real que tenha sido publicado anteriormente precisa ser revogado.
- Erros públicos usam mensagens genéricas; backups, arquivos ocultos, configurações e source maps são bloqueados. HTML, CSS, JavaScript e nomes de endpoints continuam necessariamente visíveis e não devem conter segredos.
- O cabeçalho `Server` do Apache local ainda informa versões do servidor. Para reduzi-lo, configurar `ServerTokens Prod` no Apache global e reiniciar o serviço; essa configuração está fora do projeto e não foi alterada. `X-Powered-By` e a assinatura das páginas de erro foram removidos.

- Imagens são verificadas por MIME e dimensões, mas não são reencodificadas nem passam por antivírus. Os originais são mantidos. Reencodificação isolada e varredura são camadas adicionais para hospedagem pública; parsers de imagem devem estar atualizados.
- Fotos em `public/uploads` são públicas para quem conhece o URL, inclusive antes da aprovação do comércio. Não enviar documentos pessoais ou material confidencial. Links externos de imagem mantidos por administradores podem contatar servidores terceiros.
- A CSP ainda permite estilos inline e imagens HTTPS externas por compatibilidade com o layout atual; não é uma política de isolamento absoluto.
- Uma conta administradora comprometida ainda tem acesso completo. Revisão de privilégios, MFA, monitoramento e resposta a incidentes continuam necessários.
- O verificador de configuração é uma triagem: não valida certificados, firewall, permissões efetivas SQL ou regras da hospedagem. A implantação final precisa ser testada no domínio real.

Referências: [OWASP — Upload de arquivos](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html), [OWASP — Autorização](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html), [PHP — Versões suportadas](https://www.php.net/supported-versions.php).
