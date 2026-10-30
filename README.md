# API CRUD básica en PHP y MySQL

API REST sin interfaz gráfica que gestiona **usuarios** y **productos** con PHP, MySQL y respuestas en JSON. Cada usuario puede tener varios productos asociados mediante una clave foránea, y la API expone operaciones de listar, consultar, crear, actualizar y eliminar para ambos recursos.

## Arquitectura
- **Router (`api.php`):** Punto de entrada único. Lee `resource` e `id` desde la URL, detecta el método HTTP y despacha la petición al controlador correspondiente. También define el código HTTP de la respuesta.
- **Controllers (`controllers.php`):** Contienen la lógica de negocio. Validan los datos recibidos, ejecutan las consultas con **prepared statements** y devuelven respuestas uniformes con `status`, `message` y `data`.
- **Connection (`connection.php`):** Centraliza la conexión `mysqli`, configura `utf8mb4` y devuelve errores de conexión en formato JSON.
- **Schema (`schema.sql`):** Define las tablas `users` y `products` con claves únicas, claves foráneas y `ON DELETE RESTRICT` para evitar borrar usuarios con productos pendientes.

## Stack
- PHP 8 (`mysqli`, `password_hash`)
- MySQL (FK con `ON DELETE RESTRICT`)
- JSON como formato de intercambio
- Postman para probar los endpoints

## Cómo ejecutar
1. Importar `schema.sql` en phpMyAdmin para crear la base `crud_api`.
2. Ajustar credenciales (`host`, `user`, `pass`, `db`) en `connection.php`.
3. Copiar la carpeta del proyecto dentro de `htdocs` (XAMPP) o ejecutar `php -S localhost:8000` desde la raíz del proyecto.
4. Base URL: `http://localhost/CRUD_API/api.php` (o `http://localhost:8000/api.php` con el servidor integrado).
5. En Postman, para `POST` y `PUT` seleccionar `Body → raw → JSON`.

## Endpoints
- `GET api.php?resource=users[&id=]` — Lista usuarios con sus productos, o consulta uno específico.
- `POST api.php?resource=users` — Registra un usuario (contraseña hasheada con `password_hash`).
- `PUT api.php?resource=users&id=` — Actualiza un usuario; `password` es opcional.
- `DELETE api.php?resource=users&id=` — Elimina un usuario solo si no tiene productos (responde `409` si los tiene).
- `GET api.php?resource=products[&user_id=]` — Lista todos los productos o filtra por usuario.
- `POST api.php?resource=products` — Registra un producto asociado a un `user_id` existente.
- `PUT api.php?resource=products&id=` — Actualiza un producto.
- `DELETE api.php?resource=products&id=` — Elimina un producto.

## Códigos HTTP
`200` OK · `201` Created · `400` Bad Request · `404` Not Found · `405` Method Not Allowed · `409` Conflict · `500` Internal Server Error.

