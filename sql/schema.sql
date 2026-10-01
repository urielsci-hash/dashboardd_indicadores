-- Banco de dados do Painel de Qualidade - Sinergia Agro (v3)
-- Importe este arquivo pelo phpMyAdmin, dentro do banco ja criado (uriel_sinergia).
--
-- IMPORTANTE: como ainda estamos em ambiente de testes, a forma mais segura de aplicar esta
-- versao e apagar as tabelas antigas (se ja existirem) e importar este arquivo inteiro do zero,
-- ja que a estrutura de indicadores e do historico de status mudou bastante desde a v2:
--
-- DROP TABLE IF EXISTS log_auditoria, indicadores_dados, indicadores_arquivos, indicadores,
--   mural_posts, desvios_qualidade, status_qualidade_dia, producao_tanques, producao_diaria,
--   tokens_lembrar, usuarios, departamentos, configuracoes;

CREATE TABLE departamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) NOT NULL UNIQUE
);

-- "Visualizacao" e o departamento de quem so pode ver a tela publica (TV / diretoria remota),
-- sem nenhum acesso as paginas de administracao.
INSERT INTO departamentos (nome) VALUES ("Qualidade"), ("RH"), ("Marketing"), ("TI"), ("Visualizacao");

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  departamento_id INT NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  -- 0 por padrao: quem e inserido direto no banco (o 1o usuario de TI) NAO e obrigado a trocar a senha.
  -- A tela de Usuarios grava 1 explicitamente para todo usuario que o TI cadastra.
  deve_trocar_senha TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (departamento_id) REFERENCES departamentos(id)
);

CREATE TABLE tokens_lembrar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expira_em DATETIME NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Status diario da qualidade (a "piramide"). SEM chave unica em "data" de proposito: cada
-- lancamento gera uma NOVA linha, preservando o historico de observacoes por dia (nunca
-- sobrescrevemos silenciosamente). O status "atual" de um dia e sempre o registro mais
-- recente (maior criado_em) para aquela data.
CREATE TABLE status_qualidade_dia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data DATE NOT NULL,
  status ENUM("ok","atencao","grave") NOT NULL,
  observacao VARCHAR(255) NULL,
  desvio TEXT NULL,
  acao_tomada TEXT NULL,
  como_evitar TEXT NULL,
  usuario_id INT NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  INDEX idx_status_data (data)
);

-- "Em Formulacao": um lancamento por dia, com capacidade total informada manualmente e o
-- armazenamento utilizado calculado a partir da soma dos 4 tanques (nunca digitado a mao).
CREATE TABLE producao_diaria (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data DATE NOT NULL UNIQUE,
  capacidade_total_litros DECIMAL(10,2) NOT NULL,
  armazenamento_utilizado_litros DECIMAL(10,2) NOT NULL DEFAULT 0,
  usuario_id INT NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE producao_tanques (
  id INT AUTO_INCREMENT PRIMARY KEY,
  producao_diaria_id INT NOT NULL,
  tanque VARCHAR(10) NOT NULL,
  produto VARCHAR(100) NULL,
  valor_litros DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (producao_diaria_id) REFERENCES producao_diaria(id) ON DELETE CASCADE
);

-- Cada indicador agora e um registro proprio e independente (nao mais "um upload substitui
-- tudo"): tem nome, tipo de grafico, seus proprios dados, se esta ativo, e por quanto tempo
-- fica em exibicao (mesmo esquema de data_inicio/data_fim do Mural). Categorias e valores
-- ficam guardados como JSON simples.
CREATE TABLE indicadores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  tipo_grafico ENUM("barra","linha") NOT NULL DEFAULT "barra",
  categorias TEXT NOT NULL,
  valores TEXT NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  data_inicio DATE NOT NULL,
  data_fim DATE NOT NULL,
  usuario_id INT NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Apenas um historico de quais planilhas foram enviadas (auditoria/rastreabilidade),
-- sem ser mais a fonte ao vivo dos dados exibidos.
CREATE TABLE indicadores_arquivos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_arquivo VARCHAR(255) NOT NULL,
  usuario_id INT NOT NULL,
  enviado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Mural (RH + Marketing).
CREATE TABLE mural_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM("imagem","texto") NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  conteudo TEXT NULL,
  imagem_path VARCHAR(255) NULL,
  departamento_id INT NOT NULL,
  data_inicio DATE NOT NULL,
  data_fim DATE NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  usuario_id INT NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (departamento_id) REFERENCES departamentos(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Feeds de RSS para a seção "Últimas notícias" (RH/Marketing cadastram a URL do feed).
CREATE TABLE noticias_rss (
  id INT AUTO_INCREMENT PRIMARY KEY,
  url_feed VARCHAR(500) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  departamento_id INT NOT NULL,
  usuario_id INT NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (departamento_id) REFERENCES departamentos(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE configuracoes (
  chave VARCHAR(50) PRIMARY KEY,
  valor VARCHAR(255) NOT NULL
);

INSERT INTO configuracoes (chave, valor) VALUES
("intervalo_atualizacao_geral_segundos", "300"),
("tempo_rotacao_mural_segundos", "30"),
("tempo_rotacao_indicadores_segundos", "30"),
("cidade_clima", "Serra Negra,BR");

-- Log de auditoria: so uma descricao curta por acao (quem, modulo, quando) — nunca o conteudo
-- em si, ja que o TI nao acessa os dados/arquivos. Mantido por 30 dias (limpeza automatica).
CREATE TABLE log_auditoria (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  modulo VARCHAR(50) NOT NULL,
  descricao VARCHAR(255) NOT NULL,
  data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Feed de noticias padrao (G1) — rode depois de ja existir pelo menos um usuario de RH ou
-- Marketing (troque o e-mail pelo de um usuario real desses departamentos). O Marketing/RH
-- pode trocar ou desativar esse feed a qualquer momento em /admin/noticias.
-- INSERT INTO noticias_rss (url_feed, departamento_id, usuario_id)
-- SELECT "https://g1.globo.com/dynamo/rss2.xml", departamento_id, id
-- FROM usuarios WHERE email = "email-do-usuario-de-marketing-ou-rh@sinergia-agro.com.br";

-- Primeiro usuario de TI (criado direto no banco, sem troca de senha obrigatoria).
-- Gere o hash em /gerar-senha e cole abaixo. Os demais usuarios sao cadastrados pelo proprio TI na
-- tela de Usuarios e esses, sim, precisam trocar a senha inicial no primeiro acesso.
-- INSERT INTO usuarios (nome, email, senha_hash, departamento_id, deve_trocar_senha)
-- VALUES ("Nome do responsavel de TI", "ti@sinergia-agro.com.br", "COLE_O_HASH_AQUI", 4, 0);
--
-- Se o usuario de TI ja foi criado com a troca obrigatoria ligada, libere-o com:
-- UPDATE usuarios SET deve_trocar_senha = 0 WHERE email = "ti@sinergia-agro.com.br";
-- (e, para bancos ja existentes, ajuste tambem o padrao da coluna:)
-- ALTER TABLE usuarios ALTER COLUMN deve_trocar_senha SET DEFAULT 0;
