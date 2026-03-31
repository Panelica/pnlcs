<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command("pnlcs:generate-invoices")->daily()->at("06:00");
Schedule::command("pnlcs:mark-overdue")->daily()->at("06:30");
Schedule::command("pnlcs:auto-suspend")->daily()->at("07:00");
Schedule::command("pnlcs:domain-sync")->daily()->at("03:00");
