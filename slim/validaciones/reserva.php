<?php
use Respect\Validation\Validator as v;

define('validaciones_reserva', [
    'propiedad_id' => v::notOptional()->intType(),
    'inquilino_id' => v::notOptional()->intType(),
    'fecha_desde' => v::notOptional()->date(),
    'cantidad_noches' => v::notOptional()->intType(),
]);
