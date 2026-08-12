<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$from = Illuminate\Support\Carbon::parse('2026-08-11');
$to = $from->copy();
$controller = app(App\Http\Controllers\ReportController::class);
$invoke = static function (string $method, ...$arguments) use ($controller) {
    $reflection = new ReflectionMethod($controller, $method);
    $reflection->setAccessible(true);
    return $reflection->invoke($controller, ...$arguments);
};
$request = new Illuminate\Http\Request(['from' => '2026-08-11', 'to' => '2026-08-11']);
[$appointments] = $invoke('filteredAppointments', $request);
$accessEntries = $invoke('accessEntries', $from, $to);
$hospitalizations = $invoke('hospitalizations', $from, $to);
$hospitalVisits = $invoke('hospitalVisits', $from, $to);

Barryvdh\DomPDF\Facade\Pdf::loadView('auth.reports-pdf', compact('appointments', 'accessEntries', 'hospitalizations', 'hospitalVisits', 'from', 'to') + [
    'metrics' => $invoke('metrics', $appointments),
])->setPaper('a4', 'landscape')->save(__DIR__.'/reporte-diseno.pdf');
