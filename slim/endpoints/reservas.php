<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../utilidades/strings_sql.php';
require_once __DIR__ . '/../validaciones/reserva.php';

$app->get('/reservas', function (Request $request, Response $response) {
    $pdo = createConnection();
    $sql = 'SELECT * FROM reservas';
    $query = $pdo->query($sql);
    $results = $query->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as &$reserva) { //& pasado como como referencia
        $sql = 'SELECT * fROM propiedades WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $reserva['propiedad_id']);
        $query->execute();
        $propiedad = $query->fetch(PDO::FETCH_ASSOC);
        unset($reserva['propiedad_id']);
        $reserva['propiedad'] = $propiedad;

        $sql = 'SELECT * FROM inquilinos WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id',$reserva['inquilino_id']);
        $query->execute();
        $inquilino = $query->fetch(PDO::FETCH_ASSOC);
        unset($reserva['inquilino_id']);
        $reserva['inquilino'] = $inquilino;
    }

    $data = ['status' => 'success', 'data' => $results];

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

    $errores = obtenerErrores(
        $data,
        validaciones_reserva,
        mensajes_error_reserva
    );
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

    $sql = 'SELECT * FROM inquilinos WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $inquilinoId);
    $query->execute();
    if ($query->rowCount() == 0) {
        throw new Exception('No existe un inquilino con el ID provisto', 404);
    } else {
        $inquilino = $query->fetch(PDO::FETCH_ASSOC);
        if ($inquilino['activo'] == false) {
            throw new Exception(
                'No se puede crear la reserva porque el inquilino no está activo',
                400
            );
        }
    }

    $sql = 'SELECT * FROM propiedades WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $propiedadId);
    $query->execute();
    if ($query->rowCount() == 0) {
        throw new Exception('No existe una propiedad con el ID provisto', 404);
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

$app->put('/reservas/{id:[0-9]+}', function (
    Request $request,
    Response $response,
    array $args
) {
    $data = array_intersect_key(
        $request->getParsedBody() ?? [],
        array_flip([
            'propiedad_id',
            'inquilino_id',
            'fecha_desde',
            'cantidad_noches',
        ])
    );
    $id = $args['id'];

    if (empty($data)) {
        throw new Exception('No se ingresó ningun valor', 400);
    }

    $errores = obtenerErrores(
        $data,
        validaciones_reserva,
        mensajes_error_reserva,
        true
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
    if ($query->rowCount() == 0) {
        throw new Exception('No existe una reserva con el ID provisto', 404);
    } else {
        $reserva = $query->fetch(PDO::FETCH_ASSOC);
    }

    if (isset($data['id_inquilino'])) {
        $sql = 'SELECT * FROM inquilinos WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $data['inquilino_id']);
        $query->execute();
        if ($query->rowCount() == 0) {
            throw new Exception(
                'No existe un inquilino con el ID provisto',
                404
            );
        } else {
            $inquilino = $query->fetch(PDO::FETCH_ASSOC);
            if (!$inquilino['activo']) {
                $errores['inquilino'] = throw new Exception(
                    'No se puede crear la reserva porque el inquilino no está activo',
                    400
                );
            }
        }
    }

    if (isset($data['propiedad_id'])) {
        $sql = 'SELECT * FROM propiedades WHERE id = :id';
        $query = $pdo->prepare($sql);
        $query->bindValue(':id', $data['propiedad_id']);
        $query->execute();
        if ($query->rowCount() == 0) {
            throw new Exception(
                'No existe una propiedad con el ID provisto',
                404
            );
        } else {
            $propiedad = $query->fetch(PDO::FETCH_ASSOC);
        }
    }

    //actualiza el valor total con los valores valor_noche y cantidad_noches
    if (isset($data['propiedad_id']) || isset($data['cantidad_noches'])) {
        $cantidadNoches = isset($data['cantidad_noches'])
            ? $data['cantidad_noches']
            : $reserva['cantidad_noches'];
        if (!isset($propiedad)) {
            $sql =
                'SELECT * FROM propiedades WHERE id = ' .
                $reserva['propiedad_id'];
            $query = $pdo->query($sql);
            $propiedad = $query->fetch(PDO::FETCH_ASSOC);
        }

        $valorNoche = $propiedad['valor_noche'];
        $data['valor_total'] = $cantidadNoches * $valorNoche;
    }
    $stringActualizaciones = construirStringActualizaciones($data);
    $sql = 'UPDATE reservas SET ' . $stringActualizaciones . ' WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindParam(':id', $id);
    $query->execute();

    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'Reserva actualizada',
        ])
    );
    return $response;
});

$app->delete('/reservas/{id:[0-9]+}', function (
    Request $request,
    Response $response,
    array $args
) {
    $id = $args['id'];
    $pdo = createConnection();

    $sql = 'SELECT * FROM reservas WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    if ($query->rowCount() == 0) {
        throw new Exception('No existe una reserva con el ID provisto', 404);
    }
    $reserva = $query->fetch(PDO::FETCH_ASSOC);
    if ($reserva['fecha_desde'] >= date('Y-m-d')) {
        throw new Exception(
            'No se puede eliminar la reserva porque ya comenzó',
            400
        );
    }

    $sql = 'DELETE FROM reservas WHERE id = :id';
    $query = $pdo->prepare($sql);
    $query->bindValue(':id', $id);
    $query->execute();
    $response->getBody()->write(
        json_encode([
            'status' => 'success',
            'message' => 'Se eliminó la reserva',
        ])
    );
    return $response->withStatus(200);
});
