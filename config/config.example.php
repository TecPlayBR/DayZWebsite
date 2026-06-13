<?php
// ============================================================
// Tecplay - DayZ Website Template
// (c) 2026 Tecplay - distribuido como template comercial
// ============================================================
// IMPORTANTE: este eh o arquivo MODELO. NAO edite aqui — o
// install.php cria automaticamente o /config/config.php real
// quando voce roda o wizard pela primeira vez.
//
// Caso queira configurar manualmente sem o wizard:
//   1. Copie este arquivo pra ../config/config.php
//   2. Substitua os ALTERE_AQUI por seus valores reais
//   3. Acesse o site
// ============================================================

return [

    // ======== SITE ========
    'site_name'       => 'ALTERE_AQUI_NOME_DO_SITE',
    'site_tagline'    => 'ALTERE_AQUI_TAGLINE_DO_SITE',
    'site_url'        => 'https://ALTERE_AQUI_seudominio.com.br',
    'default_locale'  => 'pt-br',

    // ======== AMBIENTE ========
    // 'production' (default): exige webhook_secret do Mercado Pago e validacao
    // estrita de assinatura. Em 'development' a validacao e mais frouxa pra
    // facilitar testes locais.
    'env' => 'production',

    // ======== BANCO DE DADOS (cPanel/Hostinger gera essas credenciais) ========
    'db' => [
        'host'    => 'localhost',                       // geralmente "localhost" no cPanel
        'name'    => 'ALTERE_AQUI_NOME_DA_DATABASE',
        'user'    => 'ALTERE_AQUI_USUARIO_DB',
        'pass'    => 'ALTERE_AQUI_SENHA_DB',
        'charset' => 'utf8mb4',
    ],

    // ======== ADMIN ========
    // Hash bcrypt da senha admin. Gere com:
    //    php -r "echo password_hash('SUASENHA', PASSWORD_BCRYPT);"
    // (o install.php cria isso automaticamente — so use manual se nao for usar o wizard)
    'admin_password_hash' => 'ALTERE_AQUI_HASH_BCRYPT_DA_SENHA',
    'admin_session_ttl'   => 3600,                      // 1h

    // ======== AGENT TECPLAY (sincronizacao de moedas com o servidor DayZ) ========
    // Token aleatorio que o tecplay-agent.exe usa pra autenticar no endpoint
    // /api/sync-players. Gere uma string randomica com no minimo 32 chars.
    // Sugestao via shell: php -r "echo bin2hex(random_bytes(24));"
    'agent_token' => 'ALTERE_AQUI_TOKEN_DO_AGENT',

    // ======== STEAM WEB API (opcional, pra avatar/nick do jogador) ========
    // Pega aqui: https://steamcommunity.com/dev/apikey
    // SEM essa chave, o login Steam funciona mas nao mostra avatar/nick real.
    // Deixe vazio se nao for usar.
    'steam_api_key' => '',

    // ======== CFTOOLS CLOUD (opcional — leaderboard + perfil de gameplay) ========
    // Liga as estatisticas REAIS do seu servidor (kills, K/D, tempo online, armas)
    // no /ranking e no perfil /player/{steamid}. Use o SEU PROPRIO app CFTools
    // (cada dono usa o seu — seu secret so controla o SEU servidor).
    //
    // Como obter (3 valores), passo a passo:
    //   1. Acesse https://developer.cftools.cloud e faca login com sua conta CFTools.
    //   2. Crie uma "Application" -> copie o Application ID e o Secret -> 'app_id' / 'secret'.
    //   3. Pegue o "Server API ID" (UUID) do SEU servidor em app.cftools.cloud
    //      (Server -> Settings/API) -> 'server_api_id'.
    //   4. Abra a "Grant URL" do app (no painel da Application) e AUTORIZE o app a
    //      ver o seu servidor. Sem o grant, a API responde "no-grant".
    // Deixe os 3 vazios se nao for usar — o site funciona normal, so sem stats de gameplay.
    'cftools' => [
        'app_id'        => '',   // Application ID do seu app CFTools
        'secret'        => '',   // Secret do seu app CFTools (NUNCA compartilhe)
        'server_api_id' => '',   // UUID do seu servidor registrado no CFTools
    ],

    // ======== MERCADO PAGO (pagamentos PIX/boleto/cartao) ========
    // Pega ambos os tokens em: https://www.mercadopago.com.br/developers/panel
    // Sem isso o site funciona, mas o checkout cai em "modo dev" que so
    // simula sem cobrar — ate voce configurar, ninguem consegue pagar.
    'mercado_pago' => [
        'access_token'      => 'ALTERE_AQUI_ACCESS_TOKEN_MP',   // TEST-... pra testes, APP_USR-... pra producao
        'webhook_secret'    => '',                              // opcional — recomendado em producao
        'currency'          => 'BRL',
        'min_purchase_brl'  => 5,
    ],

    // ======== E-MAIL (recibo de compra) ========
    // Em hospedagens compartilhadas (Hostinger/cPanel) o mail() nativo do PHP
    // funciona out-of-the-box — basta preencher o "from" com um e-mail do seu
    // dominio (ex: noreply@seudominio.com.br). SEM "from" preenchido, os e-mails
    // ficam apenas logados em /storage/cache/mail-log.txt (modo dev).
    'mail' => [
        'from'      => '',                       // ex: noreply@seudominio.com.br
        'from_name' => 'ALTERE_AQUI_NOME_DO_SITE',
    ],

    // ======== APRESENTACAO ========
    'show_payment_methods' => true,   // mostra logos MP/PIX/SSL no rodape
    'show_language_select' => true,   // mostra dropdown PT-BR/EN-US no header

    // ======== BOT DISCORD (opcional, integracao) ========
    // Se voce instalou o bot Tecplay Discord, configure pra ele receber
    // notificacao automatica de vendas aprovadas e postar em #vendas.
    // Endpoint padrao: http://127.0.0.1:8765 (bot rodando no mesmo host).
    // Token: o mesmo WEBHOOK_TOKEN do .env do bot.
    'bot' => [
        'endpoint' => '',  // ex: http://127.0.0.1:8765 (deixe vazio pra desativar)
        'token'    => '',  // mesmo WEBHOOK_TOKEN do bot
    ],
];
