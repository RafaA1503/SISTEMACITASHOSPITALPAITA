<?php

return [
    // El paciente se considera inasistente al vencer esta tolerancia, salvo
    // que Portería haya registrado previamente su llegada.
    'attendance_grace_minutes' => (int) env('ATTENDANCE_GRACE_MINUTES', 20),
];
