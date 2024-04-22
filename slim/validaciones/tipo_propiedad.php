<?php
use Respect\Validation\Validator as v;

define('validaciones_tipo_propiedad', [
    'nombre' => v::notOptional()->stringType()->length(null, 50),
]);
