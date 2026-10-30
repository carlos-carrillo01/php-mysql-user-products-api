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