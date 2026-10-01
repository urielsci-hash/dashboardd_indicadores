# Painel de Qualidade — Sinergia Agro (v3)

Painel de gestão à vista digital para a fábrica de Serra Negra/SP, hoje em ambiente de teste em
**www.vinceassessorios.com.br/dashboard**, exibido numa Smart TV e alimentado por Qualidade, RH,
Marketing e TI — cada um enxergando só o que pode editar.

## Permissões por departamento

- **Qualidade**: Status da qualidade (pirâmide + desvios), Indicadores, Em formulação.
- **RH / Marketing**: Mural (imagens ou texto).
- **TI**: Usuários (criar, editar, redefinir senha, desativar, excluir) e o Log de auditoria. Não acessa nenhum conteúdo/arquivo das outras áreas.
- **Visualização**: só vê a tela pública — use para a Smart TV e para acesso remoto da diretoria. Sem acesso a nenhuma página de admin.

Qualquer usuário logado também pode abrir a tela pública normalmente, como pré-visualização.
Todo usuário cadastrado pelo TI na tela de Usuários recebe uma senha inicial e é obrigado a trocá-la no primeiro acesso. O primeiro usuário de TI (criado direto no banco) não é obrigado a trocar — ele pode mudar a própria senha quando quiser, em "Trocar senha".

## O que mudou nesta versão (v3)

1. **Banco de dados de teste já configurado** em `config/config.php` (host, banco, usuário, senha
   e `SITE_URL` apontando para o domínio de testes). Troque para os dados definitivos quando for
   para produção.
2. **Redirecionamento pós-login**: quem tem painel vai para `/admin`; só quem é do departamento
   Visualização vai direto para `/index`.
3. **TI**: log de auditoria com limpeza automática (30 dias); usuários agora têm Editar e Excluir
   (excluir é bloqueado com um aviso se a pessoa já tiver lançamentos no sistema — nesse caso,
   desative em vez de excluir, para preservar o histórico); todo usuário novo precisa trocar a
   senha inicial no primeiro acesso (`trocar-senha.php`).
4. **Qualidade**:
   - Seletor de Ano gerado dinamicamente (de 5 anos atrás até pelo menos 2050).
   - O histórico de observações do Status da Qualidade agora é preservado: cada lançamento vira
     uma linha nova no banco (nunca sobrescreve o anterior), e a página mostra o "Histórico do
     mês" completo, com quem lançou e quando.
   - Desvios agora têm Editar e Excluir.
   - **Indicadores foi reconstruído**: cada indicador agora é um registro independente (nome,
     tipo de gráfico, categorias, valores, período de exibição, ativo/inativo), com Criar, Editar,
     Excluir e Ativar/Desativar. O upload de planilha `.xlsx` continua funcionando, só que agora
     cada aba cria ou atualiza um indicador (casado pelo nome) em vez de substituir tudo de uma
     vez. Vários indicadores ficam ativos ao mesmo tempo e alternam entre si (mesma lógica do
     Mural, com período "exibir de/até" próprio por indicador).
   - **Produção diária virou "Em formulação"**, com os campos "Total"/"Meta" substituídos por
     **Armazenamento utilizado** (calculado automaticamente como a soma dos 4 tanques — ninguém
     digita esse número) e **Capacidade total de armazenamento** (informada manualmente). Tem
     histórico dos lançamentos, com Editar e Excluir, podendo lançar ou corrigir qualquer dia.
5. **Marketing/RH (Mural)**: Editar e Excluir nos comunicados (texto e/ou imagem); o upload de
   imagem agora valida corretamente cada etapa (código de erro do PHP, permissão da pasta,
   sucesso do `move_uploaded_file`) e mostra uma mensagem clara quando algo falha, em vez de
   salvar uma referência quebrada.
6. **Listas operacionais** (Mural, Indicadores) mostram por padrão só o que está **em exibição**
   agora (dentro do período configurado); há um link "mostrar todos" para gerenciar itens
   inativos/expirados quando precisar. Essa regra não vale para o Log de auditoria, que segue a
   retenção fixa de 30 dias.
