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

function obtenerErrores($inputs, $validaciones) {
    $errores = [];
    foreach ($validaciones as $campo => $regla) {
        $valor = isset($inputs[$campo]) ? $inputs[$campo] : null;
        try {
            $regla->assert($valor);
        } catch (NestedValidationException $e) {
            $errores[$campo] = $e->getMessages([
                'notOptional' => 'Este campo es requerido',
                'stringType' => 'Este campo debe ser de tipo string',
                'intType' => 'Este campo debe ser un entero',
                'numericVal' => 'Este campo debe ser un numero',
                'boolType' => 'Este campo debe ser un booleano',
                'date' =>
                    'Este campo debe ser una fecha en el formato 2024-04-12',
                'regex' => 'Este campo no está en el formato correcto',
            ]);
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

    try {
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

$app->put('/localidades/{id}', function (
    Request $request,
    Response $response,
    array $args
) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $nombre = $data['nombre'] ?? null;

    $validaciones = [
        'id' => v::notOptional()->numericVal(),
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

    try {
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

        $sql = 'SELECT * FROM localidades WHERE nombre = :nombre AND id != :id';
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

$app->delete('/localidades/{id}', function (
    Request $request,
    Response $response,
    array $args
) {
    $id = $args['id'];
    $errores = obtenerErrores(
        ['id' => $id],
        ['id' => v::notOptional()->numericVal()]
    );
    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
    }

    try {
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

$app->get('/propiedades', function (Request $request, Response $response) {
    $pdo = createConnection();

    try {
        $sql = 'SELECT * FROM propiedades';
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

$app->post('/propiedades', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $validaciones = [
        'domicilio' => v::notOptional()->stringType(),
        'localidad_id' => v::notOptional()->intType(),
        'cantidad_habitaciones' => v::optional(v::intType()),
        'cantidad_banios' => v::optional(v::intType()),
        'cochera' => v::optional(v::boolType()),
        'cantidad_huespedes' => v::notOptional()->intType(),
        'fecha_inicio_disponibilidad' => v::notOptional()->date(),
        'cantidad_dias' => v::notOptional()->intType(),
        'disponible' => v::optional(v::boolType()), //Lo hago opcional por el momento porque por algún motivo no se valida correctamente
        'valor_noche' => v::notOptional()->intType(),
        'tipo_propiedad_id' => v::notOptional()->intType(),
        'imagen' => v::optional(v::stringType()),
        'tipo_imagen' => v::optional(v::regex('/jpg|jpeg|png/')),
    ];

    $errores = obtenerErrores($data, $validaciones);

    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
    }

    try {
        $pdo = createConnection();

        $localidadId = $data['localidad_id'];
        $sql = 'SELECT * FROM localidades WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $localidadId);
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

        $tipoPropiedadId = $data['tipo_propiedad_id'];
        $sql = 'SELECT * FROM tipo_propiedades WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $tipoPropiedadId);
        $query->execute();
        if ($query->rowCount() == 0) {
            $response->getBody()->write(
                json_encode([
                    'status' => 'failure',
                    'error' =>
                        'No existe una tipo de propiedad con el ID provisto',
                ])
            );
            return $response->withStatus(400);
        }

        $stringCampos = '';
        $stringValores = '';
        $i = 0;
        foreach ($data as $key => $value) {
            $stringCampos .= $key;
            if (is_bool($value)) {
                $stringValores .= $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                $stringValores .= '"' . $value . '"';
            } else {
                $stringValores .= $value;
            }
            if ($i < count($data) - 1) {
                $stringCampos .= ', ';
                $stringValores .= ', ';
            }
            $i++;
        }
        $sql =
            'INSERT INTO propiedades (' .
            $stringCampos .
            ') VALUES (' .
            $stringValores .
            ')';
        $query = $pdo->query($sql);
        $response->getBody()->write(
            json_encode([
                'status' => 'success',
                'message' => 'Propiedad creada',
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
});

$app->run();
