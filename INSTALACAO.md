# 🚀 Guia de Instalação — Do Zero Absoluto

> Você baixou o ZIP do **Tecplay DayZ Website Template** e não sabe por onde começar?
> Tá no lugar certo. Vamos do passo 1.

**Tempo total estimado:** 30 a 45 minutos
**Conhecimento necessário:** zero. Se você sabe usar cPanel e FileZilla, consegue.

---

## ⚠️ LEIA PRIMEIRO — Licença e Suporte (importante)

Este Template é **GRATUITO**. Você pode baixar, instalar e usar livremente no SEU servidor DayZ. Mas tem regras importantes que você precisa saber **ANTES** de instalar:

### ✅ O que VOCÊ PODE fazer

- Instalar em quantos servidores DayZ **próprios** quiser
- Modificar o código (PHP/JS/CSS) pra suas necessidades
- Customizar logo, cores, textos, imagens
- Indicar o site oficial da Tecplay pra outros baixarem

### ❌ O que VOCÊ NÃO PODE fazer (PROIBIDO POR LEI)

- **VENDER, REVENDER ou COBRAR** por este Template (modificado ou não)
- Publicar o código em repositório público (GitHub público, paste sites, etc)
- Remover créditos da Tecplay nos arquivos
- Usar o nome ou marca "Tecplay" sem autorização

> 🚨 **A venda não autorizada é CRIME** previsto no **Art. 184, §2º do Código Penal** (reclusão de 2 a 4 anos + multa) e na **Lei 9.609/98 Art. 12** (Lei do Software).
>
> **Identificada a venda**, o infrator será:
> 1. Denunciado às autoridades competentes (representação criminal)
> 2. Acionado civilmente por perdas e danos
> 3. **EXPOSTO PUBLICAMENTE** nos canais oficiais Tecplay e na comunidade DayZ
>
> Leia o `LICENSE.txt` completo antes de qualquer coisa.

### 🛠️ Sobre SUPORTE TÉCNICO

- **Versão ORIGINAL (sem modificações):** suporte disponível mediante plano contratado.
  Planos e preços em **https://tecplay.inf.br/suporte/**

- **Versão MODIFICADA (você editou o código):** SEM SUPORTE da Tecplay. Você é responsável por
  suas próprias modificações.

- **Quer uma nova funcionalidade?** Clientes com plano de suporte ativo podem abrir ticket
  sugerindo. Sugestões aprovadas entram no roadmap das próximas versões.

- **Quer customização sob demanda?** (algo só pra você, fora do template padrão):
  serviço pago em **https://tecplay.inf.br/servicos/#web**

---

## 📋 Antes de começar — checklist

- [ ] Você baixou e extraiu o ZIP do template
- [ ] Você tem (ou vai contratar) uma hospedagem web com PHP 8.0+ e MySQL
- [ ] Você tem (ou vai contratar) um domínio (ex: `meuservidor.com.br`)
- [ ] Você tem uma conta no Mercado Pago (opcional — pode configurar depois)

---

## 🌐 Passo 0 — Hospedagem (se você ainda não tem)

Você pode usar **qualquer hospedagem com PHP 8.0+ e MySQL**. Nossa recomendação pra quem está começando:

### Hostinger (recomendado, opcional)

Plano básico já funciona. **Use este link de afiliado se quiser apoiar o projeto Tecplay** — você paga o mesmo, mas a gente recebe uma comissão:

🔗 **https://www.hostinger.com/br?REFERRALCODE=ITGTECPLANYS**

Plano sugerido: **Premium ou superior** (suporta o tráfego de um servidor DayZ médio).

### Outras opções

KingHost, Locaweb, HostGator, UolHost, ou qualquer hospedagem que tenha:
- PHP 8.0 ou mais novo
- MySQL 5.7+ ou MariaDB 10.3+
- Acesso via cPanel ou similar
- Suporte a `.htaccess` (Apache)

---

## 🗄️ Passo 1 — Criar a database MySQL

1. Acesse o **cPanel** da sua hospedagem (link e senha vêm no e-mail de boas-vindas)
2. Procure pela seção **"Banco de Dados"** ou **"MySQL"**
3. Clique em **"Criar nova database"** ou **"MySQL Databases"**
4. Preencha:
   - **Nome da database:** algo como `meusite_dayz` (anote!)
   - **Usuário:** crie um novo (anote!)
   - **Senha:** anote!
5. **IMPORTANTE:** dê permissão **ALL PRIVILEGES** ao usuário sobre a database criada

> 💾 No final você deve ter anotado **4 coisas**: host (geralmente `localhost`), nome da database, usuário e senha. Vai usar daqui a pouco.

---

## 📤 Passo 2 — Subir os arquivos pro servidor

Você pode usar **FTP** (recomendado, FileZilla é grátis) ou o **Gerenciador de Arquivos do cPanel**.

### Estrutura importante

O template tem essa estrutura:

