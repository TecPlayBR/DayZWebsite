<?php
// ============================================================
// Mercado Pago - cliente HTTP minimalista (sem SDK / composer).
// Suporta: criar preference, consultar payment, validar webhook.
// ============================================================
// Docs: https://www.mercadopago.com.br/developers/pt/reference/preferences/_checkout_preferences/post
// ============================================================

namespace App;

class MercadoPago {
    private string $accessToken;
    private ?string $webhookSecret;
    private string $baseUrl = 'https://api.mercadopago.com';

    public function __construct(string $accessToken, ?string $webhookSecret = null) {
        $this->accessToken   = $accessToken;
        $this->webhookSecret = $webhookSecret ?: null;
    }

    public function isConfigured(): bool {
        return $this->accessToken !== '' && $this->accessToken !== '<MP_ACCESS_TOKEN>';
    }

    /**
     * Cria uma preference de checkout. Retorna ['init_point' => '...', 'id' => '...'] ou null em erro.
     * Mais info: https://www.mercadopago.com.br/developers/pt/reference/preferences/_checkout_preferences/post
     */
    public function createPreference(array $payload): ?array {
        $resp = $this->request('POST', '/checkout/preferences', $payload);
        if (!$resp || empty($resp['id'])) return null;
        return $resp;
    }

    /**
     * Cria um pagamento Pix DIRETO (sem checkout web). Retorna o payment do MP,
     * incluindo point_of_interaction.transaction_data (qr_code copia-e-cola,
     * qr_code_base64 PNG, ticket_url). null em erro.
     * $payload deve conter: transaction_amount, description, external_reference,
     * payer.email, notification_url e (opcional) date_of_expiration.
     * Usado pelo /comprar do Discord (bot-integration.php ?action=create_pix).
     */
    public function createPixPayment(array $payload): ?array {
        $payload['payment_method_id'] = 'pix';
        // Idempotência: evita duplicar cobrança se a chamada repetir.
        $idem = 'pix-' . ($payload['external_reference'] ?? '') . '-' . bin2hex(random_bytes(6));
        $resp = $this->request('POST', '/v1/payments', $payload, ['X-Idempotency-Key: ' . $idem]);
        if (!$resp || empty($resp['id'])) return null;
        return $resp;
    }

    /**
     * Busca um payment pelo ID (apos receber webhook).
     */
    public function getPayment(string $paymentId): ?array {
        return $this->request('GET', "/v1/payments/$paymentId");
    }

    /**
     * Valida assinatura HMAC do webhook (se webhook_secret configurado).
     * Header: X-Signature  ex: "ts=1700000000,v1=abc123..."
     * Payload: x-request-id, data.id, ts
     * Formula:  HMAC-SHA256(secret, "id:{data.id};request-id:{x-request-id};ts:{ts};")
     */
    public function verifyWebhookSignature(string $xSignature, string $xRequestId, string $dataId): bool {
        if (!$this->webhookSecret) return true; // sem secret = sem validacao (modo dev)
        // Parse "ts=X,v1=Y"
        $parts = [];
        foreach (explode(',', $xSignature) as $segment) {
            $kv = explode('=', trim($segment), 2);
            if (count($kv) === 2) $parts[$kv[0]] = $kv[1];
        }
        if (empty($parts['ts']) || empty($parts['v1'])) return false;
        $manifest = "id:$dataId;request-id:$xRequestId;ts:{$parts['ts']};";
        $expected = hash_hmac('sha256', $manifest, $this->webhookSecret);
        return hash_equals($expected, $parts['v1']);
    }

    private function request(string $method, string $path, ?array $body = null, ?array $extraHeaders = null): ?array {
        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ];
        if ($extraHeaders) {
            $headers = array_merge($headers, $extraHeaders);
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($err) {
            error_log("MP request error: $err");
            return null;
        }
        if ($code >= 400) {
            // Loga so codigo+path. A resposta do MP pode conter dados do pagador
            // (PII) / detalhes sensiveis -> NAO vai pro error_log.
            error_log("MP $method $path HTTP $code (corpo omitido)");
            return null;
        }
        $decoded = json_decode($resp, true);
        return is_array($decoded) ? $decoded : null;
    }
}
