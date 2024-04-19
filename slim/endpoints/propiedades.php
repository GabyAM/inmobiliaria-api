<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

require_once __DIR__ . '/../utilidades/strings_sql.php';

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

    $stringInserciones = construirStringInserciones($data);
    $sql = 'INSERT INTO propiedades ' . $stringInserciones;
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

    $stringActualizaciones = construirStringActualizaciones($data);
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

    $sql = 'SELECT * FROM reservas WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    if ($query->rowCount() > 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'error' =>
                    'No se puede eliminar la propiedad porque una reserva la está usando',
            ])
        );
        return $response->withStatus(400);
    }
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
