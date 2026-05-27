<?php
// ============================================================
// Mailer - envio de e-mail simples via mail() do PHP.
// ============================================================
// Em hospedagem compartilhada (Hostinger/cPanel), o mail() nativo
// geralmente funciona out-of-the-box. Pra robustez em producao
// recomenda-se SMTP autenticado (Postmark, Sendgrid, Brevo, etc).
//
// Esta classe usa mail() por padrao. Se a config tiver SMTP, usa
// PHPMailer (se disponivel) — caso contrario, fallback pra mail().
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
        $from     = self::$config['from']      ?? 'no-reply@example.com';
        $fromName = self::$config['from_name'] ?? 'Tecplay';
        $devLog   = self::$config['dev_log_path'] ?? null;

        // Em dev (sem from configurado): log em arquivo
        if (empty(self::$config['from']) && $devLog) {
            $logEntry = "=====\n[" . date('Y-m-d H:i:s') . "] " .
                        "To: $to\nSubject: $subject\n\n" .
                        ($textBody ?: strip_tags($htmlBody)) . "\n\n";
            @file_put_contents($devLog, $logEntry, FILE_APPEND);
            return true;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=utf-8',
            'From: ' . self::encodeHeader($fromName) . " <$from>",
            'Reply-To: ' . $from,
            'X-Mailer: Tecplay-DayZWebsite/1.0',
        ];

        $encodedSubject = '=?utf-8?B?' . base64_encode($subject) . '?=';

        return @mail($to, $encodedSubject, $htmlBody, implode("\r\n", $headers));
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

        $base = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">';
        $base .= '<style>
            body { background: #0d1014; color: #d4c5a9; font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .box { max-width: 560px; margin: 0 auto; background: #161a20; border: 1px solid #2a2f37; padding: 30px; }
            h1 { color: #c1440e; font-size: 22px; letter-spacing: 0.05em; margin: 0 0 20px; text-transform: uppercase; }
            .receipt { background: #0a0c10; padding: 20px; border-left: 3px solid #d4a017; margin: 20px 0; }
            .receipt-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #2a2f37; }
            .receipt-row:last-child { border-bottom: none; }
            .receipt-label { color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; }
            .receipt-value { color: #d4c5a9; font-family: monospace; font-size: 14px; }
            .total { font-size: 20px; color: #d4a017; font-weight: bold; }
            .footer { color: #6b7280; font-size: 12px; text-align: center; margin-top: 20px; }
            a { color: #e74c3c; }
        </style></head><body>';
        $base .= '<div class="box">';
        $base .= "<h1>Pagamento confirmado</h1>";
        $base .= "<p>Suas moedas foram <strong>creditadas no servidor</strong>. Se ainda não apareceram no jogo, faça <strong>relog</strong> (desconectar + conectar).</p>";
        $base .= '<div class="receipt">';
        $base .= '<div class="receipt-row"><span class="receipt-label">Pacote</span><span class="receipt-value">' . htmlspecialchars($purchase['package_id']) . '</span></div>';
        $base .= '<div class="receipt-row"><span class="receipt-label">SteamID</span><span class="receipt-value">' . htmlspecialchars($purchase['steam_id']) . '</span></div>';
        $base .= '<div class="receipt-row"><span class="receipt-label">Moedas creditadas</span><span class="receipt-value">' . $coins;
        if ($bonus > 0) $base .= ' <small style="color:#5a6c4e;">(+' . $bonus . ' bônus)</small>';
        $base .= '</span></div>';
        $base .= '<div class="receipt-row"><span class="receipt-label">Total pago</span><span class="receipt-value total">R$ ' . number_format($price, 2, ',', '.') . '</span></div>';
        if (!empty($purchase['mp_payment_id'])) {
            $base .= '<div class="receipt-row"><span class="receipt-label">ID Mercado Pago</span><span class="receipt-value">' . htmlspecialchars($purchase['mp_payment_id']) . '</span></div>';
        }
        $base .= '</div>';
        if ($siteUrl) {
            $base .= '<p style="text-align:center;"><a href="' . htmlspecialchars($siteUrl) . '/my-purchases" style="display:inline-block; background:#c1440e; color:#fff; padding:10px 24px; text-decoration:none; letter-spacing:0.05em;">Ver minhas compras</a></p>';
        }
        $base .= '<div class="footer">';
        $base .= 'Este é um e-mail automático de <strong>' . htmlspecialchars($siteName) . '</strong>. Não responda.<br>';
        $base .= 'Suporte: ' . ($config['settings']['discord_invite'] ?? '<a href="https://discord.gg/uwSE3WSjNH">Discord Tecplay</a>');
        $base .= '</div>';
        $base .= '</div></body></html>';
        return $base;
    }
}
