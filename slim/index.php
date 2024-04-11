<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';
include './validaciones/localidad.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader(
            'Access-Control-Allow-Headers',
            'X-Requested-With, Content-Type, Accept, Origin, Authorization'
        )
        ->withHeader(
            'Access-Control-Allow-Methods',
            'OPTIONS, GET, POST, PUT, PATCH, DELETE'
        )
        ->withHeader('Content-Type', 'application/json');
});

function createConnection() {
    $dsn = 'mysql:host=db;dbname=seminariophp';
    $username = 'seminariophp';
    $password = 'seminariophp';

    return new PDO($dsn, $username, $password);
}

$app->get('/localidades', function (Request $request, Response $response) {
    $pdo = createConnection();

    try {
        $sql = 'SELECT * FROM localidades';
        $query = $pdo->query($sql);
        $results = $query->fetchAll(PDO::FETCH_ASSOC);
        $data = ['status' => 'success', 'results' => $results];

        $response->getBody()->write(json_encode($data));
        return $response->withStatus(201);
    } catch (\Exception $e) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'error' => $e->getMessage(),
            ])
        );
        return $response->withStatus(500);
    }
});

$app->post('/localidades', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    if (isset($data['nombre'])) {
        $nombre = $data['nombre'];

        $error = validarNombreLocalidad($nombre);
        if ($error) {
            $response
                ->getBody()
                ->write(
                    json_encode(['status' => 'failure', 'error' => $error])
                );
            return $response->withStatus(400);
        }

        $pdo = createConnection();

        try {
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
        } catch (\Exception $e) {
            $response->getBody()->write(
                json_encode([
                    'status' => 'failure',
                    'error' => $e->getMessage(),
                ])
            );
            return $response->withStatus(500);
        }
    } else {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'error' => 'No se ingreso un nombre',
            ])
        );
        return $response->withStatus(400);
    }
});

$app->put('/localidades/{id}', function (
    Request $request,
    Response $response,
    array $args
) {
    try {
        $pdo = createConnection();

        $id = $args['id'];
        if (!(is_numeric($id) && (int) $id == $id)) {
            $response->getBody()->write(
                json_encode([
                    'status' => 'failure',
                    'error' => 'El ID debe ser un valor numérico',
                ])
            );
            return $response->withStatus(400);
        }

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

        $data = $request->getParsedBody();
        if (isset($data['nombre'])) {
            $nombre = $data['nombre'];
            $error = validarNombreLocalidad($nombre);
            if ($error) {
                $response
                    ->getBody()
                    ->write(
                        json_encode(['status' => 'failure', 'error' => $error])
                    );
                return $response->withStatus(400);
            }

            $sql =
                'SELECT * FROM localidades WHERE nombre = :nombre AND id != :id';
            $query = $pdo->prepare($sql);
            $query->bindParam(':id', $id);
            $query->bindParam(':nombre', $nombre);
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
        } else {
            $response->getBody()->write(
                json_encode([
                    'status' => 'failure',
                    'error' => 'No se ingreso un nombre',
                ])
            );
            return $response->withStatus(400);
        }
    } catch (\Exception $e) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'error' => $e->getMessage(),
            ])
        );
        return $response->withStatus(500);
    }
});

$app->run();
