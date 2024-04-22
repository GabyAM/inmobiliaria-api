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
    'disponible' => v::notOptional()->boolType(), //Lo hago opcional por el momento porque por algún motivo no se valida correctamente
    'valor_noche' => v::notOptional()->intType(),
    'tipo_propiedad_id' => v::notOptional()->intType(),
    'imagen' => v::optional(v::stringType()),
    'tipo_imagen' => v::optional(v::regex('/jpg|jpeg|png/')),
]);
