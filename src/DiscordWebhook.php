<?php
// ============================================================
// DiscordWebhook - posta embed rico num canal Discord via webhook.
// ============================================================
// Cliente cria o webhook em: Discord → Servidor → Configurações →
// Integrações → Webhooks → "Novo Webhook". Copia a URL e cola
// em Admin → Settings → discord_sales_webhook.
// ============================================================

namespace App;

class DiscordWebhook {

    /**
     * Posta uma venda aprovada no canal Discord. Falha silenciosa.
     * Estrutura: https://discord.com/developers/docs/resources/webhook#execute-webhook
     */
    public static function notifySale(string $webhookUrl, array $purchase, array $config): bool {
        if (empty($webhookUrl) || !preg_match('#^https://(discord|discordapp)\.com/api/webhooks/#', $webhookUrl)) {
            return false;
        }

        $siteName = $config['settings']['site_name'] ?? ($config['site_name'] ?? 'TECPLAY');
        $coinsTotal = (int)$purchase['coins_total'];
        $coinsBonus = (int)$purchase['coins_bonus'];
        $price = number_format((float)$purchase['price_brl'], 2, ',', '.');
        $steamId = $purchase['steam_id'];
        $packageId = strtoupper($purchase['package_id']);

        // Tenta enriquecer com display_name + avatar do player (se ja conhecemos)
        $playerName = null;
        $playerAvatar = null;
        try {
            $row = Database::fetchOne(
                "SELECT display_name FROM players WHERE steam_id = ? LIMIT 1",
                [$steamId]
            );
            $playerName = $row['display_name'] ?? null;
        } catch (\Throwable $e) {}

        $description  = "**" . ($playerName ?? "Jogador `$steamId`") . "** acaba de comprar o pacote **$packageId**.\n";
        $description .= "💰 **$coinsTotal moedas** ";
        if ($coinsBonus > 0) $description .= "(+$coinsBonus bônus) ";
        $description .= "creditadas no servidor.";

        $payload = [
            'username' => $siteName . ' — Vendas',
            'embeds' => [[
                'title'       => '🎮 Nova compra aprovada',
                'description' => $description,
                'color'       => 0xc1440e, // var(--rust) em decimal
                'fields'      => [
                    ['name' => 'Pacote',  'value' => $packageId,        'inline' => true],
                    ['name' => 'Valor',   'value' => 'R$ ' . $price,    'inline' => true],
                    ['name' => 'SteamID', 'value' => '`' . $steamId . '`', 'inline' => false],
                ],
                'footer'    => ['text' => $siteName . ' · powered by Tecplay'],
                'timestamp' => date('c'),
            ]],
        ];

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            error_log("DiscordWebhook HTTP $code: $resp");
            return false;
        }
        return true;
    }
}