7. **Clima**: chave da API já embutida em `config/config.php`.
8. **Atualização do dashboard**: os campos já alternavam a cada 30 segundos e o painel já buscava
   dados novos do banco a cada 5 minutos (`setInterval(carregarDados, 5*60*1000)` em
   `assets/js/dashboard.js`) — esse comportamento foi mantido e confirmado.
9. **URLs amigáveis**: todo o sistema roda sem `.php` na URL (ex.: `/dashboard/admin/status`).
   URLs antigas com `.php` são redirecionadas (301) para a versão limpa. As regras ficam no
   `.htaccess` da raiz do dashboard.

> **Nesta etapa, o layout visual do dashboard público não foi redesenhado** (só os dois pontos que
> precisavam refletir lá também: o nome "Em formulação" e os campos de armazenamento). A
> arquitetura de dados já está pronta para a próxima etapa, quando o dashboard for atualizado
> visualmente — tudo lê do mesmo banco, sem dados duplicados.

## Etapa 2 (dashboard público) — o que mudou

- **Tipografia e componentes responsivos** com `clamp()` em todo o `.painel` (títulos, calendário,
  valores, cards) — cresce em telas grandes e encolhe em telas pequenas, sem quebrar layout.
  Escopado só no dashboard público, sem alterar o visual do painel administrativo.
- **Calendário maior** e a **bolinha "Sem problema" corrigida para verde** (era um bug: a classe
  `bolinha-ok` existia no HTML mas nunca tinha sido definida no CSS, então caía no cinza padrão).
  A cor "Resolvido" também ficou mais claramente laranja (era um amarelo mais claro antes).
- **Desvios de qualidade**: agora aparecem com os campos rotulados (Data, Desvio, Ação tomada,
  Como evitar reincidência?, Observação — a observação vem do lançamento do Status daquele mesmo
  dia) e alternam um de cada vez, sempre em ordem cronológica, nunca pulando nenhum.
- **Últimas notícias (RSS)**: nova seção dentro do card de Status da Qualidade (mesmo lugar da
  referência visual), abaixo dos desvios. RH/Marketing cadastram a URL do feed em
  `/admin/noticias`; o dashboard busca, decodifica e alterna as notícias automaticamente, com
  link clicável para a matéria original.
- **Mural — correção definitiva do "erro ao carregar imagem"**: o endpoint agora confere se o
  arquivo da imagem realmente existe no servidor antes de mandar a referência para o dashboard;
  se não existir (registro antigo quebrado, por exemplo), mostra o texto/título no lugar da
  imagem quebrada, em vez de um ícone de erro. Também adicionei um `onerror` no `<img>` como
  segunda camada de proteção.
- **Indicadores**: com 3 ou menos indicadores ativos, cada um ocupa seu próprio espaço sem repetir
  e sem rotação (os espaços que sobrarem ficam vazios); com 4 ou mais, entra a rotação de fato,
  sempre passando por todos antes de repetir qualquer um.
- **Cada componente atualiza no seu próprio ritmo** (arquitetura de API dividida por componente,
  substituindo o `api/dados.php` único da v3): Mural a cada 30s (`api/mural.php`), Indicadores a
  cada 20s (`api/indicadores.php`), Status da Qualidade a cada 35s (`api/status.php`), Notícias a
  cada 25s (`api/noticias.php`). "Em formulação" carrega junto com a página (não tem lançamento
  tão frequente a ponto de precisar de um intervalo próprio). Além disso, a página inteira recarrega
  a cada 5 minutos, garantindo que qualquer novidade cadastrada em qualquer painel apareça mesmo
  que fuja do que os fetches acima cobrem — e isso também evita qualquer acúmulo de memória numa
  TV que fique com a aba aberta por dias.

### Novo módulo: Notícias (RSS)

- Tabela `noticias_rss` (URL do feed, ativo, departamento, usuário).
- `admin/noticias.php` — RH/Marketing cadastram, editam, ativam/desativam e excluem feeds.
- `includes/leitor_rss.php` — leitor nativo de RSS/Atom (via SimpleXML + cURL, sem biblioteca
  externa), com cache de 15 minutos em disco por feed.
- `api/noticias.php` — combina os feeds ativos, ordena por data e devolve os itens mais recentes.


## Passo a passo para colocar no ar (Locaweb)

