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