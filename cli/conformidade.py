"""Conformidade: compara uma instalacao de cliente com o template, arquivo por arquivo.

POR QUE ISTO EXISTE
-------------------
Os clientes DERIVAM do template com o tempo, e eu descobri isso por acidente: o
`bot-integration.php` do Nomade-Z e o `Settings.php`/`index.php` do Danoninhoz estavam uma
release atras. Descobrir por acidente significa que, quando eu subo um arquivo novo que
depende de outro, o cliente quebra. Aconteceu duas vezes num dia:

  1. subi os `.php` com `data-copiar` e o `app.js` (com o listener) ficou atras -> botao morto;
  2. subi `Boxes.php`/`Vip.php` chamando `Settings::saldoVemDoJogo()` e o `Settings.php`
     ficou atras -> ERRO FATAL ao abrir caixa num cliente pagante.

Conferir no olho nao escala pra 215 arquivos e nao escala pra mais clientes.

A CLASSIFICACAO E O CORACAO DISTO
---------------------------------
"Diferente do template" nao diz nada. O que decide a acao sao tres casos:

  IGUAL       -> nada a fazer.
  ATRASADO    -> o conteudo do cliente e IGUAL a uma versao ANTIGA do template (existe no
                 historico do git). Nao e customizacao: e release velha. Atualizar e o certo
                 e ainda entrega correcoes que faltavam.
  CUSTOMIZADO -> o conteudo nao casa com NENHUMA versao que o template ja teve. Alguem mexeu
                 na mao (tema, ajuste do cliente). Sobrescrever REGRIDE o site dele, entao
                 aqui e patch, e a decisao e humana.

Sem essa distincao eu ou quebro cliente (sobrescrevendo customizacao) ou deixo cliente
atrasado pra sempre (recusando tudo).

CUIDADOS
--------
- Fim de linha normalizado antes de comparar: o Windows entrega CRLF e o git guarda LF, e sem
  isso 100% dos arquivos apareceriam como customizados.
- A pasta publica muda de nome por hospedagem (`public` vs `public_html`), entao o caminho e
  traduzido.
- Arquivo do `.gitignore` (config, tema custom, uploads) NAO entra: e do cliente por design.
- Somente LEITURA por padrao. `--aplicar` atualiza SO os ATRASADOS, com backup antes e
  conferencia byte a byte depois.

Uso:
    set DEP_HOST=1.2.3.4 & set DEP_USER=u & set DEP_PASS=s
    python cli/conformidade.py cliente             # so relatorio
    python cli/conformidade.py cliente --aplicar   # atualiza os ATRASADOS
"""

import ftplib
import hashlib
import io
import os
import subprocess
import sys
from datetime import datetime

# A raiz do template e a pasta acima desta (cli/).
TEMPLATE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Credenciais NUNCA no arquivo: vem do ambiente. Um alvo por execucao.
#   set DEP_HOST=... & set DEP_USER=... & set DEP_PASS=... & python cli/conformidade.py cliente
ALVOS = {
    "cliente": {"host": os.environ.get("DEP_HOST", ""),
                "user": os.environ.get("DEP_USER", ""),
                "senha": os.environ.get("DEP_PASS", "")},
}

PUBLICAS = ("public", "public_html", "htdocs", "www")

# Binarios e coisas que nao valem o round-trip: fonte, imagem do template, favicon.
# Eles nao carregam logica, e um deles diferente nao quebra o site.
IGNORAR_EXT = {".png", ".jpg", ".jpeg", ".webp", ".gif", ".woff2", ".ico", ".svg"}


def git(*args: str) -> str:
    return subprocess.run(["git", *args], cwd=TEMPLATE, capture_output=True,
                          text=False).stdout.decode("utf-8", "replace")


def norm(b: bytes) -> str:
    """Hash do conteudo com fim de linha normalizado (CRLF do Windows vs LF do git)."""
    return hashlib.sha256(b.replace(b"\r\n", b"\n").replace(b"\r", b"\n")).hexdigest()


