-- ============================================================
-- v2.0.1 - origin 'reward' e 'box' no players
-- ============================================================
-- A premiação do leaderboard cria player novo com origin='reward' quando o
-- vencedor ainda não tem linha. Sem esse valor no ENUM o INSERT falharia.
-- Adiciona 'reward' e 'box' (provenância de moeda por premiação / caixa).
-- Seguro: só ADICIONA valores ao ENUM (não remove; dados existentes intactos).
-- ============================================================

ALTER TABLE players
    MODIFY origin ENUM('agent','panel','payment','manual','bot','reward','box') NOT NULL DEFAULT 'agent';
