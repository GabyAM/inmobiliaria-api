<?php
use Respect\Validation\Validator as v;

define('validaciones_localidad', [
    'nombre' => v::notOptional()->stringType()->length(null, 50),
]);

define('mensajes_error_localidad', [
    'nombre' => [
        'notOptional' => 'Este campo es requerido',
        'stringType' => 'Este campo debe ser un string',
        'Length' => 'Este campo debe tener una longitud menor a 50',
    ],
]);
