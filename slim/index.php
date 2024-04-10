<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

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

        if (gettype($nombre) != 'string') {
            $response->getBody()->write(
                json_encode([
                    'status' => 'failure',
                    'error' => 'El nombre debe ser un string',
                ])
            );
            return $response->withStatus(400);
        }
        if (strlen($nombre) > 50) {
            $response->getBody()->write(
                json_encode([
                    'status' => 'failure',
                    'error' =>
                        'El nombre tiene que tener menos de 50 carácteres',
                ])
            );
            return $response->withStatus(400);
        }

        $pdo = createConnection();

        try {
            $sql = 'INSERT INTO localdades (nombre) VALUES (:nombre)';
            $query = $pdo->prepare($sql);
            $query->bindValue(':nombre', $nombre);
            $query->execute();

            $response->getBody()->write(
                json_encode([
                    'status' => 'success',
                    'message' => 'Localidad creada',
                ])
            );
            return $response;
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

$app->run();
