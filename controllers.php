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