```
DayZWebsite/        ← o que você baixou e extraiu
├── public/         ← SÓ ISSO vai pra dentro do public_html
├── src/            ← um nível ACIMA do public_html
├── views/          ← um nível ACIMA do public_html
├── lang/           ← um nível ACIMA do public_html
├── config/         ← um nível ACIMA do public_html
├── storage/        ← um nível ACIMA do public_html
├── schema.sql      ← um nível ACIMA do public_html
└── README.md, LICENSE.txt, ...
```

### Cenário A: hospedagens onde o `public_html` é fixo (Hostinger, KingHost, Locaweb)

1. Conecte via FTP na raiz da hospedagem
2. **Suba TUDO menos a pasta `public/`** pra dentro da raiz (`home/seuusuario/` ou similar — **um nível acima do `public_html`**)
3. **Suba o CONTEÚDO da pasta `public/`** (não a pasta em si, só o que tá dentro) pra dentro do `public_html`

Resultado final no servidor:

```
/home/seu_usuario/                ← raiz
├── public_html/                  ← document root (acessível pela web)
│   ├── index.php
│   ├── install.php
│   ├── .htaccess
│   ├── assets/
│   └── api/
├── src/                          ← protegido (sem acesso web)
├── views/
├── lang/
├── config/
├── storage/
└── schema.sql
```

### Cenário B: VPS ou hospedagem onde você controla o document root

Aponte o document root direto pra pasta `public/` do template. Outros diretórios ficam num nível acima.

---

## ⚙️ Passo 3 — Rodar o instalador (parte mágica)

1. Abra o navegador
2. Acesse: **`https://seudominio.com.br/install.php`**
3. Você vai ver um wizard bonito com 5 cards:

### Card 1: Site
- **Nome do site:** algo como "MEU DAYZ" ou "[NOME] Server"
- **Tagline:** uma frase curta pra hero (ex: "Sobreviva. Construa. Domine.")
- **URL completa:** `https://seudominio.com.br`

### Card 2: Banco de Dados MySQL
Use as 4 informações que você anotou no Passo 1:
- **Host:** geralmente `localhost`
- **Database:** o nome que você criou
- **Usuário:** o que você criou
- **Senha:** a que você definiu

### Card 3: Admin do Painel
- **Usuário:** sugestão: `admin`
- **E-mail:** opcional, pra contato
- **Senha:** mínimo 8 caracteres, **NÃO** use senhas óbvias
- **Confirmar senha:** repita

### Card 4: AGENT_TOKEN
O wizard **já sugere um token aleatório forte**. Clique no campo amarelo pra copiar o valor sugerido pro input abaixo. Anote esse token! Você vai precisar dele depois pra configurar o `tecplay-agent.exe`.

### Card 5: Mercado Pago
**Opcional agora.** Pode deixar vazio se ainda não tem conta MP — você configura depois pelo painel admin. Se já tem:
- **Access Token:** começa com `TEST-` (testes) ou `APP_USR-` (produção)
- **Webhook Secret:** opcional, mas recomendado em produção

### Clique em **INSTALAR**

Se tudo deu certo, você vê uma tela verde dizendo:

> ✅ **Instalado com sucesso!**

E o `install.php` **se auto-renomeia** pra um arquivo com timestamp (segurança).

---

## 🧪 Passo 4 — Testar se funcionou

1. Acesse `https://seudominio.com.br/` — você deve ver a landing apocalipse
2. Acesse `https://seudominio.com.br/admin/login` — faça login com o usuário e senha do passo 3
3. No painel admin você vê:
   - Dashboard com stats zerados (esperado, ainda sem players)
   - Lista de pacotes (6 seedados)
   - Configurações

🎉 **Se chegou aqui, o site está no ar!**

---

## 🎨 Passo 5 — Customizar (mínimo necessário antes de divulgar)

### 5.1 — Configurar redes sociais

`Admin → Configurações → Redes Sociais`

Cole os links que tiver:
- Discord (link de convite)
- YouTube, Instagram, Facebook, WhatsApp (opcional)

Os ícones aparecem automaticamente no rodapé. Deixe vazio pra esconder.

### 5.2 — Trocar logo

Substitua o arquivo `public_html/assets/img/logo_semfundo.png` pelo seu logo (PNG transparente, ~250x250 recomendado).

### 5.3 — Trocar background do hero

