<?php
header("Content-Type: application/json; charset=UTF-8");

// En Docker el host NO es 127.0.0.1: es el nombre del servicio en docker-compose.yml
$host = "db";
$user = "root";
$pass = "root";
$db   = "tienda_a";

// Conexión a la base de datos de Tienda A
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión con la base de datos"]);
    exit;
}

// Obtener id por parámetro GET (?id=1)
$producto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT id, nombre, stock FROM productos WHERE id = ?");
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "id"     => (int)$row['id'],
        "nombre" => $row['nombre'],
        "stock"  => (int)$row['stock']
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Producto no encontrado"]);
}

$stmt->close();
$conn->close();
?>
