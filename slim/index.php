<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Respect\Validation\Validator as v;

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

$app->delete('/localidades/{id}', function (
    Request $request,
    Response $response,
    array $args
) {
    try {
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
        'domicilio' => v::notEmpty()->stringType(),
        'localidad_id' => v::notEmpty()->intVal(),
        'cantidad_habitaciones' => v::optional(v::intVal()),
        'cantidad_banios' => v::optional(v::intVal()),
        'cochera' => v::optional(v::boolType()),
        'cantidad_huespedes' => v::notEmpty()->intVal(),
        'fecha_inicio_disponibilidad' => v::notEmpty()->date(),
        'cantidad_dias' => v::notEmpty()->intVal(),
        'disponible' => v::optional(v::boolType()), //Lo hago opcional por el momento porque por algún motivo no se valida correctamente
        'valor_noche' => v::notEmpty()->intVal(),
        'tipo_propiedad_id' => v::notEmpty()->intVal(),
        'imagen' => v::optional(v::stringType()),
        'tipo_imagen' => v::optional(v::regex('/jpg|jpeg|png/')),
    ];

    $errores = [];
    foreach ($validaciones as $campo => $regla) {
        $valor = isset($data[$campo]) ? $data[$campo] : null;
        try {
            $regla->assert($valor);
        } catch (\Respect\Validation\Exceptions\NestedValidationException $e) {
            $errores[$campo] = $e->getMessages([
                'notEmpty' => 'Este campo es requerido',
                'stringType' => 'Este campo debe ser de tipo string',
                'intVal' => 'Este campo debe ser un entero',
                'boolType' => 'Este campo debe ser un booleano',
                'date' =>
                    'Este campo debe ser una fecha en el formato 2024-04-12',
                'regex' => 'Este campo no está en el formato correcto',
            ]);
        }
    }

    if (!empty($errores)) {
        $response->getBody()->write(json_encode(['errors' => $errores]));
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