def historico_do_template() -> tuple[dict, dict]:
    """(atual, historico): path -> hash de HEAD, e path -> {hashes de todas as versoes}."""
    print("montando o historico do template...", end=" ", flush=True)
    commits = git("rev-list", "HEAD").split()
    # (path, blob) de cada commit. Um blob repetido em varios commits e lido uma vez so.
    pares: dict[str, set] = {}
    for c in commits:
        for linha in git("ls-tree", "-r", c).splitlines():
            if not linha.strip():
                continue
            meta, _, path = linha.partition("\t")
            partes = meta.split()
            if len(partes) < 3 or partes[1] != "blob":
                continue
            pares.setdefault(path, set()).add(partes[2])

    # Le cada blob unico uma vez, em lote, e guarda o hash normalizado.
    blobs = {b for s in pares.values() for b in s}
    conteudo: dict[str, str] = {}
    proc = subprocess.Popen(["git", "cat-file", "--batch"], cwd=TEMPLATE,
                            stdin=subprocess.PIPE, stdout=subprocess.PIPE)
    entrada = ("\n".join(blobs) + "\n").encode()
    saida = proc.communicate(entrada)[0]
    pos = 0
    for b in blobs:
        fim = saida.index(b"\n", pos)
        cab = saida[pos:fim].decode()
        tam = int(cab.split()[-1])
        dados = saida[fim + 1:fim + 1 + tam]
        conteudo[b] = norm(dados)
        pos = fim + 1 + tam + 1

    historico = {p: {conteudo[b] for b in s if b in conteudo} for p, s in pares.items()}
    atual = {}
    for linha in git("ls-tree", "-r", "HEAD").splitlines():
        if not linha.strip():
            continue
        meta, _, path = linha.partition("\t")
        partes = meta.split()
        if len(partes) >= 3 and partes[1] == "blob" and partes[2] in conteudo:
            atual[path] = conteudo[partes[2]]
    print(f"{len(commits)} commits, {len(atual)} arquivos, {len(blobs)} versoes")
    return atual, historico


def acha_raiz(f, home: str):
    def tem(caminho: str) -> bool:
        try:
            pai, _, nome = caminho.rpartition("/")
            return any(i.rstrip("/").rpartition("/")[2] == nome for i in f.nlst(pai))
        except Exception:
            return False

    candidatos = [home.rstrip("/") or "/"]
    candidatos += [home.rstrip("/") + "/" + p for p in PUBLICAS]
    try:
        for linha in f.nlst(home.rstrip("/") + "/domains"):
            dom = linha.rstrip("/").rpartition("/")[2]
            if dom not in (".", ".."):
                candidatos.append(home.rstrip("/") + "/domains/" + dom)
    except Exception:
        pass
    for raiz in candidatos:
        raiz = raiz.rstrip("/") or ""
        if tem(f"{raiz}/views/admin/sparda.php"):
            for p in PUBLICAS:
                if tem(f"{raiz}/{p}/index.php"):
                    return raiz, p
    return None, None


def reconecta(alvo):
    """Conexao nova. O FTP do host cai em varredura longa e perder o resto por isso e bobo."""
    f = ftplib.FTP_TLS()
    f.connect(alvo["host"], 21, timeout=45)
    f.login(alvo["user"], alvo["senha"])
    f.prot_p()
    f.set_pasv(True)
    return f


def fecha(f):
    """QUIT estoura quando o servidor ja fechou o canal. Nao vale derrubar o relatorio."""
    try:
        f.quit()
    except Exception:
        try:
            f.close()
        except Exception:
            pass


