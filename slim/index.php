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

    return $response->withStatus(500);
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

$app->get('/propiedades', function (Request $request, Response $response) {
    $pdo = createConnection();

    $sql = 'SELECT * FROM propiedades';
    $query = $pdo->query($sql);
    $results = $query->fetchAll(PDO::FETCH_ASSOC);
    $data = ['status' => 'success', 'results' => $results];

    $response->getBody()->write(json_encode($data));
    return $response->withStatus(201);
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

    $pdo = createConnection();

    $errores = [];

    $localidadId = $data['localidad_id'];
    $sql = 'SELECT * FROM localidades WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $localidadId);
    $query->execute();
    if ($query->rowCount() == 0) {
        $errores['localidad_id'] = 'No existe una localidad con el ID provisto';
    }

    $tipoPropiedadId = $data['tipo_propiedad_id'];
    $sql = 'SELECT * FROM tipo_propiedades WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $tipoPropiedadId);
    $query->execute();
    if ($query->rowCount() == 0) {
        $errores['tipo_propiedad_id'] =
            'No existe una tipo de propiedad con el ID provisto';
    }

    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
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
        'id' => v::notOptional()->regex('/^[0-9]+$/'),
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

    $pdo = createConnection();

    $errores = [];
    if (isset($data['localidad_id'])) {
        $localidadId = $data['localidad_id'];
        $sql = 'SELECT FROM localidades WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $localidadId);
        if ($query->rowCount() != 1) {
            $errores['localidad_id'] =
                'No existe una localidad con el ID provisto';
        }
    }
    if (isset($data['tipo_propiedad_id'])) {
        $tipoPropiedadId = $data['tipo_propiedad_id'];
        $sql = 'SELECT FROM tipo_propiedades WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $tipoPropiedadId);
        if ($query->rowCount() != 1) {
            $errores['propiedad_id'];
        }
    }
    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
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
        'UPDATE propiedades SET ' . $stringActualizaciones . ' WHERE id = :id';
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
});

$app->delete('/propiedades/{id}', function (
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
});

$app->get('/reservas', function (Request $request, Response $response) {
    $pdo = createConnection();
    $sql = 'SELECT * FROM reservas';
    $query = $pdo->query($sql);
    $results = $query->fetchAll(PDO::FETCH_ASSOC);
    $data = ['status' => 'success', 'results' => $results];

    $response->getBody()->write(json_encode($data));
    return $response->withStatus(200);
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

    $pdo = createConnection();

    $errores = [];

    $sql = 'SELECT * FROM inquilinos WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $inquilinoId);
    $query->execute();
    if ($query->rowCount() == 0) {
        $errores['inquilino_id'] = 'No existe un inquilino con el ID provisto';
    } else {
        $inquilino = $query->fetch(PDO::FETCH_ASSOC);
        if (!$inquilino['activo']) {
            $errores['inquilino'] =
                'No se puede crear la reserva porque el inquilino no está activo';
        }
    }

    $sql = 'SELECT * FROM propiedades WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $propiedadId);
    $query->execute();
    if ($query->rowCount() == 0) {
        $errores['propiedad_id'] = 'No existe una propiedad con el ID provisto';
    }

    if (!empty($errores)) {
        $response
            ->getBody()
            ->write(json_encode(['status' => 'failure', 'errors' => $errores]));
        return $response->withStatus(400);
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
});

//tipos de propiedades--------------------------------------

$app->get('/tipo_propiedades', function (Request $request, Response $response) {
    $pdo = createConnection(); //obtiene la direccion de la base de datos
    $sql = 'SELECT * FROM tipo_propiedades';
    $query = $pdo->query($sql); //query(consulta)

    $data = $query->fetchAll(PDO::FETCH_ASSOC); //fetchAll:arreglo asociativo

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'data' => $data,
        ])
    );

    return $response->withStatus(200);
});

$app->post('/tipo_propiedades', function (
    Request $request,
    Response $response
) {
    $data = $request->getParsedBody();
    $nombre = $data['nombre'] ?? null;

    $errores = obtenerErrores(
        ['nombre' => $nombre],
        ['nombre' => v::notOptional()->stringType()]
    );

    if (!empty($errores)) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'errors' => $errores,
            ])
        );
        return $response->withStatus(400);
    }

    $pdo = createConnection();
    $sql = "SELECT * FROM tipo_propiedades
        WHERE nombre = :nombre";

    $query = $pdo->prepare($sql);
    $query->bindValue(':nombre', $nombre);
    $query->execute();

    if ($query->rowCount() > 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => 'el campo nombre no se puede repetir',
            ])
        );

        return $response->withStatus(400);
    }

    $sql = "INSERT INTO tipo_propiedades (nombre)
        values (:nombre)";

    $query = $pdo->prepare($sql);
    $query->bindValue(':nombre', $nombre);
    $query->execute();

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'tipo de propiedad creada',
        ])
    );
    return $response->withStatus(200);
});

