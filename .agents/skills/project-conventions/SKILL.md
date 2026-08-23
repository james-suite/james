---
name: project-conventions
description: Use this skill for general architectural decisions and coding conventions in this project. Activate when creating views, refactoring code, or registering components.
---

# Project Conventions

Sempre siga essas regras ao programar no James. Elas definem decisões arquiteturais e de design específicas deste projeto. Se necessário, consulte arquivos adicionais na pasta `references/`.

## Frontend e Blade

1. **Componentes Blade (Sem Alias):** O Laravel já resolve os componentes anonimamente baseando-se no nome e nas pastas. NÃO use aliases como `ui.` ou `form.`. (Incorreto: `<x-ui.avatar>`, Correto: `<x-avatar>`).
2. **Diretivas do AlpineJS:** Sempre coloque os atributos do Alpine (`x-data`, `x-show`, `@click`, etc.) no final das tags HTML, obrigatoriamente APÓS `class` e `style`, para não quebrar o syntax highlighting da IDE.
3. **Helpers Globais para Views:** NUNCA formate dados brutos manualmente nas views. Use as funções injetadas pelos Helpers (`formatShort()`, `formatDateTime()`, `formatMonthYear()`, `formatCurrency()`). Apenas em `<input type="date">` é permitido o `->format('Y-m-d')`.
4. **Textos e Traduções:** Textos nas views Blade devem ser injetados diretamente em português (Hardcoded). Não utilize o sistema de tradução (`__('texto')`), dado que o escopo é local.
5. **TailwindCSS (Design System do James):**
   - Use cores semânticas (`text-neutral-500`, `bg-primary-600`) e sintaxe canônica v4 (`text-white!`).
   - É **proibido** o uso de valores arbitrários (ex: `w-[15px]`, `bg-[#333]`). Exceção permitida: definições de grid (ex: `grid-cols-[1fr_auto_1fr]`).
   - Para textos menores que `text-xs`, utilize a classe customizada `text-xxs` (10px).
6. **Ícones:** Utilize a biblioteca Heroicons (`<x-heroicon-...>`) por padrão. Para itens específicos, utilize pacotes do ecossistema Blade Icons (Tabler, Phosphor). Não adicione SVGs soltos nas views.

## UI e UX

7. **Consistência e Reutilização:** Priorize o uso dos componentes existentes (`<x-card>`, `<x-button>`, `<x-page-header>`). Siga a identidade visual das telas similares.
8. **Responsividade (Desktop-First):** O foco principal é **Desktop-First**. O mobile foca em cadastro e visualizações essenciais. **Consulte:** `references/mobile-ui.md`.
9. **Feedback Visual:** Ações assíncronas DEVEM possuir *loading states* e desabilitar botões temporariamente para evitar cliques duplos.
10. **Botões no Cabeçalho (Page Header):**
    - 1 botão: Ocupa 100% no mobile (`w-full sm:w-auto`).
    - 2 botões: Cada um ocupa 50% no mobile (`flex-1 sm:flex-initial`).
    - 3+ botões: O mais à direita estica (`flex-1`) e os demais usam tamanho do conteúdo. Botão "Voltar" sempre visível.
11. **Altura de Inputs (Princípio dos 44px):** Inputs grandes (texto, data, select) devem ter 44px usando `h-11` (ou `min-h-11`).
12. **Tabelas e Responsividade:** NÃO utilize scroll horizontal (`overflow-x-auto`). Toda tabela DEVE ter uma versão para celular usando `<x-slot:mobile>`, transformando linhas em cards verticais.

## Backend e Arquitetura

13. **Lógica de Negócio nos Controllers:** A lógica principal deve ser mantida nos Controllers. Não extraia para Actions/Services genéricas a menos que a complexidade justifique.
14. **Auditoria (Spatie Activitylog):** Aplicada em todos os models de negócio com retenção vitalícia (nunca agende `activitylog:clean` nem delete registros).
   - Use mass-assignment clássico `protected $fillable = [...]` e `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()`.
   - **Regra de SoftDeletes:** Em `protected static array $recordEvents`, só inclua `'restored'` e `'forceDeleted'` se a model utilizar a trait `SoftDeletes` (evita loop no boot do Laravel).
   - No frontend, exiba logs com `causer_id` nulo como "Sistema / Rotina Automática".
15. **Soft Deletes Obrigatório:** Todos os Models de negócio DEVEM utilizar `SoftDeletes` por padrão (exceto pivôs puros ou dados temporários como cache/tokens).

## Testes

16. **Testes focados em funcionalidade:** Testes devem validar regras de negócio, persistência, autorização, validação, integrações e respostas funcionais. Não crie testes para CSS, classes Tailwind, estrutura Blade, textos apresentados, atributos HTML ou detalhes de implementação visual. Testes de segurança devem validar o efeito funcional (por exemplo, acesso negado ou conteúdo perigoso não processado), sem depender de assertions sobre markup.

## Workflow e Ferramentas

17. **Documentação:** Quando solicitada a criação ou atualização de documentação técnica, salve sempre dentro do diretório `/docs`.
18. **Controle de Versão (Git):** NUNCA execute comandos de commit (`git commit`) automaticamente sem autorização explícita para aquela tarefa específica. Quando autorizado, utilize mensagens em inglês no padrão *Conventional Commits* (`feat:`, `fix:`, `refactor:`).
19. **Evolução Contínua:** Sugira proativamente novas regras ao identificar padrões repetitivos de design ou código combinados durante o desenvolvimento.