O `public_html/assets/img/background.png` é o background da hero principal. Recomendado:
- Resolução mínima: 1920x1080
- **Otimize antes de subir** via [TinyPNG](https://tinypng.com) ou [Squoosh](https://squoosh.app) — pode reduzir de 2MB pra 300KB sem perda visual

### 5.4 — Configurar IP/porta do servidor DayZ

`Admin → Configurações → Servidor DayZ`
- IP público
- Porta (geralmente 2302)

### 5.5 — Editar páginas legais

`Admin → Páginas`

Os arquivos `terms`, `privacy`, `refund` já foram seedados em PT-BR e EN-US. **Leia e adapte ao seu caso real.** Recomendamos revisão por advogado antes de divulgar pro público.

---

## 💳 Passo 6 — Configurar Mercado Pago (pra começar a vender)

### Se você não fez no install

1. Crie conta em https://www.mercadopago.com.br/
2. Acesse o painel de desenvolvedores: https://www.mercadopago.com.br/developers/panel
3. Crie uma aplicação
4. Pegue o **Access Token** (use o de produção `APP_USR-...` quando estiver pronto pra vender de verdade)
5. **Edite `config/config.php`** (no servidor, via FTP) e cole o token:
   ```php
   'access_token' => 'APP_USR-1234567890-...',
   ```

### Configurar o webhook

No painel MP, configure o webhook pra:
- URL: `https://seudominio.com.br/api/mp-webhook.php`
- Eventos: **Payments** (pagamentos)

Isso faz o site receber notificação quando o pagamento confirma, e creditar as moedas automaticamente.

### Testar

1. Vai no `/shop`
2. Escolhe um pacote, informa um SteamID válido (17 dígitos começando com 7656119)
3. Clica em COMPRAR — você é redirecionado pro Mercado Pago
4. Faz um pagamento PIX (modo TEST não cobra de verdade)
5. Volta pro site e confere em `Admin → Compras` que o status mudou pra `approved`
6. Confere em `Admin → Jogadores` que o saldo foi creditado

---

## 🎮 Passo 7 — Integrar com o Tecplay Agent

> Este passo só é necessário se você tem o `tecplay-agent.exe` instalado no servidor DayZ.

1. No servidor DayZ (via RDP):
   - Roda o `Install.bat` do agent (entregue separadamente pela Tecplay)
   - Cola o **AGENT_TOKEN** que você anotou no Passo 3 (Card 4)
   - URL da API: `https://seudominio.com.br/api/sync-players.php`
2. O agent começa a sincronizar moedas a cada 15 segundos

📞 **Sem o agent ainda?** Adquira em contato com a Tecplay.

---

## 🔐 Passo 8 — HTTPS e segurança final

### Habilitar HTTPS

Quase toda hospedagem moderna tem **Let's Encrypt grátis** no cPanel:
1. cPanel → SSL/TLS → **AutoSSL** ou **Let's Encrypt**
2. Selecione seu domínio e ative
3. Aguarde 10–30 minutos pra propagar

### Forçar redirect HTTP → HTTPS

No arquivo `public_html/.htaccess`, descomente as 3 linhas:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Conferir checklist de segurança

- [ ] `install.php` foi removido ou auto-renomeado
- [ ] Admin com senha forte (12+ caracteres)
- [ ] AGENT_TOKEN com 32+ caracteres (o wizard gera assim)
- [ ] HTTPS ativo
- [ ] Webhook MP com `webhook_secret` configurado (opcional mas recomendado)

---

## 🆘 Algo deu errado?

### "Internal Server Error" ao acessar
- Geralmente é PHP < 8.0 ou `.htaccess` não suportado
- Confirme com a hospedagem que tem PHP 8.0+ e `mod_rewrite`

### "DB unavailable"
- Credenciais erradas no `config.php`
- Confirma `host`, `name`, `user`, `pass` contra o que cPanel mostra

### Páginas dão 404
- O `.htaccess` não está sendo lido (servidor sem `mod_rewrite`)
- Pede pra hospedagem habilitar `mod_rewrite` e `AllowOverride All`

### `install.php` sai com erro "DB ja tem tabelas"
- Use uma database vazia, ou apague as tabelas existentes antes

### Login admin não funciona após install
- Confere se o cookie de sessão está sendo aceito (HTTPS bloqueia cookies `Secure` em HTTP)
- Tenta acessar via aba anônima

---

## 📞 Suporte

**Lembrete da seção inicial:** suporte oficial Tecplay é exclusivo para versões **NÃO MODIFICADAS** com plano contratado.

### Planos de suporte (versão original)
👉 **https://tecplay.inf.br/suporte/**

Inclui:
- Diagnóstico e correção de problemas no template original
- Atualizações regulares (correções, melhorias, novas features)
- Direito de abrir ticket sugerindo novas funcionalidades

### Modificações sob demanda (algo específico só pra você)
👉 **https://tecplay.inf.br/servicos/#web**

Inclui:
- Customizações fora do template padrão
- Integrações específicas (Discord bot, mods, sistemas próprios)
- Desenvolvimento de features sob medida

### Canais

- 📧 **E-mail:** suporte@tecplay.inf.br
- 💬 **Discord:** https://discord.gg/uwSE3WSjNH
- 🌐 **Site:** https://tecplay.inf.br

> ⚠️ Pedidos de suporte para versões modificadas pelo cliente serão automaticamente direcionados ao serviço de modificação sob demanda. Não há como diagnosticar/atualizar código alterado por terceiros.

---

## 📚 Próximos passos (recomendados após estar no ar)

1. **Personaliza pacotes** (Admin → Pacotes → Editar) com seus preços
2. **Cria página "Sobre"** ou "Como Comprar" (Admin → Páginas → Nova)
3. **Configura backups automáticos** no cPanel da hospedagem
4. **Monitora vendas** em Admin → Dashboard
5. **Faz teste de compra real** com R$ 5 antes de divulgar pro público
6. **Quando estiver tudo OK,** divulga seu site!

🎯 **Bom servidor pra você, e boas vendas!**
