<?php
header("Content-Type: application/json; charset=utf-8");

include_once("controllers.php");

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$resource = $_GET["resource"] ?? "";
$id = isset($_GET["id"]) ? filter_var($_GET["id"], FILTER_VALIDATE_INT) : null;
$userId = isset($_GET["user_id"]) ? filter_var($_GET["user_id"], FILTER_VALIDATE_INT) : null;

// Postman debe enviar PUT/POST como Body > raw > JSON.
$rawBody = file_get_contents("php://input");
$body = json_decode($rawBody, true);
if (!is_array($body)) {
    // Permite también Body > form-data o x-www-form-urlencoded.
    $body = $_POST;
}

switch ($method) {
    case "GET":
        if ($resource === "users") {
            $result = getUsers($id !== false ? $id : null);
        } elseif ($resource === "products") {
            $result = getProducts($userId !== false ? $userId : null);
        } else {
            $result = apiResult("error", "resource debe ser users o products", null, 400);
        }
        break;

    case "POST":
        if ($resource === "users") {
            $result = registerUser($body);
        } elseif ($resource === "products") {
            $result = registerProduct($body);
        } else {
            $result = apiResult("error", "resource debe ser users o products", null, 400);
        }
        break;

    case "PUT":
        if ($id === null || $id === false) {
            $result = apiResult("error", "id es obligatorio en la URL para actualizar", null, 400);
        } elseif ($resource === "users") {
            $result = updateUser((int)$id, $body);
        } elseif ($resource === "products") {
            $result = updateProduct((int)$id, $body);
        } else {
            $result = apiResult("error", "resource debe ser users o products", null, 400);
        }
        break;

    case "DELETE":
        if ($id === null || $id === false) {
            $result = apiResult("error", "id es obligatorio en la URL para eliminar", null, 400);
        } elseif ($resource === "users") {
            $result = deleteUser((int)$id);
        } elseif ($resource === "products") {
            $result = deleteProduct((int)$id);
        } else {
            $result = apiResult("error", "resource debe ser users o products", null, 400);
        }
        break;

    default:
        $result = apiResult("error", "Método HTTP no permitido. Usa GET, POST, PUT o DELETE", null, 405);
        header("Allow: GET, POST, PUT, DELETE");
}

$httpCode = $result["_http_code"] ?? 200;
unset($result["_http_code"]);
http_response_code($httpCode);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
