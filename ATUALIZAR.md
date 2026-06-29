# 🔄 Como atualizar seu site (passo a passo, sem perder nada)

> **Calma — atualizar NÃO apaga nada do seu site** se você seguir estes passos.
> Seus dados (jogadores, pacotes, compras, páginas, cores, configurações) ficam **no banco e em arquivos protegidos** — o update não encosta neles.
> **Não importa de qual versão você está vindo** (1.0, 1.1, 1.2, 1.4…): o comando do Passo 4 (`migrate.php`) detecta sozinho o que falta no seu banco e aplica **só isso**, em ordem, sem apagar nada. Funciona vindo de qualquer versão.
>
> 🧪 **Recomendado pra quem é cauteloso:** se der, teste primeiro numa cópia (subdomínio/staging) — cria suas páginas de teste, confere, e só depois faz no site de produção. Mas seguindo os passos, dá certo direto.

Tempo: ~15 minutos. Precisa: acesso ao **FTP/Gerenciador de Arquivos** da sua hospedagem e ao **phpMyAdmin** (ou SSH/Cron). Não precisa saber programar.

---

## ⚠️ Antes de tudo: 2 regras que evitam 100% dos perrengues

1. **NUNCA use o `install.php` pra atualizar.** Ele é só pra instalar do ZERO. (A partir desta versão ele se recusa a rodar se já tem dados — mas nem chegue perto dele.)
2. **NUNCA apague o `config/config.php`.** É ele que guarda o acesso ao seu banco e suas configurações.

Se seguir essas duas, não tem como quebrar.

---

## Passo 1 — Faça um backup (2 minutos, dorme tranquilo)

**Banco de dados:** entre no **phpMyAdmin** da sua hospedagem → selecione seu banco → aba **Exportar** → **Executar**. Vai baixar um arquivo `.sql`. Guarde.

**Seus arquivos personalizados:** pelo FTP, baixe uma cópia destes (se existirem):
- `config/config.php`
- `public/assets/css/theme.override.css` (suas cores)
- `public/assets/img/custom/` (logo/fundos do painel)
- `public/assets/img/gallery/` (suas screenshots)

Pronto. Se qualquer coisa der errado, você tem como voltar.

---

## 🛑 Passo 2 — A "Regra de Ouro": o que NÃO sobrescrever

Quando for subir os arquivos novos, **NÃO suba / não deixe substituir** estes (são SEUS):

| Não toque | O que é |
|---|---|
| `config/config.php` | seu acesso ao banco + tokens |
| `public/assets/css/theme.override.css` | suas cores |
| `public/assets/img/custom/` | logo/favicon/fundos que você mandou pelo painel |
| `public/assets/img/gallery/` | suas screenshots |
| `storage/` | cache e logs |
| `.htaccess` | só se você editou ele à mão |

> 💡 **Trocou seu logo/fundo substituindo o arquivo direto** em `assets/img/` (jeito antigo)? Faça backup deles antes — ou melhor: depois de atualizar, mande seu logo/cores pelo **Admin → Personalização**. Aí eles ficam guardados e **nenhum update futuro mexe neles**.

---

## Passo 3 — Suba os arquivos novos

1. Baixe a versão nova do site (o `.zip` que a Tecplay te passar / do GitHub).
2. Pelo FTP ou Gerenciador de Arquivos, suba os arquivos **por cima** dos antigos, **respeitando a Regra de Ouro acima**:
   - Tudo que está dentro de `public/` vai pra **raiz do site** (geralmente `public_html`).
   - As pastas `src/`, `views/`, `lang/`, `config/`, `migrations/`, `cli/` e o `schema.sql` vão **um nível acima** (junto da pasta `src/`).
   - **Suba TODAS as pastas.** Se faltar a `lang/`, por exemplo, o menu aparece como "NAV.RULES" (é só reenviar a pasta).

---

## Passo 4 — Atualize o banco (1 comando, não apaga nada)

Isso só **adiciona** o que a versão nova precisa (nunca apaga seus dados).

**Se você tem SSH:**
```
php cli/migrate.php
```

**Se NÃO tem SSH (Hostinger/cPanel):** Painel → **Cron Jobs** → criar um cron "uma vez só" com o comando:
```
php /home/SEU_USUARIO/public_html/cli/migrate.php
```
(o caminho exato você vê no Gerenciador de Arquivos). Rode uma vez e depois apague o cron.

> Pode rodar quantas vezes quiser — ele só aplica o que falta.

---

## Passo 5 — Teste rápido (confere se subiu tudo)

- Abra a **home**, a **loja** e faça **login no `/admin`**.
- Veja se seus pacotes, páginas e configurações estão lá (vão estar — estão no banco).
- Se o menu aparecer como `NAV.RULES`/`SHOP.TITLE`, a pasta `lang/` não subiu — reenvie ela.

