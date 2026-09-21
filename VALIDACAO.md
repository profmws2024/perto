# Verificação e aceite
- Sintaxe JavaScript e referências de assets verificadas.
- Frontend: busca sem acentos, cidade/categoria, estados pendente/oculto, cadastro e aprovação, exclusão, escaping e navegação verificados em ambiente isolado.
- Backend revisado no código; PHP/MySQL não estão disponíveis aqui para testes de integração. Não houve auditoria independente nem validação visual em navegador.
- WebMCP validado com registro simulado, sem navegador compatível disponível.

## Testes necessários na hospedagem
1. Instale o schema novo OU migração da versão anterior, nunca ambos no mesmo banco. Crie administrador pela CLI e confirme que o banco público começa vazio.
2. Envie um comércio como visitante. Confira que fica pending, sem imagem/destaque e não aparece na API pública.
3. Tente enviar status=published e featured=1 pela API pública: deve continuar em análise.
4. Entre no painel, revise, publique e recarregue o navegador. Confirme persistência, busca, cidade e contato.
5. Oculte, publique novamente e exclua com confirmação. Veja o registro no audit_log.
6. Sem autenticação, admin-list, save e delete devem falhar; sem token/origem, todas as escritas devem falhar com 403.
7. Exceda os limites de login/envio e confira 429; não exponha um sistema sem controle antiabuso adicional.
8. Troque a senha e confira a invalidação das outras sessões. Saia e tente acessar novamente o painel.
9. Teste WhatsApp/site/mapa com dados comerciais autorizados. Teste SQL/HTML como texto e confirme que nada é executado.
10. Confira HTTPS, headers, cookies e inacessibilidade de app/bin/SQL/configurações. Teste backup e restauração.
