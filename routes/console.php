<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('finance:rollover-invoices')->daily();
Schedule::command('finance:rollover-transactions')->daily();
Schedule::command('finance:process-recurrences')->daily()->withoutOverlapping()->onOneServer();
Schedule::command('finance:due-today-alerts')->dailyAt('08:00')->withoutOverlapping()->onOneServer();
Schedule::command('finance:monthly-digest')->monthlyOn(1, '09:00')->withoutOverlapping()->onOneServer();
