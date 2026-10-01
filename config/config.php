<?php
// Ajuste estes dados conforme o painel da Locaweb (MySQL > Detalhes da conexão)
define("DB_HOST", "localhost");
define("DB_NAME", "uriel_sinergia");
define("DB_USER", "uriel_sinergia");
define("DB_PASS", "Sinergia@2023");

define("SITE_URL", "https://www.vinceassessorios.com.br/dashboard");
define("TIMEZONE", "America/Sao_Paulo");
date_default_timezone_set(TIMEZONE);

// Chave da API de clima (OpenWeatherMap)
define("CLIMA_API_KEY", "6e5ac0b27e0a520eb089a9f00c2e6556");
define("CLIMA_CIDADE", "Serra Negra,BR");

// Quantos dias a sessão da TV fica salva sem precisar logar de novo
define("SESSAO_VISUALIZACAO_DIAS", 365);
