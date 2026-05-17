# Acessibilidade e UX

## Semântica

- Use elementos HTML semânticos antes de `div` clicável.
- Botões acionáveis devem ser `button`; navegação deve ser link/rota quando apropriado.
- Ícones sem texto precisam de nome acessível (`aria-label`, texto visual oculto ou tooltip acessível).

## Teclado e foco

- Fluxos essenciais devem funcionar por teclado.
- Estados modais, menus e drawers devem controlar foco e retorno de foco.
- Não remova outline sem substituição visível.

## Feedback

- Toda ação assíncrona deve ter estado de loading/desabilitação quando houver risco de duplo envio.
- Erros devem indicar o que ocorreu e como tentar novamente.
- Estados vazios devem orientar a próxima ação real, sem explicar detalhes internos da implementação.

## Conteúdo

- Use português brasileiro formal no texto de interface, salvo termos técnicos ou nomes de produto.
- Evite truncar informação crítica como nomes, telefones, status de cobrança e mensagens de atendimento.
- Garanta contraste adequado e legibilidade em temas suportados.
