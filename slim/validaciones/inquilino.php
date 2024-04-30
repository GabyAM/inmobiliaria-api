<?php
use Respect\Validation\Validator as v;

define('validaciones_inquilino', [
    'documento' => v::notOptional()->stringType()->length(null, 20),
    'apellido' => v::notOptional()->stringType()->length(null, 15),
    'nombre' => v::notOptional()->stringType()->length(null, 25),
    'email' => v::notOptional()->email()->length(null, 20),
    'activo' => v::notOptional()->boolType(),
]);

define('mensajes_error_inquilino', [
    'documento' => [
        'notOptional' => 'Este campo es requerido',
        'stringType' => 'Este campo debe ser un string',
        'length' => 'Este campo debe tener una longitud menor o igual a 20',
    ],
    'apellido' => [
        'notOptional' => 'Este campo es requerido',
        'stringType' => 'Este campo debe ser un string',
        'length' => 'Este campo debe tener una longitud menor o igual a 15',
    ],
    'nombre' => [
        'notOptional' => 'Este campo es requerido',
        'stringType' => 'Este campo debe ser un string',
        'length' => 'Este campo debe tener una longitud menor o igual a 25',
    ],
    'email' => [
        'notOptional' => 'Este campo es requerido',
        'email' => '',
        'length' => 'Este campo debe tener una longitud menor o igual a 20',
    ],
    'activo' => [
        'notOptional' => 'Este campo es requerido',
        'boolType' => 'Este campo debe ser un booleano',
    ],
]);
