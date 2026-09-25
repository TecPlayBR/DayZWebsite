-- Redes sociais do streamer: a coluna que o codigo grava desde julho (botoes YouTube,
-- Twitch, Kick, Discord, Instagram, TikTok, X, Facebook na pagina do streamer) mas que
-- nenhuma migration criava. Sem ela, TODO cadastro de streamer falhava no INSERT e o
-- admin voltava pro formulario vazio sem mensagem. Encontrado num cliente em 24/09/2026.
ALTER TABLE streamers ADD COLUMN IF NOT EXISTS socials_json TEXT NULL AFTER video_urls_json;
