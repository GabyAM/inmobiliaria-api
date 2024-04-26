<?php
use Respect\Validation\Validator as v;

define('validaciones_propiedad', [
    'domicilio' => v::notOptional()->stringType(),
    'localidad_id' => v::notOptional()->intType(),
    'cantidad_habitaciones' => v::optional(v::intType()),
    'cantidad_banios' => v::optional(v::intType()),
    'cochera' => v::optional(v::boolType()),
    'cantidad_huespedes' => v::notOptional()->intType(),
    'fecha_inicio_disponibilidad' => v::notOptional()->date(),
    'cantidad_dias' => v::notOptional()->intType(),
    'disponible' => v::notOptional()->boolType(),
    'valor_noche' => v::notOptional()->intType(),
    'tipo_propiedad_id' => v::notOptional()->intType(),
    'imagen' => v::optional(v::stringType()),
    'tipo_imagen' => v::optional(v::regex('/jpg|jpeg|png/')),
]);

define('mensajes_error_propiedad', [
    'domicilio' => [
        'notOptional' => 'Este campo es requerido',
        'stringType' => 'Este campo debe ser un string',
    ],
    'localidad_id' => [
        'notOptional' => 'Este campo es requerido',
        'intType' => 'Este campo debe ser un entero',
    ],
    'cantidad_habitaciones' => [
        'intType' => 'Este campo debe ser un entero',
    ],
    'cantidad_banios' => [
        'intType' => 'Este campo debe ser un entero',
    ],
    'cochera' => [
        'boolType' => 'Este campo debe ser un booleano',
    ],
    'cantidad_huespedes' => [
        'notOptional' => 'Este campo es requerido',
        'intType' => 'Este campo debe ser un entero',
    ],
    'fecha_inicio_disponibilidad' => [
        'notOptional' => 'Este campo es requerido',
        'date' => 'Este campo debe ser una fecha',
    ],
    'cantidad_dias' => [
        'notOptional' => 'Este campo es requerido',
        'intType' => 'Este campo debe ser un entero',
    ],
    'disponible' => [
        'notOptional' => 'Este campo es requerido',
        'boolType' => 'Este campo debe ser un booleano',
    ],
    'valor_noche' => [
        'notOptional' => 'Este campo es requerido',
        'intType' => 'Este campo debe ser un entero',
    ],
    'tipo_propiedad_id' => [
        'notOptional' => 'Este campo es requerido',
        'intType' => 'Este campo debe ser un entero',
    ],
    'imagen' => [
        'notOptional' => 'Este campo es requerido',
        'stringType' => 'Este campo debe ser un string',
    ],
    'tipo_imagen' => [
        'regex' =>
            'Este campo debe ser un tipo de imagen aceptado (jpg, jpeg, png)',
    ],
]);

define('validaciones_filtros_propiedad', [
    'disponible' => v::regex('/true|false/'),
    'localidad_id' => v::regex('/^[0-9]+$/'),
    'fecha_inicio_disponibilidad' => v::date(),
    'cantidad_huespedes' => v::regex('/^[0-9]+$/'),
]);

define('mensajes_error_filtros_propiedad', [
    'disponible' => [
        'regex' => 'Este parámetro debe ser un booleano',
    ],
    'localidad_id' => [
        'regex' => 'Este parámetro debe ser un entero',
    ],
    'fecha_inicio_disponibilidad' => [
        'date' => 'Este parámetro debe ser una fecha',
    ],
    'cantidad_huespedes' => [
        'regex' => 'Este parámetro debe ser un entero',
    ],
]);
