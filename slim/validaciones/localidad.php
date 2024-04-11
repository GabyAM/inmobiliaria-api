<?php

function validarNombreLocalidad($nombre) {
    if (!is_string($nombre)) {
        return [
            'status' => 'failure',
            'error' => 'El nombre debe ser un string',
        ];
    }
    if (strlen($nombre) > 50) {
        return [
            'status' => 'failure',
            'error' => 'El nombre tiene que tener menos de 50 carácteres',
        ];
    }
    return null;
}
