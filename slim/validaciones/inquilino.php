<?php
use Respect\Validation\Validator as v;

define('validaciones_inquilino', [
    'documento' => v::notoptional()->stringType()->length(null, 20),
    'apellido' => v::notOptional()->stringType()->length(null, 15),
    'nombre' => v::notOptional()->stringType()->length(null, 25),
    'email' => v::notOptional()->email()->length(null, 20),
    'activo' => v::notOptional()->boolType(),
]);
