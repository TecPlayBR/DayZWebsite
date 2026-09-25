# Proteção de menores (ECA Digital): o que muda no seu site e o que você precisa fazer

A Lei 15.211/2025 (Estatuto Digital da Criança e do Adolescente, a "Lei Felca") está em vigor
desde 17/03/2026. Para sites de servidor de jogo, o ponto que pega é o **art. 20: caixas de
recompensa (loot boxes) não podem ser oferecidas a menores**, e o art. 9º diz que a verificação
de idade tem que ser confiável: **autodeclaração não vale**. A ANPD fiscaliza a partir de
janeiro de 2027; a multa vai a percentual do faturamento ou até R$ 50 milhões por infração.

A versão 3.3.0 do template resolve isso sem custo obrigatório. Este guia diz o que acontece
depois de atualizar e o que você decide.

## 1. Atualize e rode a migration

Suba os arquivos da versão nova e abra `/update.php` (ou rode `php cli/migrate.php`). A migration
`v3.3.0_eca_digital.sql` cria as tabelas `age_verifications` e `consents`, as colunas
`players.age_status` e `players.age_verified_at`, e as configurações novas. Ela é idempotente:
pode rodar duas vezes sem problema. **Rode a migration logo depois de subir os arquivos**: no
intervalo entre uma coisa e outra, a loja manda o jogador para uma tela que ainda não tem tabela.

## 2. O que muda para o jogador, na hora

- **Caixas fechadas por padrão.** Só abrem para jogador com idade **verificada por CPF** numa
  fonte externa. Enquanto você não configurar um fornecedor, ninguém abre caixa. Isso é
  proposital: o Decreto 12.880/2026, art. 23 § 1º, diz que caixa restrita por padrão
  **dispensa a verificação de idade**. Ou seja, nesse estado o seu site **já está conforme**,
  sem pagar nada a ninguém.
- **Comprar moeda exige conta Steam, data de nascimento (18+) e aceite real dos Termos.** O
  campo oculto que "aceitava" os Termos sozinho foi removido; agora fica registrado quando,
  de qual IP e qual versão do texto o jogador aceitou. Comprar só digitando o SteamID, sem
  login, deixou de existir.
- Quem se declara menor continua jogando, aparecendo no ranking e entrando em clã. Compras e
  caixas ficam fechadas, com explicação na tela.
- Tudo isso acontece na página `/idade`, que explica ao jogador o que é guardado e por quê.

## 3. Se você quer vender caixas: escolha um fornecedor

Em **Configurações → Proteção de menores (ECA Digital)** você escolhe o fornecedor que
confere o CPF, cola a chave, salva e clica **Testar chave** na tela **Conformidade ECA**.

| Fornecedor | Custo | Como conseguir a chave |
|---|---|---|
| **CPFHub** (padrão) | grátis até 50 consultas por mês; R$ 149/mês acima disso | conta em cpfhub.io, sem cartão; a chave sai na hora em "Chaves de API" |
| **Serpro** (a própria Receita Federal) | centavos por consulta | contrato com CNPJ na Loja Serpro (Consulta CPF v3); consumer key e secret |
| **FlagCheck** | sob consulta | e-mail para api@flagcheck.com.br, onboarding em 2-3 dias úteis |

**Cada jogador verifica uma única vez.** Um servidor com 30 jogadores novos por mês gasta 30
das 50 consultas grátis do CPFHub. O botão Testar chave usa o `/quota` do CPFHub: não gasta
consulta e mostra quantas restam.

Os três modos do painel:

- **Verificado**: loja com declaração e aceite; caixas só com CPF verificado. É o modo conforme.
- **Declaração** (o site nasce assim): igual ao Verificado, com um aviso amarelo no painel
  enquanto a chave do fornecedor não existe.
- **Desligado**: caixas abrem sem verificação. A loja continua pedindo idade e aceite. O
  painel mostra um aviso vermelho fixo de "não conforme". Só use se assumir o risco.

A caixa diária grátis também exige verificação por padrão. Dá pra liberar na mesma tela.

## 4. O que fica guardado (e o que nunca fica)

O CPF **nunca é gravado**: ele vai ao fornecedor na mesma requisição e é descartado. O que
fica é o mínimo que a ANPD pede para auditoria: resultado (adulto ou menor), método, data, IP,
a referência de auditoria do fornecedor e um **hash irreversível** do CPF com um sal próprio
do seu site. Esse hash serve só para uma coisa: **um CPF verifica uma única conta** no seu site,
o que fecha o caso do adulto que verifica várias contas de menores.

Em **Conformidade ECA** você vê contadores, as últimas verificações (só metadados), exporta CSV,
revoga uma verificação com motivo, e imprime o **relatório de conformidade**, que descreve o
mecanismo ponto a ponto contra os 11 requisitos do art. 24 do Decreto. É o documento que se
mostra se a ANPD perguntar.

## 5. Atualize seus Termos de Uso e a Política de Privacidade

O template não sobrescreve as páginas legais do seu site, porque você pode ter editado. Cole
o trecho abaixo nas duas páginas (Páginas, no painel), e leve ao seu advogado se quiser
ajustar o texto:

> **Verificação de idade.** Para cumprir a Lei 15.211/2025 (ECA Digital), a compra de moeda
> neste site exige login, declaração de data de nascimento e aceite destes Termos; a abertura
> de caixas de recompensa exige verificação de idade por CPF junto a um fornecedor externo
> ([nome do fornecedor escolhido]). O CPF é usado apenas durante a verificação e não é
> armazenado. Guardamos o resultado da verificação, a data, o método, o endereço IP e um
> identificador irreversível derivado do CPF, usado exclusivamente para impedir que um mesmo
> CPF verifique mais de uma conta. Esses dados são tratados com base no cumprimento de
> obrigação legal (LGPD, art. 7º, II) e não são usados para nenhuma outra finalidade. Menores
> de 18 anos não podem comprar nem abrir caixas de recompensa.

## 6. Se você usa o bot do Discord

O bot passa a receber `age_required` (HTTP 403) ao tentar criar uma cobrança para jogador
que ainda não declarou idade. A versão do bot que responde com o link `/idade` sai em separado.

## 7. Dúvidas frequentes

**Posso continuar com as caixas abertas como antes?** Só no modo Desligado, e aí você está
fora da lei. O template deixa, mas avisa.

**O jogador errou a data e virou "menor". E agora?** Ele mesmo resolve: faz a verificação por
CPF em `/idade`. Declarar de novo não reabre, de propósito.

**Preciso guardar o CPF para provar alguma coisa?** Não. A ANPD recomenda exatamente o
contrário: guardar só o resultado, a data e o método. É o que o template faz.

**O fornecedor caiu ou os créditos acabaram.** O painel mostra um aviso vermelho quando
há 3 ou mais falhas em 24 horas. Enquanto isso, ninguém abre caixa; o resto do site segue.
