<?php

function connection(): mysqli
{
    $host = "localhost";
    $user = "root";
    $pass = ""; 
    $db   = "crud_api";

    mysqli_report(MYSQLI_REPORT_OFF);
    $connect = new mysqli($host, $user, $pass, $db);

    if ($connect->connect_error) {
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "status" => "error",
            "message" => "Error de conexión a la base de datos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $connect->set_charset("utf8mb4");
    return $connect;
}