$app->put('/tipo_propiedades/{id}', function (
    request $request,
    response $response,
    array $args
) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $nombre = $data['nombre'] ?? null;

    $validaciones = [
        'id' => v::notOptional()->regex('/^[0-9]+$/'),
        'nombre' => v::notOptional()->stringType(),
    ];

    $errores = obtenerErrores($args, $validaciones);

    if (!empty($errores)) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'errores' => $errores,
            ])
        );
        return $response->withStatus(400);
    }
    $pdo = createConnection();
    $sql = "SELECT * FROM tipo_propiedades
        WHERE id = :id";

    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();

    if ($query->rowCount() == 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => 'no existe una propiedad con el ID provisto',
            ])
        );
        return $response->withStatus(400);
    }

    $sql = 'SELECT * FROM localidades WHERE nombre = :nombre AND id != :id'; //busco si existe un mismo nombre con otra ID
    $query = $pdo->prepare($sql);
    $query->bindParam(':id', $id);
    $query->bindParam(':nombre', $nombre);
    $query->execute();
    if ($query->rowCount() > 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => 'ya existe otra propiedad con el mismo nombre',
            ])
        );
        return $response->withStatus(400);
    }

    $sql = "UPDATE tipo_propiedades
        SET nombre = :nombre
        WHERE id = :id";

    $query = $pdo->prepare($sql);
    $query->bindValue(':nombre', $nombre);
    $query->bindValue(':id', $id);
    $query->execute();

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'se actualizo con exito',
        ])
    );

    return $response->withStatus(200);
});

$app->delete('/tipo_propiedades/{id}', function (
    request $request,
    response $response,
    array $args
) {
    $id = $args['id'];

    $errores = obtenerErrores(
        ['id' => $id],
        ['id' => v::notOptional()->regex('/^[0-9]+$/')]
    );

    if (!empty($errores)) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'errores' => $errores,
            ])
        );
        return $response->withStatus(400);
    }

    $pdo = createConnection();
    $sql = "SELECT * FROM tipo_propiedades
        WHERE id = :id";

    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();

    if ($query->rowCount() == 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => 'no existe una propiedad con el ID provisto',
            ])
        );
        return $response->withStatus(400);
    }

    $sql = "DELETE * FROM tipo_propiedades
        WHERE id = :id";

    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'se ELIMINO la propiedad',
        ])
    );
    return $response->withStatus(200);
});

//inquilinos--------------------------------------------

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

$app->post('/inquilinos', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $validaciones = [
        'id' => v::notOptional()->intType(),
        'documento' => v::notoptional()->stringType()->length(null, 20),
        'apellido' => v::notOptional()->stringType()->length(null, 15),
        'nombre' => v::notOptional()->stringType()->length(null, 25),
        'email' => v::notOptional()->stringType()->length(null, 20),
        'activo' => v::notOptional()->boolType(),
    ];

    $errores = obtenerErrores($data, $validaciones);

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

    if ($query > 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => 'no se puede repetir el Documento',
            ])
        );
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
            $i++;
        }
    }

    $sql =
        'INSERT into inquilinos (' .
        $stringCampos .
        ')
        values (' .
        $stringValores .
        ')';

    $pdo->query($sql);

    $response->getBody()->write(
        json_encode([
            'status' => 'succes',
            'message' => 'propiedad creada',
        ])
    );

    return $response->withStatus(200);
});

$app->put('/inquilinos/{id}', function (
    Request $request,
    Response $response,
    array $args
) {
    $data = array_intersect_key(
        $request->getParsedBody() ?? [],
        array_flip(['id', 'documento', 'apellido', 'nombre', 'email', 'activo'])
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

    $documento = $data['documento'] ?? null;
    $id = $args['id'] ?? null;

    $verificacion = [
        'id' => v::notOptional()->intType(),
        'documento' => v::notOptional()->stringType(),
        'nombre' => v::notOptional()->stringType(),
        'email' => v::notOptional()->stringType(),
        'activo' => v::notOptional()->boolType(),
    ];

    $errores = obtenerErrores([...$data, 'id' => $id], $verificacion);

    if (!empty($errores)) {
        //esta vacia?
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => $errores,
            ])
        );
        return $response->withStatus(400);
    }

    $pdo = createConnection();
    $sql = 'SELECT * From inquilinos
        where id = :id';

    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();

    if ($query->rowCount() == 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'error' => 'no existe nignun inquilino con la id provista',
            ])
        );
        return $response->withstatus(400);
    }

    $sql = 'SELECT * FROM inquilinos
        WHERE documento = :documento';

    $query = $pdo->prepare($sql);
    $query->bindValue(':documento', $documento);
    $query->execute();

    if ($query->rowCount() == 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'error' => 'no existe ningun el documento provisto',
            ])
        );
        return $response->withStatus(400);
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
            $i++;
        }
    }
    $sql = "UPDATE propiedades 
            SET ' .$stringActualizaciones .' 
            WHERE id = :id and documento = :documento";

    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->bindValue(':documento', $documento);
    $query->execute();

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'se actualizo los inquilinos',
        ])
    );

    return $response->withStatus(200);
});

$app->run();
