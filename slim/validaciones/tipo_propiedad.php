<?php
use Respect\Validation\Validator as v;

define('validaciones_tipo_propiedad', [
    'nombre' => v::notOptional()->stringType()->length(null, 50),
]);

define('mensajes_error_tipo_propiedad', [
    'nombre' => [
        'notOptional' => 'Este campo es requerido',
        'stringType' => 'Este campo debe ser un string',
        'Length' => 'Este campo debe tener una longitud menor a 50',
    ],
]);
