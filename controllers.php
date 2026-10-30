<?php
include_once("connection.php");

function apiResult(string $status, string $message, $data = null, int $httpCode = 200): array
{
    $result = [
        "status" => $status,
        "message" => $message,
        "_http_code" => $httpCode
    ];

    if ($data !== null) {
        $result["data"] = $data;
    }

    return $result;
}

function publicUser(array $row): array
{
    // Nunca regresamos el hash de la contraseña al cliente.
    return [
        "id" => (int)$row["id"],
        "name" => $row["name"],
        "lastname" => $row["lastname"],
        "username" => $row["username"],
        "email" => $row["email"]
    ];
}

function getProductsForUser(mysqli $con, int $userId): array
{
    $products = [];
    $stmt = $con->prepare(
        "SELECT id, user_id, name, description, price, stock, created_at
        FROM products WHERE user_id = ? ORDER BY id"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $query = $stmt->get_result();

    while ($row = $query->fetch_assoc()) {
        $row["id"] = (int)$row["id"];
        $row["user_id"] = (int)$row["user_id"];
        $row["price"] = (float)$row["price"];
        $row["stock"] = (int)$row["stock"];
        $products[] = $row;
    }

    $stmt->close();
    return $products;
}

// GET /api.php?resource=users
// GET /api.php?resource=users&id=1
function getUsers(?int $id = null): array
{
    $con = connection();

    if ($id !== null) {
        $stmt = $con->prepare(
            "SELECT id, name, lastname, username, email FROM users WHERE id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $query = $stmt->get_result();
        $row = $query->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $con->close();
            return apiResult("error", "Usuario no encontrado", null, 404);
        }

        $user = publicUser($row);
        $user["products"] = getProductsForUser($con, $id);
        $con->close();
        return apiResult("success", "Usuario consultado correctamente", $user);
    }

    $query = $con->query(
        "SELECT id, name, lastname, username, email FROM users ORDER BY id"
    );
    $users = [];

    while ($row = $query->fetch_assoc()) {
        $user = publicUser($row);
        $user["products"] = getProductsForUser($con, (int)$row["id"]);
        $users[] = $user;
    }

    $con->close();
    return apiResult("success", "Usuarios consultados correctamente", $users);
}

// POST /api.php?resource=users
function registerUser(array $data): array
{
    $name = trim($data["name"] ?? "");
    $lastname = trim($data["lastname"] ?? "");
    $username = trim($data["username"] ?? "");
    $email = trim($data["email"] ?? "");
    $password = $data["password"] ?? "";

    if ($name === "" || $lastname === "" || $username === "" || $email === "" || $password === "") {
        return apiResult("error", "name, lastname, username, email y password son obligatorios", null, 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return apiResult("error", "El correo electrónico no tiene un formato válido", null, 400);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $con = connection();
    $stmt = $con->prepare(
        "INSERT INTO users (name, lastname, username, email, password)
        VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssss", $name, $lastname, $username, $email, $passwordHash);

    if ($stmt->execute()) {
        $id = (int)$con->insert_id;
        $stmt->close();
        $con->close();
        return apiResult("success", "Usuario registrado correctamente", ["id" => $id], 201);
    }

    $duplicate = $stmt->errno === 1062;
    $stmt->close();
    $con->close();
    return apiResult(
        "error",
        $duplicate ? "El username o el correo ya está registrado" : "Error al registrar el usuario",
        null,
        $duplicate ? 409 : 500
    );
}



// PUT /api.php?resource=users&id=1
// Actualiza los datos indicados. password es opcional.
function updateUser(int $id, array $data): array
{
    $name = trim($data["name"] ?? "");
    $lastname = trim($data["lastname"] ?? "");
    $username = trim($data["username"] ?? "");
    $email = trim($data["email"] ?? "");

    if ($id <= 0 || $name === "" || $lastname === "" || $username === "" || $email === "") {
        return apiResult("error", "id en la URL y name, lastname, username y email son obligatorios", null, 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return apiResult("error", "El correo electrónico no tiene un formato válido", null, 400);
    }

    $con = connection();
    $check = $con->prepare("SELECT id FROM users WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$exists) {
        $con->close();
        return apiResult("error", "Usuario no encontrado", null, 404);
    }

    $password = $data["password"] ?? "";
    if ($password !== "") {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $con->prepare(
            "UPDATE users SET name = ?, lastname = ?, username = ?, email = ?, password = ? WHERE id = ?"
        );
        $stmt->bind_param("sssssi", $name, $lastname, $username, $email, $passwordHash, $id);
    } else {
        $stmt = $con->prepare(
            "UPDATE users SET name = ?, lastname = ?, username = ?, email = ? WHERE id = ?"
        );
        $stmt->bind_param("ssssi", $name, $lastname, $username, $email, $id);
    }

    if ($stmt->execute()) {
        $stmt->close();
        $con->close();
        return apiResult("success", "Usuario actualizado correctamente");
    }

    $duplicate = $stmt->errno === 1062;
    $stmt->close();
    $con->close();
    return apiResult("error", $duplicate ? "El username o el correo ya está registrado" : "Error al actualizar el usuario", null, $duplicate ? 409 : 500);
}



// DELETE /api.php?resource=users&id=1
function deleteUser(int $id): array
{
    if ($id <= 0) {
        return apiResult("error", "id es obligatorio en la URL", null, 400);
    }

    $con = connection();
    $check = $con->prepare("SELECT id FROM users WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$exists) {
        $con->close();
        return apiResult("error", "Usuario no encontrado", null, 404);
    }

    $countStmt = $con->prepare("SELECT COUNT(*) AS total FROM products WHERE user_id = ?");
    $countStmt->bind_param("i", $id);
    $countStmt->execute();
    $count = (int)$countStmt->get_result()->fetch_assoc()["total"];
    $countStmt->close();

    if ($count > 0) {
        $con->close();
        return apiResult(
            "error",
            "No se puede eliminar el usuario: primero elimina sus productos",
            ["products_remaining" => $count],
            409
        );
    }

    $stmt = $con->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    $con->close();

    return $ok
        ? apiResult("success", "Usuario eliminado correctamente")
        : apiResult("error", "Error al eliminar el usuario", null, 500);
}


// GET /api.php?resource=products[&user_id=1]
function getProducts(?int $userId = null): array
{
    $con = connection();

    if ($userId !== null) {
        $check = $con->prepare("SELECT id FROM users WHERE id = ?");
        $check->bind_param("i", $userId);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        if (!$exists) {
            $con->close();
            return apiResult("error", "Usuario no encontrado", null, 404);
        }
        $products = getProductsForUser($con, $userId);
    } else {
        $products = [];
        $query = $con->query(
            "SELECT id, user_id, name, description, price, stock, created_at FROM products ORDER BY id"
        );
        while ($row = $query->fetch_assoc()) {
            $row["id"] = (int)$row["id"];
            $row["user_id"] = (int)$row["user_id"];
            $row["price"] = (float)$row["price"];
            $row["stock"] = (int)$row["stock"];
            $products[] = $row;
        }
    }

    $con->close();
    return apiResult("success", "Productos consultados correctamente", $products);
}


// POST /api.php?resource=products
function registerProduct(array $data): array
{
    $userId = filter_var($data["user_id"] ?? null, FILTER_VALIDATE_INT);
    $name = trim($data["name"] ?? "");
    $description = trim($data["description"] ?? "");
    $price = $data["price"] ?? null;
    $stock = filter_var($data["stock"] ?? null, FILTER_VALIDATE_INT);

    if (!$userId || $name === "" || !is_numeric($price) || $stock === false || $stock === null) {
        return apiResult("error", "user_id, name, price numérico y stock entero son obligatorios", null, 400);
    }
    if ((float)$price < 0 || $stock < 0) {
        return apiResult("error", "price y stock deben ser mayores o iguales a cero", null, 400);
    }

    $con = connection();
    $check = $con->prepare("SELECT id FROM users WHERE id = ?");
    $check->bind_param("i", $userId);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();
    if (!$exists) {
        $con->close();
        return apiResult("error", "El usuario indicado no existe", null, 404);
    }

    $price = (float)$price;
    $stmt = $con->prepare(
        "INSERT INTO products (user_id, name, description, price, stock) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issdi", $userId, $name, $description, $price, $stock);

    if ($stmt->execute()) {
        $id = (int)$con->insert_id;
        $stmt->close();
        $con->close();
        return apiResult("success", "Producto registrado correctamente", ["id" => $id], 201);
    }

    $stmt->close();
    $con->close();
    return apiResult("error", "Error al registrar el producto", null, 500);
}


// PUT /api.php?resource=products&id=1
function updateProduct(int $id, array $data): array
{
    $userId = filter_var($data["user_id"] ?? null, FILTER_VALIDATE_INT);
    $name = trim($data["name"] ?? "");
    $description = trim($data["description"] ?? "");
    $price = $data["price"] ?? null;
    $stock = filter_var($data["stock"] ?? null, FILTER_VALIDATE_INT);

    if ($id <= 0 || !$userId || $name === "" || !is_numeric($price) || $stock === false || $stock === null) {
        return apiResult("error", "id en la URL, user_id, name, price numérico y stock entero son obligatorios", null, 400);
    }
    if ((float)$price < 0 || $stock < 0) {
        return apiResult("error", "price y stock deben ser mayores o iguales a cero", null, 400);
    }

    $con = connection();
    $check = $con->prepare("SELECT id FROM products WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();
    if (!$exists) {
        $con->close();
        return apiResult("error", "Producto no encontrado", null, 404);
    }

    $checkUser = $con->prepare("SELECT id FROM users WHERE id = ?");
    $checkUser->bind_param("i", $userId);
    $checkUser->execute();
    $userExists = $checkUser->get_result()->num_rows > 0;
    $checkUser->close();
    if (!$userExists) {
        $con->close();
        return apiResult("error", "El usuario indicado no existe", null, 404);
    }

    $price = (float)$price;
    $stmt = $con->prepare(
        "UPDATE products SET user_id = ?, name = ?, description = ?, price = ?, stock = ? WHERE id = ?"
    );
    $stmt->bind_param("issdii", $userId, $name, $description, $price, $stock, $id);
    $ok = $stmt->execute();
    $stmt->close();
    $con->close();

    return $ok
        ? apiResult("success", "Producto actualizado correctamente")
        : apiResult("error", "Error al actualizar el producto", null, 500);
}


// DELETE /api.php?resource=products&id=1
function deleteProduct(int $id): array
{
    if ($id <= 0) {
        return apiResult("error", "id es obligatorio en la URL", null, 400);
    }

    $con = connection();
    $stmt = $con->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    $con->close();

    if ($deleted === 0) {
        return apiResult("error", "Producto no encontrado", null, 404);
    }
    return apiResult("success", "Producto eliminado correctamente");
}