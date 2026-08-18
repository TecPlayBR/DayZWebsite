# 🎮 Vender moeda no site e o jogador receber dentro do jogo

> Este guia é pra quem usa a **loja da FlameHost** no servidor (os mods de loja e Painel VIP).
> Depois de configurar, o jogador compra moeda no seu site e ela aparece **dentro do jogo**,
> pronta pra gastar na loja de lá. Tempo: uns 5 minutos. Não precisa saber programar.

---

## Como funciona, em uma frase

O jogador compra no site, a moeda entra **no servidor**, e o número que ele vê no site é o
mesmo que ele tem no jogo. Quando ele compra um item dentro do jogo, o saldo do site
acompanha a redução sozinho.

---

## Antes de começar

Você vai precisar de duas coisas:

1. **Os dados de FTP do seu servidor de jogo.** A sua hospedagem mostra isso: normalmente em
   *FTP* ou *Acesso a arquivos*, no painel dela. São quatro campos: endereço (host), porta,
   usuário e senha.
2. **O bot conectado ao seu site.** Se você já usa o bot com o site, isso já está feito.

⚠️ **A porta quase sempre é `8821`, não 21.** É o erro mais comum de quem preenche na mão.

---

## Passo 1 · Ligar a entrega

No painel do bot, abra a aba **Integração** e vá até **🔥 Entrega in-game na FlameHost**.

Preencha host, porta, usuário e senha, e clique em **Testar e ligar**.

Você **não precisa** saber onde fica a pasta do servidor: eu procuro sozinho. O campo
*Caminho da pasta do servidor* só existe pra quando eu avisar que não achei.

**Se der certo**, aparece a pasta que eu encontrei e o selo muda pra **ligado**.

**Se não der**, a mensagem diz o que fazer. As mais comuns:

| Mensagem | O que fazer |
|---|---|
| "o servidor não respondeu" | quase sempre é a porta: tente **8821** |
| "usuário ou senha recusados" | confira na aba de FTP do painel da sua hospedagem |
| "não achei a pasta com Shop e PainelVip" | abra o gerenciador de arquivos da hospedagem, ache a pasta que tem `Shop` e `PainelVip` dentro dela, e cole o caminho no campo |

---

## Passo 2 · Ligar a entrega da compra feita no site

Logo abaixo, marque **Entregar in-game a moeda comprada no site** e salve.

⚠️ **Leia antes de marcar.** Ao ligar, a moeda passa a morar no servidor de jogo. Isso muda
duas coisas no seu site:

- **Gastar moeda no site sai do ar.** As caixas e a compra de VIP com moeda deixam de
  aparecer, porque o gasto tem que acontecer onde a moeda está. Se a sua loja do site só
  vende **pacote de moeda**, nada muda pra você.
- **Se você já tem jogadores com saldo no site**, esse saldo some da vista, porque o número
  que passa a valer é o do servidor. O painel avisa quantos jogadores e quanto, antes de
  ligar. Se for o seu caso, fale com o suporte antes de anunciar pros jogadores.

Desligar volta tudo ao normal sozinho, sem precisar mexer em mais nada.

---

## Passo 3 · Conferir

Faça uma compra pequena no seu site e entre no jogo. A moeda tem que estar lá.

Se não estiver, a mesma aba mostra a fila de entrega:

- **entregues** — já foram pro servidor
- **na fila** — ainda vão. **Jogador que nunca entrou no servidor conta aqui**, e é normal:
  o mod só cria o arquivo dele no primeiro acesso, e a compra sai nesse momento
- **travadas** — não saíram depois de muitas tentativas. Aí vale falar com o suporte

Nada se perde: eu tento de novo a cada 2 minutos.

---

## Perguntas que aparecem sempre

**A minha senha de FTP fica guardada onde?**
Cifrada, e ela nunca é exibida de volta pra tela. Pra trocar, é só digitar a nova; pra manter
a atual, deixe o campo em branco. Você pode remover a credencial quando quiser, e aí a
integração desliga.

**O jogador comprou e não apareceu no jogo. E agora?**
Se ele nunca entrou no servidor, é esperado: a compra fica na fila e sai no primeiro acesso
dele. Se ele já joga, veja se aparece em **travadas**.

**Eu dei moeda pelo painel do site e sumiu.**
Isso foi corrigido. Hoje o ajuste feito no painel vai direto pro servidor. Se o servidor não
puder ser alcançado na hora, o painel avisa e **não grava nada**, em vez de mostrar um valor
que não existe no jogo.

**Posso usar isso e o mod Sparda ao mesmo tempo?**
Não faz sentido, e não deve. Cada um tem a sua forma de guardar o saldo, e usar os dois faria
o mesmo jogador ter dois saldos diferentes.

**Preciso reiniciar o servidor?**
Não. A moeda é escrita no arquivo do jogador e o mod lê de lá.