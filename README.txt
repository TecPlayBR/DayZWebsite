==============================================================
  TECPLAY - DAYZ WEBSITE TEMPLATE
==============================================================
  (c) 2026 Tecplay - Distribuido gratuitamente
  Sparda Store Integration Suite (Tecplay Edition)
==============================================================

O QUE E?
--------
Template de site pra servidor de DayZ brasileiro, gratuito,
PHP vanilla (sem framework). Visual apocalipse/zombie, painel
admin pronto, integracao com PIX/Mercado Pago, multi-idioma
PT-BR/EN-US. Roda em qualquer hospedagem com PHP 8.0+.

ESTRUTURA
--------
DayZWebsite/
  config/              ← config.example.php (copie pra config.php)
  lang/                ← traducoes PT-BR e EN-US
  public/              ← ISSO vai no public_html da hospedagem
    assets/
    index.php
    install.php
    .htaccess
  src/                 ← codigo compartilhado (Router, View, Lang)
  views/               ← templates PHP
  README.txt           ← este arquivo

INSTALACAO (cliente final)
--------
1. Sobe a pasta INTEIRA pra hospedagem (FileZilla via FTP)
2. Aponta o public_html pra `public/`
   (ou se o public_html for fixo, sobe SO o conteudo de `public/`
    pra dentro do public_html, e o resto fica num nivel acima)
3. Cria uma database MySQL no cPanel
4. Acessa https://seudominio.com/install.php
5. Preenche o form e clica em Instalar

Pronto.

REQUISITOS NO SERVIDOR
--------
- PHP 8.0+ (8.2+ recomendado)
- MySQL 5.7+ ou MariaDB 10.3+
- Extensoes PHP: PDO, PDO_mysql, mbstring, json
- mod_rewrite habilitado no Apache (vem por padrao na maioria das hospedagens)

OTIMIZACOES RECOMENDADAS (antes de subir pra producao)
--------
1. background.png (2.4MB) -> otimizar com https://tinypng.com ou https://squoosh.app
   Reducao tipica: 60-80% do tamanho sem perda visual perceptivel.
   Salve por cima do arquivo em public/assets/img/background.png

2. Habilitar HTTPS na hospedagem (Let's Encrypt gratis na maioria dos cPanels)
   Depois descomente as 3 linhas de force HTTPS no public/.htaccess

3. Compressao gzip/brotli ja vem habilitada via .htaccess
   Cache de assets ja vem habilitado via .htaccess

DESENVOLVIMENTO (Tecplay/dev)
--------
- Rode `php -S localhost:8000 -t public public/index.php`
  e acesse http://localhost:8000
- Sem composer, sem npm, sem build step

ARQUIVO DE CONFIGURACAO
--------
`config/config.example.php` - copie como `config/config.php` e edite.
Contem: dados de banco, senha admin, AGENT_TOKEN, credenciais Mercado Pago.

LICENCA - LEIA ANTES DE INSTALAR
--------
GRATUITO PARA USO PROPRIO. PROIBIDO REDISTRIBUIR DE FORMA
MONETIZADA (vender, revender, alugar, cobrar acesso). A venda
nao autorizada e crime conforme:
  - Art. 184, paragrafo 2o do Codigo Penal (reclusao 2-4 anos)
  - Lei 9.609/98 Art. 12 (Lei do Software)
  - Lei 9.610/98 (Direitos Autorais)

Detectada venda nao autorizada, o infrator sera:
  1) Acionado judicialmente (criminal e civil)
  2) Exposto publicamente nos canais Tecplay e comunidade DayZ

Modificacoes para uso proprio sao permitidas mas tiram direito
a suporte oficial. Leia o LICENSE.txt completo.

SUPORTE
--------
Suporte oficial (versao original):
  https://tecplay.inf.br/suporte/

Modificacoes/customizacoes sob demanda (servico pago):
  https://tecplay.inf.br/servicos/#web

Canais:
  E-mail:  suporte@tecplay.inf.br
  Discord: https://discord.gg/uwSE3WSjNH
  Site:    https://tecplay.inf.br

VERSOES MODIFICADAS NAO RECEBEM SUPORTE OFICIAL.
