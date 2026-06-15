<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// Alias do webhook do Mercado Pago.
// ============================================================
// O handler REAL e o mp-webhook.php. Mas alguns paineis do Mercado Pago ficam
// apontados pra /api/webhook.php (URL antiga/padrao). Este alias garante que
// AMBAS as URLs funcionem -> nunca um webhook morto por causa da URL no painel.
// (O ideal e apontar o painel do MP pra /api/mp-webhook.php; este alias e a rede
// de seguranca.)
// ============================================================
require __DIR__ . '/mp-webhook.php';
