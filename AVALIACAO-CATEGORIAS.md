# Cobertura do guia local

A avaliação considera os campos, filtros e regras presentes no projeto. Não é um levantamento estatístico dos estabelecimentos de uma cidade.

## Atualização: categorias principais

Por preferência de navegação, o catálogo foi simplificado para 12 grupos principais. As atividades especializadas ficam nos grupos abrangentes, especialmente Serviços. A página inicial apresenta os grupos em um carrossel horizontal com setas e deslize por toque. O levantamento abaixo registra a avaliação anterior de 36 opções, não o catálogo atual.

## Resultado da avaliação anterior

As oito categorias anteriores eram muito amplas. O catálogo agora possui 36 categorias, mantendo os nomes anteriores para preservar cadastros e links. As novas opções incluem comércio varejista, alimentação, saúde, profissionais liberais, serviços domésticos, indústria, atividades rurais e serviços especializados. Isso oferece uma cobertura ampla das atividades comuns de uma cidade, sem prometer uma classificação específica para toda profissão possível.

O catálogo central está em `app/categories.json`, com exemplos de atividades por categoria. O servidor usa esse arquivo na validação e envia as mesmas opções ao site. Categorias são opções de classificação: nenhum comércio ou profissional foi pré-cadastrado.

## Exemplos de enquadramento

| Atividade | Categoria |
| --- | --- |
| Médico, dentista, psicólogo, nutricionista | Saúde |
| Advogado | Advocacia e serviços jurídicos |
| Contador e consultor empresarial | Contabilidade e consultoria |
| Arquiteto e engenheiro | Arquitetura e engenharia |
| Professor particular | Educação e aulas particulares |
| Personal trainer | Esportes e atividades físicas |
| Eletricista, pintor e encanador | Reformas e instalações |
| Diarista e lavanderia | Limpeza e serviços domésticos |
| Veterinário e pet shop | Pets e veterinária |
| Fotógrafo e designer | Comunicação e marketing |
| Costureira | Costura e serviços têxteis |
| Cuidador de idosos e babá | Cuidados e assistência pessoal |
| Supermercado e açougue | Mercados e conveniências |
| Farmácia e ótica | Farmácias e produtos de saúde |
| Atividade sem categoria específica | Serviços |

## Limitações que ainda existem

- O portal permite apenas as cinco cidades configuradas do Alto Tietê. As categorias servem para uma cidade comum, mas a cobertura geográfica continua regional.
- Cada perfil escolhe uma categoria. Negócios com várias atividades precisam selecionar a principal e informar as demais na descrição.
- Endereço completo e bairro são obrigatórios e públicos. Ainda falta uma opção própria para atendimento online, em domicílio ou por região, sem publicar endereço residencial.
- A busca consulta nome, resumo, descrição, categoria e bairro. Não há sinônimos automáticos: informar a especialidade na descrição ajuda a encontrar o profissional.
- Não há campos próprios para especialidades, registro profissional ou modalidades de atendimento. A descrição permite informar esses detalhes, mas o portal não verifica habilitação profissional.

Portanto, o catálogo ampliado atende muito melhor à diversidade local. O cadastro ainda precisa evoluir nas modalidades e áreas de atendimento para acomodar plenamente profissionais sem estabelecimento físico.
