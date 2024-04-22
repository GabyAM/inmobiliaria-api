<?php
use Respect\Validation\Validator as v;

define('validaciones_localidad', [
    'nombre' => v::notOptional()->stringType()->length(null, 50),
]);
