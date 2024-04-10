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


$app->run();
