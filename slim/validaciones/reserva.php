<?php
use Respect\Validation\Validator as v;

define('validaciones_reserva', [
    'propiedad_id' => v::notOptional()->intType(),
    'inquilino_id' => v::notOptional()->intType(),
    'fecha_desde' => v::notOptional()->date(),
    'cantidad_noches' => v::notOptional()->intType(),
]);

define('mensajes_error_reserva', [
    'propiedad_id' => [
        'notOptional' => 'Este campo es requerido',
        'intType' => 'Este campo debe ser un entero',
    ],
    'inquilino_id' => [
        'notOptional' => 'Este campo es requerido',
        'intType' => 'Este campo debe ser un entero',
    ],
    'fecha_desde' => [
        'notOptional' => 'Este campo es requerido',
        'date' => 'Este campo debe ser una fecha en el formato "2024-01-01"',
    ],
    'cantidad_noches' => [
        'notOptional' => 'Este campo es requerido',
        'intType' => 'Este campo debe ser un entero',
    ],
]);
