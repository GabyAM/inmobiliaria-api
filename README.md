# inmobiliaria-api

Primer proyecto de la materia seminario de lenguajes\
**Realizado por:** Gabriel Miranda y Santiago Álvarez.

hecho en PHP con el framework Slim.\
Validación de campos con la librería [Respect\Validation](https://respect-validation.readthedocs.io/en/2.3/).

## Endpoints

### Localidades
  - Listar: GET /localidades
  - Crear: POST /localidades\
    ***Recibe nombre***     
  - Editar: PUT /localidades/$id\
    ***Recibe nombre***
  - Eliminar: DELETE /localidades/$id
### Tipo propiedades
  - Listar: GET /tipo_propiedades
  - Crear: POST /tipo_propiedades\
    ***Recibe nombre***
  - Editar: PUT /tipo_propiedades/$id\
    ***Recibe nombre***
  - Eliminar: DELETE /tipo_propiedades/$id 
### Propiedades
  - Listar: GET /propiedades\
    ***Recibe parámetros: disponible, localidad, fecha_inicio_disponibilidad, cantidad_huespedes***
  - Ver: GET /propiedades/$id
  - Crear: POST /propiedades\
    ***Recibe: domicilio, localidad_id, cantidad_habitaciones, cantidad_banios, cochera, cantidad_huespedes, fecha_inicio_disponibilidad, cantidad_dias, disponible,         valor_noche, tipo_propiedad_id, imagen, tipo_imagen***
  - Editar: PUT /propiedades/$id\
    ***Recibe: domicilio, localidad_id, cantidad_habitaciones, cantidad_banios, cochera, cantidad_huespedes, fecha_inicio_disponibilidad, cantidad_dias, disponible,         valor_noche, tipo_propiedad_id, imagen, tipo_imagen***
  - Eliminar: DELETE /propiedades/$id
### Inquilinos
  - Listar: GET /inquilinos
  - Ver: GET /inquilinos/$id
  - Ver historial: GET /inquilinos/$id/reservas
  - Crear: POST /inquilinos\
    ***Recibe: nombre_usuario, apellido, nombre, email, activo***
  - Editar: PUT /inquilinos/$id\
    ***Recibe: nombre_usuario, apellido, nombre, email, activo***
  - Eliminar: DELETE /inquilinos/$id
### Reservas
  - Listar: GET /reservas
  - Crear: POST /reservas\
    ***Recibe: propiedad_id, inquilino_id, fecha_desde, cantidad_noches***
  - Editar: PUT /reservas/$id\
    ***Recibe: propiedad_id, inquilino_id, fecha_desde, cantidad_noches***
  - Eliminar: DELETE /reservas/$id
