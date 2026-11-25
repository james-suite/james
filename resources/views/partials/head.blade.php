@php
    $routeTitle = match (true) {
        request()->routeIs('dashboard') => 'Dashboard',
        request()->routeIs('contacts.groups.*') => 'Grupos de Contatos',
        request()->routeIs('contacts.*') => 'Contatos',
        request()->routeIs('financial.dashboard') => 'Dashboard Financeiro',
        request()->routeIs('financial.transactions.*') => 'Transações',
        request()->routeIs('financial.recurrences.*') => 'Recorrências',
        request()->routeIs('financial.reports*') => 'Relatórios',
        request()->routeIs('financial.accounts.*') => 'Contas Financeiras',
        request()->routeIs('financial.cards.invoices.*') => 'Fatura de Cartão',
        request()->routeIs('financial.cards.*') => 'Cartões de Crédito',
        request()->routeIs('financial.tags.*') => 'Tags',
        request()->routeIs('settlements.groups.*') => 'Contas Divididas',
        request()->routeIs('settlements.*') => 'Acertos',
        request()->routeIs('notifications.*') => 'Notificações',
        request()->routeIs('audit.*') => 'Logs do Sistema',
        request()->routeIs('settings') => 'Configurações',
        request()->routeIs('login') => 'Entrar',
        default => null,
    };
    $resolvedTitle = $title ?? $routeTitle;
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>{{ $resolvedTitle ? $resolvedTitle . ' — ' . config('app.name') : config('app.name') }}</title>

<link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="James" />
<link rel="manifest" href="/site.webmanifest" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