Acabou. 🎉

---

## Passo 6 (opcional) — Automação (UM cron só)

**Não é obrigatório.** O site funciona sem cron: a entrega pendente de caixa cai quando alguém abre `/caixas`, o **placar dos eventos de clã** atualiza no tráfego da página, e a premiação você roda no botão **"Premiar agora"**. O cron só deixa tudo mais **pontual** (placar de clã mais ao vivo, congelamento e premiação na hora certa). **Painel → Cron Jobs**, a cada **2 minutos**:

```
curl -s "https://seusite.com/api/cron.php?token=SEU_AGENT_TOKEN"
```
Esse **único cron** já cobre tudo: pendências de caixa + ciclo/placar dos eventos de clã + premiação do ranking.

Prefere separar (endpoints individuais, ainda funcionam)?
```
curl -s "https://seusite.com/api/deliver-boxes.php?token=SEU_AGENT_TOKEN"   # só caixas (2 min)
curl -s "https://seusite.com/api/award-rewards.php?token=SEU_AGENT_TOKEN"   # só premiação (1 h)
```
> `SEU_AGENT_TOKEN` é o que está no seu `config/config.php` (e aparece em **Admin → 🎮 Entrega Sparda**).

---

## ✨ Novidades da v2.2.0 — páginas legais de exemplo (IMPORTANTE)

**Corrige um problema antigo:** sites instalados em versões anteriores nasciam com as **páginas legais vazias** (Termos, Privacidade/LGPD, Reembolso, Regras, FAQ, Como Conectar). Resultado: quem abria `/page/terms` via página em branco.

**Como resolver no seu site (já em produção):** é só rodar o **Passo 4** (`php cli/migrate.php`). A migration `v2.2.0_seed_legal_pages.sql` preenche essas páginas com um **texto de exemplo pronto** (em PT e EN).

> 🛡️ **Não apaga nada do que você já escreveu.** A regra é **"só preenche se estiver vazia"**:
> - Página que **você já editou** → **fica intacta** (não sobrescreve).
> - Página **vazia ou que nunca existiu** → recebe o texto de exemplo.
> - Rodar de novo não duplica nada (idempotente).

**Depois de rodar**, vá em **Admin → Páginas** e ajuste o exemplo pra sua realidade: **nome do servidor, CNPJ/empresa, link do Discord e IP:porta** aparecem como `[NOME DO SERVIDOR]`, `[SEU CNPJ]`, `discord.gg/SEU-CONVITE`, `[IP:PORTA do seu servidor]` — troque por seus dados. (Instalações **novas** já nascem com essas páginas; isso vale só pra quem está atualizando.)

---

## ✨ Novidades da v2.1.0 — o que ligar (opcional)

Esta versão **não tem migration** (não precisa rodar nada no banco) — só subir os arquivos. Pra aproveitar o que chegou:

- **💳 Cartão de crédito dentro do site:** adicione sua **Public Key** do Mercado Pago no `config/config.php`, no bloco `mercado_pago`:
  ```php
  'mercado_pago' => [
      'access_token' => 'APP_USR-...',   // (você já tinha)
      'public_key'   => 'APP_USR-...',   // ← ADICIONE (mesma conta MP, em "Credenciais de produção")
      // ...
  ],
  ```
  Sem a Public Key, só o **PIX** aparece — nada quebra. Com ela, a aba **Cartão** liga sozinha.
- **Parcelamento:** em **Admin → Configurações → "Parcelamento no cartão — valor mínimo (R$)"** (padrão R$ 30). Quem define os juros é a sua conta do Mercado Pago (Custos/Parcelamento).
- O resto (histórico de caixas, logs anti-golpista, SEO, segurança) **já vem ligado automaticamente**.

---

## 🆘 Se algo parecer estranho

| O que você vê | O que é | Como resolver |
|---|---|---|
| Menu/textos como `NAV.RULES`, `SHOP.TITLE` | a pasta `lang/` não subiu | reenvie a pasta `lang/` (fica ao lado de `src/`) |
| Logo/cores voltaram pro padrão | você tinha trocado o arquivo direto e ele foi sobrescrito | restaure do seu backup, e depois mande pelo **painel Personalização** |
| Erro 500 em tudo | PHP abaixo de 8.0 ou `.htaccess` desativado | confirme PHP 8.0+ no painel |
| "Banco indisponível" | o `config/config.php` foi alterado/apagado | restaure ele do backup |
| Página que você editou sumiu | provavelmente o `install.php` foi rodado de novo | **nunca** rode o install pra atualizar; restaure o banco do backup |

**Qualquer dúvida, chama o suporte Tecplay antes de mexer — a gente faz junto com você.**
