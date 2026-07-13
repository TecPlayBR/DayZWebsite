<?php
// ============================================================
// Mailer - envio de e-mail simples via mail() do PHP.
// ============================================================
// Em hospedagem compartilhada (Hostinger/cPanel), o mail() nativo
// geralmente funciona out-of-the-box. Pra robustez em producao
// recomenda-se SMTP autenticado (Postmark, Sendgrid, Brevo, etc).
//
// Esta classe usa o mail() nativo do PHP. NAO ha caminho SMTP/PHPMailer
// implementado - se precisar de entrega robusta (SMTP autenticado), integrar
// depois. E-mails HTML devem usar estilo INLINE + tabelas (clientes de e-mail
// ignoram <style> do <head> e nao entendem CSS custom properties var(--x)).
// ============================================================

namespace App;

class Mailer {
    private static array $config = [];

    public static function init(array $mailConfig): void {
        self::$config = $mailConfig;
    }

    /**
     * Envia e-mail HTML. Retorna true se enfileirou com sucesso.
     * Em modo dev (sem config), apenas loga em /storage/cache/mail-log.txt
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool {
        $from     = trim((string)(self::$config['from'] ?? ''));
        $fromName = self::$config['from_name'] ?? 'Tecplay';
        $devLog   = self::$config['dev_log_path'] ?? null;

        // Sem 'from' configurado: deriva do dominio do site. mail() em shared
        // hosting (Hostinger) aceita remetente do MESMO dominio; 'no-reply@example.com'
        // (fallback antigo) era rejeitado/spam -> email "sumia". Antes a gente caia
        // em dev-log e NUNCA enviava.
        if ($from === '') {
            $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
            if ($host !== '') {
                $from = 'no-reply@' . $host;
            }
        }

        // Sem from E sem dominio (ex: CLI sem config) -> so loga; senao ENVIA de verdade.
        if ($from === '') {
            if ($devLog) {
                $logEntry = "=====\n[" . date('Y-m-d H:i:s') . "] (sem 'from'/host - nao enviado)\n" .
                            "To: $to\nSubject: $subject\n\n" . ($textBody ?: strip_tags($htmlBody)) . "\n\n";
                @file_put_contents($devLog, $logEntry, FILE_APPEND);
            }
            return false;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=utf-8',
            'From: ' . self::encodeHeader($fromName) . " <$from>",
            'Reply-To: ' . $from,
            'X-Mailer: Tecplay-DayZWebsite/1.0',
        ];
        $encodedSubject = '=?utf-8?B?' . base64_encode($subject) . '?=';

        $ok = @mail($to, $encodedSubject, $htmlBody, implode("\r\n", $headers));
        if (!$ok && $devLog) {
            @file_put_contents($devLog, "=====\n[" . date('Y-m-d H:i:s') . "] [MAIL FALHOU] To: $to | From: $from | Subject: $subject\n\n", FILE_APPEND);
        }
        return $ok;
    }

    private static function encodeHeader(string $value): string {
        if (preg_match('/[^\x20-\x7e]/', $value)) {
            return '=?utf-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    /**
     * Template HTML do recibo de compra (estilo apocalipse).
     */
    public static function purchaseReceiptHtml(array $purchase, array $config): string {
        $siteName = $config['settings']['site_name'] ?? ($config['site_name'] ?? 'TECPLAY');
        $siteUrl  = $config['site_url'] ?? '';
        $coins    = (int)$purchase['coins_total'];
        $bonus    = (int)$purchase['coins_bonus'];
        $price    = (float)$purchase['price_brl'];

        // Paleta FIXA (email nao entende var(--x)). Tema escuro padrao do template.
        $cBg = '#12141a'; $cBox = '#1b1f27'; $cBorder = '#2a2f37'; $cInner = '#0a0c10';
        $cBone = '#e8e2d4'; $cDim = '#8a8f98'; $cRust = '#b23a2e'; $cHazard = '#c9a961';
        $cMoss = '#7fae63'; $cLink = '#d98b84';
        $esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        // Uma linha do recibo (tabela = renderiza em qualquer cliente, ate Outlook).
        $row = static function (string $label, string $valueHtml, bool $last = false) use ($cDim, $cBone, $cBorder) {
            $bb = $last ? '' : "border-bottom:1px solid {$cBorder};";
            return '<tr>'
                . '<td style="padding:8px 0;' . $bb . 'color:' . $cDim . ';font-size:12px;text-transform:uppercase;letter-spacing:0.08em;">' . $label . '</td>'
                . '<td style="padding:8px 0;' . $bb . 'color:' . $cBone . ';font-family:monospace,monospace;font-size:14px;text-align:right;">' . $valueHtml . '</td>'
                . '</tr>';
        };

        $moedasVal = (string)$coins;
        if ($bonus > 0) $moedasVal .= ' <span style="color:' . $cMoss . ';">(+' . $bonus . ' bônus)</span>';
        $totalVal = '<span style="color:' . $cHazard . ';font-size:20px;font-weight:bold;">R$ ' . number_format($price, 2, ',', '.') . '</span>';

        $base = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;background:' . $cBg . ';">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $cBg . ';padding:20px 0;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:' . $cBox . ';border:1px solid ' . $cBorder . ';">'
            . '<tr><td style="padding:30px;font-family:Arial,Helvetica,sans-serif;">'
            . '<h1 style="color:' . $cRust . ';font-size:22px;letter-spacing:0.05em;margin:0 0 20px;text-transform:uppercase;">Pagamento confirmado</h1>'
            . '<p style="color:' . $cBone . ';font-size:14px;line-height:1.6;margin:0 0 20px;">Suas moedas foram <strong>creditadas no servidor</strong>. Se ainda não apareceram no jogo, faça <strong>relog</strong> (desconectar e conectar).</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $cInner . ';border-left:3px solid ' . $cHazard . ';margin:0 0 20px;">'
            . '<tr><td style="padding:16px 20px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
        $base .= $row('Pacote', $esc($purchase['package_id']));
        $base .= $row('SteamID', $esc($purchase['steam_id']));
        $base .= $row('Moedas creditadas', $moedasVal);
        $base .= $row('Total pago', $totalVal, empty($purchase['mp_payment_id']));
        if (!empty($purchase['mp_payment_id'])) {
            $base .= $row('ID Mercado Pago', $esc($purchase['mp_payment_id']), true);
        }
        $base .= '</table></td></tr></table>';
        if ($siteUrl) {
            $base .= '<p style="text-align:center;margin:0 0 20px;"><a href="' . $esc($siteUrl) . '/my-purchases" style="display:inline-block;background:' . $cRust . ';color:#ffffff;padding:12px 28px;text-decoration:none;letter-spacing:0.05em;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;">Ver minhas compras</a></p>';
        }
        $support = ($config['settings']['social_discord'] ?? '') ?: ($config['settings']['discord_invite'] ?? '');
        $supportHtml = $support !== '' ? $esc($support) : '<a href="https://discord.gg/uwSE3WSjNH" style="color:' . $cLink . ';">Discord Tecplay</a>';
        $base .= '<p style="color:' . $cDim . ';font-size:12px;text-align:center;margin:0;line-height:1.6;">'
            . 'Este é um e-mail automático de <strong style="color:' . $cBone . ';">' . $esc($siteName) . '</strong>. Não responda.<br>'
            . 'Suporte: ' . $supportHtml
            . '</p>'
            . '</td></tr></table></td></tr></table></body></html>';
        return $base;
    }
}
