<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// cli/migrate-lib.php - motor das migrations, compartilhado
// ============================================================
// Usado por DOIS lugares, de proposito:
//   - cli/migrate.php  (linha de comando / cron)
//   - public/update.php (tela de atualizacao, protegida por login de admin)
// Ter um motor so evita os dois caminhos divergirem com o tempo e um aplicar
// migration de um jeito e o outro de outro.
//
// Regra que vale em todo lugar: SO ADICIONA. Nenhuma funcao daqui apaga dado
// de cliente. A unica migration destrutiva do template e a v2.18.0
// (aposenta o Sistema de Pontos) e ela e destrutiva de proposito.
// ============================================================

declare(strict_types=1);

if (!function_exists('mig_ordenar')) {
    /**
     * Ordena migrations por VERSAO, nao alfabeticamente.
     *
     * Por que isto existe: com sort() puro, 'v2.10.0' vem antes de 'v2.2.0'
     * porque '1' < '2' no alfabeto. Na pratica isso jogava v2.2.0, v2.4.0,
     * v2.5.0, v2.6.0, v2.7.0 e v2.9.1 pro FIM da fila, depois da v2.23.0.
     * Numa instalacao do zero nao dava problema porque o schema.sql ja cria
     * tudo antes, mas num cliente atualizando de uma versao antiga a fila saia
     * fora de ordem. Aqui comparamos numero por numero.
     */
    function mig_ordenar(array $arquivos): array {
        usort($arquivos, function ($a, $b) {
            $peca = function (string $nome): array {
                if (preg_match('/^v?(\d+)\.(\d+)\.(\d+)/', basename($nome), $m)) {
                    return [(int)$m[1], (int)$m[2], (int)$m[3], basename($nome)];
                }
                // sem versao no nome: vai pro fim, mas de forma estavel
                return [PHP_INT_MAX, 0, 0, basename($nome)];
            };
            $pa = $peca($a); $pb = $peca($b);
            for ($i = 0; $i < 3; $i++) {
                if ($pa[$i] !== $pb[$i]) return $pa[$i] <=> $pb[$i];
            }
            return strcmp($pa[3], $pb[3]);   // empate de versao: nome, pra ser deterministico
        });
        return $arquivos;
    }
}

if (!function_exists('mig_garante_tabela')) {
    /** Cria a tabela de controle. Idempotente. */
    function mig_garante_tabela(PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                filename   VARCHAR(150) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

if (!function_exists('mig_aplicadas')) {
    /** @return string[] nomes de arquivo ja registrados como aplicados */
    function mig_aplicadas(PDO $pdo): array {
        mig_garante_tabela($pdo);
        return $pdo->query("SELECT filename FROM schema_migrations")
                   ->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}

if (!function_exists('mig_pendentes')) {
    /**
     * O que falta aplicar, na ordem correta de versao.
     * @return string[] caminhos absolutos
     */
    function mig_pendentes(PDO $pdo, string $dir): array {
        $aplicadas = mig_aplicadas($pdo);
        $todas = mig_ordenar(glob(rtrim($dir, '/\\') . '/*.sql') ?: []);
        return array_values(array_filter(
            $todas,
            fn($f) => !in_array(basename($f), $aplicadas, true)
        ));
    }
}

if (!function_exists('mig_aplicar')) {
    /**
     * Aplica UMA migration e registra.
     *
     * Erro de "ja existe" e tratado como sucesso silencioso: acontece em site
     * que rodou a alteracao antes da tabela de controle existir. Qualquer outro
     * erro devolve status 'falhou' e NAO registra, pra poder tentar de novo
     * depois de corrigir.
     *
     * @return array{status:string, arquivo:string, erro:?string}
     */
    function mig_aplicar(PDO $pdo, string $arquivo): array {
        $nome = basename($arquivo);
        $benignos = ['already exists', 'duplicate column', 'duplicate key',
                     'duplicate entry', "doesn't exist for"];
        try {
            $pdo->exec((string) file_get_contents($arquivo));
            $status = 'aplicada';
            $erro = null;
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());
            $benigno = false;
            foreach ($benignos as $b) {
                if (strpos($msg, $b) !== false) { $benigno = true; break; }
            }
            if (!$benigno) {
                return ['status' => 'falhou', 'arquivo' => $nome, 'erro' => $e->getMessage()];
            }
            $status = 'ja_presente';
            $erro = null;
        }
        $st = $pdo->prepare("INSERT IGNORE INTO schema_migrations (filename) VALUES (?)");
        $st->execute([$nome]);
        return ['status' => $status, 'arquivo' => $nome, 'erro' => $erro];
    }
}

if (!function_exists('mig_aplicar_pendentes')) {
    /**
     * Aplica tudo o que falta, em ordem, e PARA na primeira falha real
     * (nao continua deixando o banco meio atualizado).
     *
     * @return array{aplicadas:array, ja_presentes:array, falhou:?array}
     */
    function mig_aplicar_pendentes(PDO $pdo, string $dir): array {
        $out = ['aplicadas' => [], 'ja_presentes' => [], 'falhou' => null];
        foreach (mig_pendentes($pdo, $dir) as $arq) {
            $r = mig_aplicar($pdo, $arq);
            if ($r['status'] === 'falhou') { $out['falhou'] = $r; break; }
            if ($r['status'] === 'aplicada') $out['aplicadas'][] = $r['arquivo'];
            else                            $out['ja_presentes'][] = $r['arquivo'];
        }
        return $out;
    }
}