1. **Banco de dados**: como ainda é ambiente de teste, a forma mais segura é apagar as tabelas
   antigas (se já tiver importado uma versão anterior) e importar `sql/schema.sql` inteiro — o
   comentário no topo do arquivo traz o `DROP TABLE` pronto.
2. **Envie os arquivos** via FTP para dentro de `www.vinceassessorios.com.br/dashboard`.
3. **Crie o primeiro usuário (TI)**: acesse `/gerar-senha`, gere o hash, insira o usuário pelo
   phpMyAdmin usando o INSERT comentado no fim do `sql/schema.sql` (departamento TI), depois
   **apague o `gerar-senha.php` do servidor**. Esse usuário entra direto no `/admin`, sem troca de senha obrigatória.
   Ele cria os demais em `/admin/usuarios` — incluindo o
   usuário do departamento "Visualização" para a TV/diretoria.

## Configurando a Smart TV

1. Abra o navegador da TV em `https://www.vinceassessorios.com.br/dashboard`.
2. Faça login com o usuário do departamento Visualização e marque "Manter conectado" — a sessão
   fica salva por 1 ano (ajustável em `SESSAO_VISUALIZACAO_DIAS` no `config.php`).
3. Deixe o navegador em tela cheia.

## Segurança

- Senhas com hash (`password_hash`), prepared statements em todas as consultas.
- `config/` e `uploads/*/` bloqueadas por `.htaccess`.
- Toda página exige login; cada página de admin exige o departamento certo.
- Exclusão de usuário é bloqueada quando há histórico associado (preserva rastreabilidade).
- Log de auditoria guarda só uma frase curta por ação — nunca o conteúdo alterado.

## Pontos de atenção para a segunda etapa

- **Migração de dados**: como a estrutura de indicadores e do histórico de status mudou bastante
  entre v2 e v3, o caminho recomendado (dado que ainda estamos em teste) foi reimportar o schema
  do zero, em vez de escrever scripts de migração automática não testados. Se já existirem dados
  de teste que precisem ser preservados, me avise para eu preparar os `ALTER TABLE`/migração
  específicos.
- **Redesenho visual do dashboard público**: ainda não fizemos os ajustes visuais combinados para
  a segunda etapa (o layout dos indicadores, mural e "em formulação" no telão continuam com o
  visual da v2, só os textos/campos citados acima já foram atualizados).
- Este ambiente ainda não foi testado num servidor PHP/MySQL real (não há como executar PHP neste
  ambiente de desenvolvimento) — a recomendação é validar o passo a passo de instalação e os
  fluxos abaixo assim que subir para a Locaweb.

## Solução de problemas

**Erro 404 ao abrir `/dashboard/login` (ou qualquer página sem `.php`)**
- Confira se o arquivo `.htaccess` da pasta `dashboard/` foi realmente enviado ao servidor. Arquivos
  que começam com ponto ficam escondidos: no cPanel/Gerenciador de Arquivos, ative
  "Mostrar arquivos ocultos (dotfiles)"; no FTP, habilite a exibição de arquivos ocultos.
- O `.htaccess` da v4 corrige um erro da versão anterior, em que a regra de reescrita ficava
  `RewriteRule ^(.*)$ .php [L]` (faltava o `$1`) e devolvia 404 para toda URL sem extensão.
  A regra correta é `RewriteRule ^(.*)$ $1.php [L]`.
- Se, com o `.htaccess` correto no lugar, o 404 continuar, o servidor pode estar com o
  `mod_rewrite` desativado ou com `AllowOverride None` — peça ao suporte da hospedagem para
  habilitar `mod_rewrite` e `AllowOverride All` nessa pasta.

**Erro `ERR_TOO_MANY_REDIRECTS` em `/trocar-senha`**
- Era um bug da v4: a trava de troca de senha comparava o nome do arquivo como `trocar_senha`
  (underscore), mas o arquivo é `trocar-senha.php` (hífen), então a própria tela de troca não era
  reconhecida como exceção e redirecionava para si mesma. Corrigido em `includes/auth.php`.
- Se o usuário de TI já existia com a troca obrigatória ligada, rode no phpMyAdmin:
  `UPDATE usuarios SET deve_trocar_senha = 0 WHERE email = "seu-email";` e depois saia e entre de
  novo (a sessão guarda esse valor). Se a página ficar presa no erro, abra `/dashboard/logout`.
