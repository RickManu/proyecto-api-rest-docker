<?php
header("Content-Type: application/json; charset=UTF-8");

$producto_id = isset($_GET['id']) ? intval($_GET['id']) : 1;

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

$stmt = $conn->prepare("SELECT nombre, stock FROM productos WHERE id = ?");
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$prod_b = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$url = "http://tiendaa/api/stock.php?id=" . $producto_id;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
curl_close($ch);

$data_a = json_decode($response, true);

$stock_local  = $prod_b ? (int)$prod_b['stock'] : 0;
$stock_remoto = isset($data_a['stock']) ? (int)$data_a['stock'] : 0;
$nombre = $prod_b['nombre'] ?? ($data_a['nombre'] ?? "Desconocido");

echo json_encode([
    "id"           => $producto_id,
    "nombre"       => $nombre,
    "stock_local"  => $stock_local,
    "stock_remoto" => $stock_remoto,
    "stock_total"  => $stock_local + $stock_remoto
], JSON_UNESCAPED_UNICODE);
?>
