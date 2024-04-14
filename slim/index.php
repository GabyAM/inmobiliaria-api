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

function obtenerErrores($inputs, $validaciones, $opcionales = false) {
    $mensajes = [
        'notOptional' => 'Este campo es requerido',
        'stringType' => 'Este campo debe ser de tipo string',
        'intType' => 'Este campo debe ser un entero',
        'numericVal' => 'Este campo debe ser un numero',
        'boolType' => 'Este campo debe ser un booleano',
        'date' => 'Este campo debe ser una fecha en el formato 2024-04-12',
        'regex' => 'Este campo no está en el formato correcto',
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
    $data = array_intersect_key(
        $request->getParsedBody() ?? [],
        array_flip([
            'domicilio',
            'localidad_id',
            'cantidad_habitaciones',
            'cantidad_banios',
            'cochera',
            'cantidad_huespedes',
            'fecha_inicio_disponibilidad',
            'cantidad_dias',
            'disponible',
            'valor_noche',
            'tipo_propiedad_id',
            'imagen',
            'tipo_imagen',
        ])
    ); //creo un nuevo array con los campos necesarios para no operar sobre campos adicionales/incorrectos

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

$app->put('/propiedades/{id}', function (
    Request $request,
    Response $response,
    array $args
) {
    $data = array_intersect_key(
        $request->getParsedBody() ?? [],
        array_flip([
            'domicilio',
            'localidad_id',
            'cantidad_habitaciones',
            'cantidad_banios',
            'cochera',
            'cantidad_huespedes',
            'fecha_inicio_disponibilidad',
            'cantidad_dias',
            'disponible',
            'valor_noche',
            'tipo_propiedad_id',
            'imagen',
            'tipo_imagen',
        ])
    );
    if (empty($data)) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'error' => 'No se insertó ningún valor',
            ])
        );
        return $response->withStatus(400);
    }

    $id = $args['id'];
    $validaciones = [
        'id' => v::notOptional()->numericVal(),
        'domicilio' => v::stringType(),
        'localidad_id' => v::intType(),
        'cantidad_habitaciones' => v::intType(),
        'cantidad_banios' => v::intType(),
        'cochera' => v::boolType(),
        'cantidad_huespedes' => v::intType(),
        'fecha_inicio_disponibilidad' => v::date(),
        'cantidad_dias' => v::intType(),
        'disponible' => v::boolType(),
        'valor_noche' => v::intType(),
        'tipo_propiedad_id' => v::intType(),
        'imagen' => v::stringType(),
        'tipo_imagen' => v::regex('/jpg|jpeg|png/'),
    ];

    $errores = obtenerErrores([...$data, 'id' => $id], $validaciones, true);
    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
    }

    try {
        $pdo = createConnection();

        if (isset($data['localidad_id'])) {
            $localidadId = $data['localidad_id'];
            $sql = 'SELECT FROM localidades WHERE id = :id';
            $query = $pdo->prepare($sql);
            $query->bindValue(':id', $localidadId);
            if ($query->rowCount() != 1) {
                $response->getBody()->write(
                    json_encode([
                        'status' => 'failure',
                        'error' => 'No existe una localidad con el ID provisto',
                    ])
                );
                return $response->withStatus(400);
            }
        }
        if (isset($data['tipo_propiedad_id'])) {
            $tipoPropiedadId = $data['tipo_propiedad_id'];
            $sql = 'SELECT FROM tipo_propiedades WHERE id = :id';
            $query = $pdo->prepare($sql);
            $query->bindValue(':id', $tipoPropiedadId);
            if ($query->rowCount() != 1) {
                $response->getBody()->write(
                    json_encode([
                        'status' => 'failure',
                        'error' =>
                            'No existe un tipo de propiedad con el ID provisto',
                    ])
                );
                return $response->withStatus(400);
            }
        }

        $stringActualizaciones = '';
        $i = 0;
        foreach ($data as $key => $value) {
            $stringActualizaciones .= $key . ' = ';
            if (is_bool($value)) {
                $stringActualizaciones .= $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                $stringActualizaciones .= '"' . $value . '"';
            } else {
                $stringActualizaciones .= $value;
            }
            if ($i < count($data) - 1) {
                $stringActualizaciones .= ', ';
            }
            $i++;
        }
        $sql =
            'UPDATE propiedades SET ' .
            $stringActualizaciones .
            ' WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $id);
        $query->execute();
        $response->getBody()->write(
            json_encode([
                'status' => 'success',
                'message' => 'Propiedad actualizada',
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

$app->delete('/propiedades/{id}', function (
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

        $sql = 'SELECT * FROM propiedades WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $id);
        $query->execute();
        if ($query->rowCount() == 0) {
            $response->getBody()->write(
                json_encode([
                    'status' => 'failure',
                    'error' => 'No existe una propiedad con el ID provisto',
                ])
            );
            return $response;
        }

        $sql = 'DELETE FROM propiedades WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $id);
        $query->execute();
        $response->getBody()->write(
            json_encode([
                'status' => 'success',
                'message' => 'Propiedad borrada',
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
});

$app->get('/reservas', function (Request $request, Response $response) {
    try {
        $pdo = createConnection();
        $sql = 'SELECT * FROM reservas';
        $query = $pdo->query($sql);
        $results = $query->fetchAll(PDO::FETCH_ASSOC);
        $data = ['status' => 'success', 'results' => $results];

        $response->getBody()->write(json_encode($data));
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

$app->post('/reservas', function (Request $request, Response $response) {
    $data = array_intersect_key(
        $request->getParsedBody() ?? [],
        array_flip([
            'propiedad_id',
            'inquilino_id',
            'fecha_desde',
            'cantidad_noches',
        ])
    );

    $validaciones = [
        'propiedad_id' => v::notOptional()->intType(),
        'inquilino_id' => v::notOptional()->intType(),
        'fecha_desde' => v::notOptional()->date(),
        'cantidad_noches' => v::notOptional()->intType(),
    ];

    $errores = obtenerErrores($data, $validaciones);
    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
    }

    $propiedadId = $data['propiedad_id'];
    $inquilinoId = $data['inquilino_id'];
    $fechaDesde = $data['fecha_desde'];
    $cantidadNoches = $data['cantidad_noches'];

    try {
        $pdo = createConnection();

        $sql = 'SELECT * FROM inquilinos WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $inquilinoId);
        $query->execute();
        if ($query->rowCount() == 0) {
            $response->getBody()->write(
                json_encode([
                    'status' => 'failure',
                    'error' => 'No existe un inquilino con el ID provisto',
                ])
            );
            return $response;
        }
        $inquilino = $query->fetch(PDO::FETCH_ASSOC);
        if (!$inquilino['activo']) {
            $response->getBody()->write(
                json_encode([
                    'status' => 'failure',
                    'error' =>
                        'No se puede crear la reserva porque el inquilino no está activo',
                ])
            );
            return $response->withStatus(400);
        }

        $sql = 'SELECT * FROM propiedades WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $propiedadId);
        $query->execute();
        if ($query->rowCount() == 0) {
            $response->getBody()->write(
                json_encode([
                    'status' => 'failure',
                    'error' => 'No existe una propiedad con el ID provisto',
                ])
            );
            return $response;
        }

        $propiedad = $query->fetch(PDO::FETCH_ASSOC);
        $valorTotal = $propiedad['valor_noche'] * $cantidadNoches;

        $sql = 'INSERT INTO reservas (propiedad_id, inquilino_id, fecha_desde, cantidad_noches, valor_total) 
            VALUES (:propiedad_id, :inquilino_id, :fecha_desde, :cantidad_noches, :valor_total)';
        $query = $pdo->prepare($sql);
        $query->bindValue(':propiedad_id', $propiedadId, PDO::PARAM_INT);
        $query->bindValue(':inquilino_id', $inquilinoId, PDO::PARAM_INT);
        $query->bindValue(':fecha_desde', $fechaDesde, PDO::PARAM_STR);
        $query->bindValue(':cantidad_noches', $cantidadNoches, PDO::PARAM_INT);
        $query->bindValue(':valor_total', $valorTotal, PDO::PARAM_INT);
        $query->execute();

        $response->getBody()->write(
            json_encode([
                'status' => 'success',
                'message' => 'Reserva creada',
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
});

$app->run();
