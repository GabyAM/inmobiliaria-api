<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Exceptions\NestedValidationException;
use Slim\Factory\AppFactory;
use Respect\Validation\Validator as v;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$customErrorHandler = function (Request $request, Throwable $exception) use (
    $app
) {
    $payload = ['status' => 'failure', 'error' => $exception->getMessage()];

    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));

    return $response->withStatus(
        $exception->getCode() > 500 ? 500 : $exception->getCode()
    );
};

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);
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

function obtenerErrores($inputs, $validaciones, $opcionales = false) {
    $mensajes = [
        'notOptional' => 'Este campo es requerido',
        'stringType' => 'Este campo debe ser de tipo string',
        'intType' => 'Este campo debe ser un entero',
        'numericVal' => 'Este campo debe ser un numero',
        'boolType' => 'Este campo debe ser un booleano',
        'date' => 'Este campo debe ser una fecha en el formato 2024-04-12',
        'regex' => 'Este campo no está en el formato correcto',
        'greaterThan' => 'Este campo debe ser una fecha próxima',
    ];
    $errores = [];
    if ($opcionales) {
        foreach ($validaciones as $campo => $regla) {
            if (isset($inputs[$campo])) {
                $valor = $inputs[$campo];
                try {
                    $regla->assert($valor);
                } catch (NestedValidationException $e) {
                    $errores[$campo] = $e->getMessages($mensajes);
                }
            }
        }
    } else {
        foreach ($validaciones as $campo => $regla) {
            $valor = isset($inputs[$campo]) ? $inputs[$campo] : null;
            // echo $campo . ': ' . (string) $valor; //debug
            try {
                $regla->assert($valor);
            } catch (NestedValidationException $e) {
                $errores[$campo] = $e->getMessages($mensajes);
            }
        }
    }

    return $errores;
}

function createConnection() {
    $dsn = 'mysql:host=db;dbname=seminariophp';
    $username = 'seminariophp';
    $password = 'seminariophp';

    return new PDO($dsn, $username, $password);
}

function existeEnTabla($pdo, $nombreTabla, $id) {
    $sql = 'SELECT * FROM ' . $nombreTabla . ' WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id, PDO::PARAM_INT);
    $query->execute();
    return $query->rowCount() > 0;
}

require __DIR__ . '/endpoints/localidades.php';
require __DIR__ . '/endpoints/propiedades.php';
require __DIR__ . '/endpoints/reservas.php';
require __DIR__ . '/endpoints/tipo_propiedades.php';
require __DIR__ . '/endpoints/inquilinos.php';

$app->run();
