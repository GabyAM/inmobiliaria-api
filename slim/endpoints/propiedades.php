<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../utilidades/strings_sql.php';
require_once __DIR__ . '/../validaciones/propiedad.php';

$app->get('/propiedades', function (Request $request, Response $response) {
    $params = array_intersect_key(
        $request->getQueryParams() ?? [],
        array_flip([
            'disponible',
            'localidad_id',
            'fecha_inicio_disponibilidad',
            'cantidad_huespedes',
        ])
    );

    $errores = obtenerErrores(
        $params,
        validaciones_filtros_propiedad,
        mensajes_error_filtros_propiedad,
        true
    );
    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
    }

    $pdo = createConnection();

    $stringCondiciones = '';
    $i = 0;
    foreach ($params as $key => $value) {
        if ($key == 'fecha_inicio_disponibilidad') {
            $stringCondiciones .= $key . ' = "' . $value . '"';
        } else {
            $stringCondiciones .= $key . ' = ' . $value;
        }
        if ($i < count($params) - 1) {
            $stringCondiciones .= ', ';
        }
        $i++;
    }

    $sql = 'SELECT * FROM propiedades';
    if ($stringCondiciones != '') {
        $sql .= ' WHERE ' . $stringCondiciones;
    }
    $query = $pdo->query($sql);
    $results = $query->fetchAll(PDO::FETCH_ASSOC);
    $data = ['status' => 'success', 'results' => $results];

    $response->getBody()->write(json_encode($data));
    return $response->withStatus(201);
});

$app->get('/propiedades/{id:[0-9]+}', function (
    Request $request,
    Response $response,
    array $args
) {
    $pdo = createConnection();
    $id = $args['id'];

    $sql = 'SELECT * FROM propiedades WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    if ($query->rowCount() == 0) {
        throw new Exception('No existe una propiedad con el ID provisto', 404);
    }
    $propiedad = $query->fetch(PDO::FETCH_ASSOC);
    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'data' => $propiedad,
        ])
    );
    return $response->withStatus(200);
});

$app->post('/propiedades', function (Request $request, Response $response) {
    $data = array_intersect_key(
        $request->getParsedBody() ?? [],
        array_flip([
            'domicilio',
            'localidad_id',
            'cantidad_habitaciones',
            'cantidad_banios',
            'cochera',
            'cantidad_huespedes',
            'fecha_inicio_disponibilidad',
            'cantidad_dias',
            'disponible',
            'valor_noche',
            'tipo_propiedad_id',
            'imagen',
            'tipo_imagen',
        ])
    ); //creo un nuevo array con los campos necesarios para no operar sobre campos adicionales/incorrectos

    $errores = obtenerErrores(
        $data,
        validaciones_propiedad,
        mensajes_error_propiedad
    );

    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
    }

    $pdo = createConnection();

    $localidadId = $data['localidad_id'];
    if (!existeEnTabla($pdo, 'localidades', $localidadId)) {
        throw new Exception('No existe una localidad con el ID provisto', 404);
    }

    $tipoPropiedadId = $data['tipo_propiedad_id'];
    if (!existeEnTabla($pdo, 'tipo_propiedades', $tipoPropiedadId)) {
        throw new Exception(
            'No existe una tipo de propiedad con el ID provisto',
            404
        );
    }

    $stringInserciones = construirStringInserciones($data);
    $sql = 'INSERT INTO propiedades ' . $stringInserciones;
    $query = $pdo->query($sql);
    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'Propiedad creada',
        ])
    );
    return $response->withStatus(201);
});

$app->put('/propiedades/{id:[0-9]+}', function (
    Request $request,
    Response $response,
    array $args
) {
    $data = array_intersect_key(
        $request->getParsedBody() ?? [],
        array_flip([
            'domicilio',
            'localidad_id',
            'cantidad_habitaciones',
            'cantidad_banios',
            'cochera',
            'cantidad_huespedes',
            'fecha_inicio_disponibilidad',
            'cantidad_dias',
            'disponible',
            'valor_noche',
            'tipo_propiedad_id',
            'imagen',
            'tipo_imagen',
        ])
    );
    if (empty($data)) {
        throw new Exception('No se insertó ningún valor', 400);
    }

    $errores = obtenerErrores(
        $data,
        validaciones_propiedad,
        mensajes_error_propiedad,
        true
    );
    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
    }

    $pdo = createConnection();

    if (isset($data['localidad_id'])) {
        $localidadId = $data['localidad_id'];
        if (!existeEnTabla($pdo, 'localidades', $localidadId)) {
            throw new Exception(
                'No existe una localidad con el ID provisto',
                404
            );
        }
    }
    if (isset($data['tipo_propiedad_id'])) {
        $tipoPropiedadId = $data['tipo_propiedad_id'];
        if (!existeEnTabla($pdo, 'tipo_propiedades', $tipoPropiedadId)) {
            throw new Exception(
                'No existe un tipo de propiedad con el ID provisto',
                404
            );
        }
    }

    $id = $args['id'];
    // Si se actualiza el valor por noche, entonces también
    // hay que actualizar el valor total de las reservas
    // que referencien a esta propiedad
    if (isset($data['valor_noche'])) {
        $sql = 'SELECT * FROM reservas WHERE propiedad_id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $id);
        $query->execute();
        if ($query->rowCount() > 0) {
            $valorNoche = $data['valor_noche'];
            $reservas = $query->fetchAll(PDO::FETCH_ASSOC);
            $sql =
                'UPDATE reservas SET valor_total = :valor_total WHERE id = :id';
            $query = $pdo->prepare($sql);
            foreach ($reservas as $reserva) {
                $cantidadNoches = $reserva['cantidad_noches'];
                $query->bindValue(
                    ':valor_total',
                    $cantidadNoches * $valorNoche
                );
                $query->bindValue(':id', $reserva['id']);
                $query->execute();
            }
        }
    }

    $stringActualizaciones = construirStringActualizaciones($data);
    $sql =
        'UPDATE propiedades SET ' . $stringActualizaciones . ' WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'Propiedad actualizada',
        ])
    );
    return $response->withStatus(200);
});

$app->delete('/propiedades/{id:[0-9]+}', function (
    Request $request,
    Response $response,
    array $args
) {
    $id = $args['id'];
    $pdo = createConnection();

    $sql = 'SELECT * FROM reservas WHERE propiedad_id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    if ($query->rowCount() > 0) {
        throw new Exception(
            'No se puede eliminar la propiedad porque una reserva la está usando',
            409
        );
    }

    if (!existeEnTabla($pdo, 'propiedades', $id)) {
        throw new Exception('No existe una propiedad con el ID provisto', 404);
    }

    $sql = 'DELETE FROM propiedades WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'Propiedad borrada',
        ])
    );
    return $response;
});
