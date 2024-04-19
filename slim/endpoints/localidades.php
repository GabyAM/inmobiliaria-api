<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

$app->get('/localidades', function (Request $request, Response $response) {
    $pdo = createConnection();

    $sql = 'SELECT * FROM localidades';
    $query = $pdo->query($sql);
    $results = $query->fetchAll(PDO::FETCH_ASSOC);
    $data = ['status' => 'success', 'results' => $results];

    $response->getBody()->write(json_encode($data));
    return $response->withStatus(201);
});

$app->post('/localidades', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $nombre = $data['nombre'] ?? null;

    $errores = obtenerErrores(
        ['nombre' => $nombre],
        ['nombre' => v::notOptional()->stringType()]
    );
    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
    }

    $pdo = createConnection();

    $sql = 'SELECT * FROM localidades WHERE nombre = :nombre';
    $query = $pdo->prepare($sql);
    $query->bindValue(':nombre', $nombre);
    $query->execute();
    if ($query->rowCount() > 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'error' => 'Ya existe una localidad con ese nombre',
            ])
        );
        return $response->withStatus(409);
    }

    $sql = 'INSERT INTO localidades (nombre) VALUES (:nombre)';
    $query = $pdo->prepare($sql);
    $query->bindValue(':nombre', $nombre);
    $query->execute();

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'Localidad creada',
        ])
    );
    return $response->withStatus(201);
});

$app->put('/localidades/{id}', function (
    Request $request,
    Response $response,
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
        [
            'id' => $id,
            'nombre' => $nombre,
        ],
        $validaciones
    );
    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
    }

    $pdo = createConnection();

    $errores = [];

    $sql = 'SELECT * FROM localidades WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindParam(':id', $id);
    $query->execute();
    if ($query->rowCount() == 0) {
        $errores['localidad_id'] = 'No existe una localidad con el ID provisto';
    }

    $sql = 'SELECT * FROM localidades WHERE nombre = :nombre AND id != :id';
    $query = $pdo->prepare($sql);
    $query->bindParam(':id', $id);
    $query->bindParam(':nombre', $nombre);
    $query->execute();
    if ($query->rowCount() > 0) {
        $errores['nombre'] = 'Ya existe una localidad con ese nombre';
    }

    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
    }

    $sql = 'UPDATE localidades SET nombre = :nombre WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindParam(':id', $id);
    $query->bindParam(':nombre', $nombre);
    $query->execute();

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'Localidad actualizada',
        ])
    );
    return $response->withStatus(200);
});

$app->delete('/localidades/{id}', function (
    Request $request,
    Response $response,
    array $args
) {
    $id = $args['id'];
    $errores = obtenerErrores(
        ['id' => $id],
        ['id' => v::notOptional()->regex('/^[0-9]+$/')]
    );
    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
    }

    $pdo = createConnection();

    $sql = 'SELECT * FROM localidades WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindParam(':id', $id);
    $query->execute();
    if ($query->rowCount() == 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'error' => 'No existe una localidad con el ID provisto',
            ])
        );
        return $response->withStatus(400);
    }

    $sql = 'SELECT * FROM propiedades WHERE localidad_id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    if ($query->rowCount() > 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'error' =>
                    'No se puede eliminar la localidad porque una propiedad la está usando',
            ])
        );
        return $response->withStatus(400);
    }

    $sql = 'DELETE FROM localidades WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'Localidad borrada',
        ])
    );
    return $response->withStatus(200);
});
