<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Exceptions\NestedValidationException;
use Slim\Factory\AppFactory;

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

    $exceptionCode = $exception->getCode();
    if (gettype($exceptionCode) !== 'integer' || $exceptionCode > 500) {
        $responseCode = 500;
    } else {
        $responseCode = $exception->getCode();
    }

    return $response->withStatus($responseCode);
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

function traducirMensajes($mensajes, $traducciones) {
    $mensajesTraducidos = [];
    foreach ($mensajes as $campo => $reglas) {
        $mensajesTraducidos[$campo] = [];
        foreach ($reglas as $regla => $mensaje) {
            $mensajesTraducidos[$campo][$regla] = $traducciones[$campo][$regla];
        }
    }
    return $mensajesTraducidos;
}
function obtenerErrores(
    $inputs,
    $validaciones,
    $mensajes,
    $opcionales = false
) {
    $errores = [];
    if ($opcionales) {
        foreach ($validaciones as $campo => $regla) {
            if (isset($inputs[$campo])) {
                $valor = $inputs[$campo];
                try {
                    $regla->assert($valor);
                } catch (NestedValidationException $e) {
                    $errores[$campo] = $e->getMessages();
                }
            }
        }
    } else {
        foreach ($validaciones as $campo => $regla) {
            $valor = isset($inputs[$campo]) ? $inputs[$campo] : null;
            //echo $campo . ': ' . (string) $valor; //debug
            try {
                $regla->assert($valor);
            } catch (NestedValidationException $e) {
                $errores[$campo] = $e->getMessages();
            }
        }
    }

    return traducirMensajes($errores, $mensajes);
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