def main() -> int:
    if len(sys.argv) < 2 or sys.argv[1] not in ALVOS:
        print("uso: python conformidade.py " + "|".join(ALVOS) + " [--aplicar]")
        return 2
    apelido, aplicar = sys.argv[1], "--aplicar" in sys.argv
    alvo = ALVOS[apelido]

    atual, historico = historico_do_template()

    # Fora: gitignored (é do cliente por design) e binario.
    # `tests/` nao vai pro cliente, e o `install.php` e RENOMEADO depois de instalar (some de
    # proposito). Contar os dois como "ausente" so faria o relatorio mentir que ha problema.
    alvos_path = [p for p in atual
                  if os.path.splitext(p)[1].lower() not in IGNORAR_EXT
                  and not p.endswith(".gitkeep")
                  and not p.startswith("tests/")
                  and p != "public/install.php"]

    f = ftplib.FTP_TLS()
    f.connect(alvo["host"], 21, timeout=45)
    f.login(alvo["user"], alvo["senha"])
    f.prot_p()
    f.set_pasv(True)
    HOME = f.pwd()
    RAIZ, PUB = acha_raiz(f, HOME)
    if RAIZ is None:
        print(f"NAO achei a raiz do projeto (HOME={HOME}). Nao vou concluir nada.")
        fecha(f)
        return 2
    print(f"conectado | raiz={RAIZ or '/'} | pasta publica={PUB}\n")

    iguais, atrasados, customizados, ausentes, erros = [], [], [], [], []
    for i, path in enumerate(sorted(alvos_path), 1):
        remoto_rel = (PUB + path[len("public"):]) if path.startswith("public/") else path
        remoto = (RAIZ.rstrip("/") + "/" + remoto_rel) if RAIZ else "/" + remoto_rel
        buf = io.BytesIO()
        try:
            f.retrbinary("RETR " + remoto, buf.write)
        except ftplib.error_perm:
            ausentes.append(path)
            continue
        except Exception:
            # Numa varredura de 200 arquivos o FTP derruba a conexao no meio. Reconectar e
            # seguir; reportar como erro esconderia o estado real do arquivo.
            try:
                f = reconecta(alvo)
                buf = io.BytesIO()
                f.retrbinary("RETR " + remoto, buf.write)
            except ftplib.error_perm:
                ausentes.append(path)
                continue
            except Exception as e2:
                erros.append((path, str(e2)[:60]))
                continue
        h = norm(buf.getvalue())
        if h == atual[path]:
            iguais.append(path)
        elif h in historico.get(path, ()):
            atrasados.append(path)
        else:
            customizados.append(path)
        if i % 40 == 0:
            print(f"  ... {i}/{len(alvos_path)}")

    print(f"\n{'='*70}\nCONFORMIDADE - {apelido}\n{'='*70}")
    print(f"  IGUAIS ao template ... {len(iguais)}")
    print(f"  ATRASADOS ............ {len(atrasados)}   (versao antiga: da pra atualizar)")
    print(f"  CUSTOMIZADOS ......... {len(customizados)}   (mexido na mao: decisao humana)")
    print(f"  AUSENTES ............. {len(ausentes)}")
    print(f"  ERROS ................ {len(erros)}")
    for rot, lista in (("ATRASADO", atrasados), ("CUSTOMIZADO", customizados),
                       ("AUSENTE", ausentes)):
        if lista:
            print(f"\n  --- {rot} ---")
            for p in lista:
                print(f"    {p}")
    for p, e in erros:
        print(f"    ERRO {p}: {e}")

    if not aplicar:
        print("\n(somente relatorio. use --aplicar pra atualizar os ATRASADOS)")
        fecha(f)
        return 0

    if not atrasados:
        print("\nnada atrasado pra atualizar.")
        fecha(f)
        return 0

    stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    bkp = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                       f"conformidade-{apelido}-{stamp}")
    os.makedirs(bkp, exist_ok=True)
    print(f"\naplicando {len(atrasados)} arquivo(s) ATRASADO(s). backup em {bkp}")
    falhas = 0
    def com_reconexao(fn, tentativas=3):
        """Roda `fn(conexao)` reconectando se o FTP cair.

        A APLICACAO precisa disso mais que a varredura: um EOF no meio deixa o cliente
        PARCIALMENTE atualizado, e arquivo novo dependendo de arquivo velho e o jeito mais
        rapido de quebrar o site dele (aconteceu duas vezes hoje).
        """
        nonlocal f
        ultimo = None
        for _ in range(tentativas):
            try:
                return fn(f)
            except (EOFError, OSError, ftplib.error_temp, ftplib.error_proto) as e:
                ultimo = e
                try:
                    f = reconecta(alvo)
                except Exception as e2:
                    ultimo = e2
        raise ultimo if ultimo else RuntimeError("falhou sem erro")

    for path in atrasados:
        remoto_rel = (PUB + path[len("public"):]) if path.startswith("public/") else path
        remoto = (RAIZ.rstrip("/") + "/" + remoto_rel) if RAIZ else "/" + remoto_rel
        novo_conteudo = open(os.path.join(TEMPLATE, path), "rb").read()

        def baixa(con, r=remoto):
            b = io.BytesIO()
            con.retrbinary("RETR " + r, b.write)
            return b.getvalue()

        try:
            antes = com_reconexao(baixa)
            open(os.path.join(bkp, path.replace("/", "__")), "wb").write(antes)
            com_reconexao(lambda con, r=remoto, d=novo_conteudo:
                          con.storbinary("STOR " + r, io.BytesIO(d)))
            depois = com_reconexao(baixa)
        except Exception as e:
            print(f"  FALHA  {path}  ({type(e).__name__}: {str(e)[:50]})")
            falhas += 1
            continue

        ok = norm(depois) == atual[path]
        print(f"  {'ok' if ok else 'FALHA'}  {path}")
        if not ok:
            falhas += 1
    fecha(f)
    print()
    print("TUDO EM CONFORMIDADE" if not falhas else f"ATENCAO: {falhas} falha(s)")
    return 1 if falhas else 0


if __name__ == "__main__":
    sys.exit(main())
