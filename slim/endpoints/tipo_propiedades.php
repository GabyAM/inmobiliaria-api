<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

$app->get('/tipo_propiedades', function (Request $request, Response $response) {
    $pdo = createConnection(); //obtiene la direccion de la base de datos
    $sql = 'SELECT * FROM tipo_propiedades';
    $query = $pdo->query($sql); //query(consulta)

    $data = $query->fetchAll(PDO::FETCH_ASSOC); //fetchAll:arreglo asociativo

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'data' => $data,
        ])
    );

    return $response->withStatus(200);
});

$app->post('/tipo_propiedades', function (
    Request $request,
    Response $response
) {
    $data = $request->getParsedBody();
    $nombre = $data['nombre'] ?? null;

    $errores = obtenerErrores(
        ['nombre' => $nombre],
        ['nombre' => v::notOptional()->stringType()]
    );

    if (!empty($errores)) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'errors' => $errores,
            ])
        );
        return $response->withStatus(400);
    }

    $pdo = createConnection();
    $sql = "SELECT * FROM tipo_propiedades
        WHERE nombre = :nombre";

    $query = $pdo->prepare($sql);
    $query->bindValue(':nombre', $nombre);
    $query->execute();

    if ($query->rowCount() > 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => 'el campo nombre no se puede repetir',
            ])
        );

        return $response->withStatus(400);
    }

    $sql = "INSERT INTO tipo_propiedades (nombre)
        values (:nombre)";

    $query = $pdo->prepare($sql);
    $query->bindValue(':nombre', $nombre);
    $query->execute();

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'tipo de propiedad creada',
        ])
    );
    return $response->withStatus(200);
});

$app->put('/tipo_propiedades/{id}', function (
    request $request,
    response $response,
    array $args
) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $nombre = $data['nombre'] ?? null;

    $validaciones = [
        'id' => v::notOptional()->regex('/^[0-9]+$/'),
        'nombre' => v::notOptional()->stringType(),
    ];

    $errores = obtenerErrores(
        ['id' => $id, 'nombre' => $nombre],
        $validaciones
    );

    if (!empty($errores)) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'errores' => $errores,
            ])
        );
        return $response->withStatus(400);
    }
    $pdo = createConnection();
    $sql = "SELECT * FROM tipo_propiedades
        WHERE id = :id";

    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();

    if ($query->rowCount() == 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => 'no existe una propiedad con el ID provisto',
            ])
        );
        return $response->withStatus(400);
    }

    $sql = 'SELECT * FROM localidades WHERE nombre = :nombre AND id != :id'; //busco si existe un mismo nombre con otra ID
    $query = $pdo->prepare($sql);
    $query->bindParam(':id', $id);
    $query->bindParam(':nombre', $nombre);
    $query->execute();
    if ($query->rowCount() > 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => 'ya existe otra propiedad con el mismo nombre',
            ])
        );
        return $response->withStatus(400);
    }

    $sql = "UPDATE tipo_propiedades
        SET nombre = :nombre
        WHERE id = :id";

    $query = $pdo->prepare($sql);
    $query->bindValue(':nombre', $nombre);
    $query->bindValue(':id', $id);
    $query->execute();

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'se actualizo con exito',
        ])
    );

    return $response->withStatus(200);
});

$app->delete('/tipo_propiedades/{id}', function (
    request $request,
    response $response,
    array $args
) {
    $id = $args['id'];

    $errores = obtenerErrores(
        ['id' => $id],
        ['id' => v::notOptional()->regex('/^[0-9]+$/')]
    );

    if (!empty($errores)) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'errores' => $errores,
            ])
        );
        return $response->withStatus(400);
    }

    $pdo = createConnection();
    $sql = "SELECT * FROM tipo_propiedades
        WHERE id = :id";

    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();

    if ($query->rowCount() == 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => 'no existe una propiedad con el ID provisto',
            ])
        );
        return $response->withStatus(400);
    }

    $sql = 'SELECT * FROM propiedades WHERE tipo_propiedad_id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    if ($query->rowCount() > 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' =>
                    'No se puede eliminar el tipo de propiedad porque una propiedad lo está usando',
            ])
        );
        return $response->withStatus(400);
    }
    $sql = 'DELETE FROM tipo_propiedades WHERE id = :id';

    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'se ELIMINO la propiedad',
        ])
    );
    return $response->withStatus(200);
});
