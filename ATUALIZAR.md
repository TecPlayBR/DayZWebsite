# 🔄 Como atualizar seu site (passo a passo, sem perder nada)

> **Calma — atualizar NÃO apaga nada do seu site** se você seguir estes passos.
> Seus dados (jogadores, pacotes, compras, páginas, cores, configurações) ficam **no banco e em arquivos protegidos** — o update não encosta neles.
> Você está na versão **1.4.0**. Este guia leva pra versão atual com segurança.

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

## Passo 5 — Confira se subiu tudo certo

Abra no navegador: **`https://seusite.com/verificar.php`**

Ele mostra um checklist verde/vermelho do que subiu (não expõe nenhuma senha). Se tiver algo **vermelho**, é só reenviar aquela pasta. **Depois de conferir, apague o `verificar.php`.**

---

## Passo 6 — Teste rápido

- Abra a home, a loja e faça login no `/admin`.
- Veja se seus pacotes, páginas e configurações estão lá (vão estar — estão no banco).

Acabou. 🎉

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
