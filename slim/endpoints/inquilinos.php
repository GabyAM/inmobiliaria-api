<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

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
        'documento' => v::notoptional()->stringType()->length(null, 20),
        'apellido' => v::notOptional()->stringType()->length(null, 15),
        'nombre' => v::notOptional()->stringType()->length(null, 25),
        'email' => v::notOptional()->email()->length(null, 20),
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

    if ($query->rowCount() > 0) {
        $response->getBody()->write(
            json_encode([
                'status' => 'failure',
                'message' => 'no se puede repetir el Documento',
            ])
        );
        return $response;
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
            'status' => 'success',
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

    $id = $args['id'] ?? null;

    $verificacion = [
        'id' => v::regex('/^[0-9]+$/'),
        'documento' => v::stringType()->length(null, 20),
        'apellido' => v::stringType()->length(null, 15),
        'nombre' => v::stringType()->length(null, 25),
        'email' => v::email()->length(null, 20),
        'activo' => v::boolType(),
    ];

    $errores = obtenerErrores([...$data, 'id' => $id], $verificacion, true);

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

    if (isset($data['documento'])) {
        $documento = $data['documento'];
        $sql = 'SELECT * FROM inquilinos WHERE documento = :documento';

        $query = $pdo->prepare($sql);
        $query->bindValue(':documento', $documento);
        $query->execute();

        if ($query->rowCount() > 0) {
            $response->getBody()->write(
                json_encode([
                    'status' => 'failure',
                    'error' => 'No se puede repetir el documento',
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
            $i++;
        }
    }
    $sql =
        'UPDATE inquilinos SET ' . $stringActualizaciones . ' WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'se actualizo el inquilino',
        ])
    );

    return $response->withStatus(200);
});
