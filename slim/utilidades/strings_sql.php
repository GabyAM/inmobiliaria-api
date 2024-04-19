<?php
function construirStringActualizaciones($valores): string {
    $stringActualizaciones = '';
    $i = 0;
    foreach ($valores as $key => $value) {
        $stringActualizaciones .= $key . ' = ';
        if (is_bool($value)) {
            $stringActualizaciones .= $value ? 'true' : 'false';
        } elseif (is_string($value)) {
            $stringActualizaciones .= '"' . $value . '"';
        } else {
            $stringActualizaciones .= $value;
        }
        if ($i < count($valores) - 1) {
            $stringActualizaciones .= ', ';
            $i++;
        }
    }

    return $stringActualizaciones;
}

function construirStringInserciones($valores): string {
    $stringCampos = '';
    $stringValores = '';
    $i = 0;
    foreach ($valores as $key => $value) {
        $stringCampos .= $key;
        if (is_bool($value)) {
            $stringValores .= $value ? 'true' : 'false';
        } elseif (is_string($value)) {
            $stringValores .= '"'.$value.'"';
        } else {
            $stringValores .= $value;
        }
        if ($i < count($valores) - 1) {
            $stringCampos .= ', ';
            $stringValores .= ', ';
            $i++;
        }
    }

    return '(' . $stringCampos . ') VALUES (' . $stringValores . ')';
}
