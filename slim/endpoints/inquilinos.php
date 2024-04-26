<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

require_once __DIR__ . '/../utilidades/strings_sql.php';
require_once __DIR__ . '/../validaciones/inquilino.php';
//listar
$app->get('/inquilinos', function (Request $request, Response $response) {
    $pdo = createConnection();

    $sql = 'SELECT * FROM inquilinos';
    $query = $pdo->query($sql);
    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    $payload = json_encode([
        'status' => 'success',
        'data' => $data,
    ]);

    $response->getBody()->write($payload);
    return $response->withStatus(200);
});
//ver inquilino
$app->get('/inquilinos/{id:[0-9]+}', function (
    Request $resquest,
    Response $response,
    array $args
) {
    $id = $args['id'];
    $pdo = createConnection();
    $sql = 'SELECT * FROM inquilinos WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();

    if ($query->rowCount() == 0) {
        throw new Exception(
            'no se encontro ningun inquilino con el ID provisto',
            404
        );
    }
    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'data' => $query->fetchAll(PDO::FETCH_ASSOC),
        ])
    );
    return $response->withStatus(200);
});

$app->get('/inquilinos/{id:[0-9]+}/reservas', function (
    Request $request,
    Response $response,
    array $args
) {
    $id = $args['id'];
    $pdo = createConnection();

    $sql = 'SELECT * FROM inquilinos WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();

    if ($query->rowCount() == 0) {
        throw new Exception(
            'no existe ningun inquilino con el ID provisto',
            404
        );
    }

    $sql = 'SELECT * FROM reservas WHERE inquilino_id = :inquilino_id ';
    $query = $pdo->prepare($sql);
    $query->bindValue(':inquilino_id', $id);
    $query->execute();

    /*if ($query->rowCount() == 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => 'el inquilino no realizo ninguna reserva',
            ])
        );
        return $response->withStatus(400);
    }     No hace falta devolver un error cuando el inquilino no tiene reservas, basta con devolver
          el arreglo de reservas vacío */

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'data' => $query->fetchAll(PDO::FETCH_ASSOC),
        ])
    );
    return $response->withStatus(200);
});

$app->post('/inquilinos', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $errores = obtenerErrores($data, validaciones_inquilino);

    if (!empty($errores)) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'error' => $errores,
            ])
        );
        return $response->withStatus(400);
    }

    $documento = $data['documento'];

    $pdo = createConnection();
    $sql = 'SELECT * FROM inquilinos 
        WHERE documento = :documento ';

    $query = $pdo->prepare($sql);
    $query->bindValue(':documento', $documento);
    $query->execute();

    if ($query->rowCount() > 0) {
        throw new Exception('no se puede repetir el documento', 409);
    }

    $stringInserciones = construirStringInserciones($data);

    $sql = 'INSERT INTO inquilinos' . $stringInserciones;

    $query = $pdo->query($sql);

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'inquilino creada',
        ])
    );

    return $response->withStatus(200);
});

$app->put('/inquilinos/{id:[0-9]+}', function (
    Request $request,
    Response $response,
    array $args
) {
    $data = array_intersect_key(
        $request->getParsedBody() ?? [],
        array_flip(['documento', 'apellido', 'nombre', 'email', 'activo'])
    );

    if (empty($data)) {
        throw new Exception('No se insertó ningún valor', 404);
    }

    //$id = $args['id'] ?? null;     $args['id'] nunca es null, ya que si no existiera el id, el url sería invalido
    $id = $args['id'];

    $errores = obtenerErrores($data, validaciones_inquilino, true);

    if (!empty($errores)) {
        //esta vacia?
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'errors' => $errores,
            ])
        );
        return $response->withStatus(400);
    }

    $pdo = createConnection();

    $sql = 'SELECT * From inquilinos WHERE id = :id';

    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    if ($query->rowCount() == 0) {
        throw new Exception('No existe un inquilino con la ID provista', 404);
    }

    if (isset($data['documento'])) {
        $documento = $data['documento'];
        $sql = 'SELECT * FROM inquilinos WHERE documento = :documento';

        $query = $pdo->prepare($sql);
        $query->bindValue(':documento', $documento);
        $query->execute();

        if ($query->rowCount() > 0) {
            throw new Exception('No se puede repetir el documento', 409);
        }
    }

    $stringActualizaciones = construirStringActualizaciones($data);
    $sql =
        'UPDATE inquilinos SET ' . $stringActualizaciones . ' WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'se actualizo el inquilino',
        ])
    );

    return $response->withStatus(200);
});

$app->delete('/inquilinos/{id:[0-9]+}', function (
    Request $request,
    Response $response,
    array $args
) {
    $id = $args['id'];
    $pdo = createConnection();

    if (!existeEnTabla($pdo, 'inquilinos', $id)) {
        throw new Exception('No existe un inquilino con el ID provisto', 404);
    }

    $sql = 'SELECT * FROM reservas WHERE inquilino_id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    if ($query->rowCount() > 0) {
        throw new Exception(
            'no se puede eliminar el inquilino porque una reserva lo está utilizando',
            409
        );
    }

    $sql = 'DELETE FROM inquilinos WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'se elimino el inquilino',
        ])
    );
    return $response->withStatus(200);
});
