<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

$host = "db";
$user = "root";
$pass = "root";
$db   = "tienda_b";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión con la base de datos"]);
    exit;
}

$producto_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$nuevo_stock = isset($_POST['stock']) ? intval($_POST['stock']) : null;

if ($producto_id <= 0 || $nuevo_stock === null || $nuevo_stock < 0) {
    http_response_code(400);
    echo json_encode(["error" => "Parámetros inválidos"]);
    exit;
}

$stmt = $conn->prepare("UPDATE productos SET stock = ? WHERE id = ?");
$stmt->bind_param("ii", $nuevo_stock, $producto_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["mensaje" => "Stock actualizado", "id" => $producto_id, "stock" => $nuevo_stock]);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Sin cambios o producto no encontrado"]);
}

$stmt->close();
$conn->close();
?>