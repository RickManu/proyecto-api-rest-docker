<?php
$producto_id = 1;

// 1. Obtener stock local de Tienda B
$host = "db";       // nombre del servicio de BD en docker-compose.yml
$user = "root";
$pass = "root";
$db   = "tienda_b";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Error conectando a Tienda B: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT nombre, stock FROM productos WHERE id = ?");
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$res = $stmt->get_result();
$prod_b = $res->fetch_assoc();

$stock_local = $prod_b ? (int)$prod_b['stock'] : 0;
$nombre_prod = $prod_b ? $prod_b['nombre'] : "Desconocido";

$stmt->close();
$conn->close();

// 2. Consumir la API de Tienda A usando cURL
// IMPORTANTE: dentro de la red de Docker se usa el nombre del servicio
// (tiendaA) y el puerto INTERNO del contenedor (80), no el puerto publicado al host.
$url = "http://tiendaa/api/stock.php?id=" . $producto_id;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
curl_close($ch);
// 3. Procesar respuesta de Tienda A
$data_a = json_decode($response, true);
$stock_remoto = isset($data_a['stock']) ? (int)$data_a['stock'] : 0;

// 4. Calcular inventario total combinado
$stock_total = $stock_local + $stock_remoto;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario Combinado - Tienda B</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f8fafc; color: #1e293b; }
        .card { background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 500px; }
        h2 { margin-top: 0; color: #334155; }
        ul { list-style: none; padding: 0; }
        li { padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        .total { font-weight: bold; color: #4338ca; font-size: 1.1em; border-bottom: none; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Inventario: <?= htmlspecialchars($nombre_prod) ?> (ID: <?= $producto_id ?>)</h2>
        <ul>
            <li>Stock Local (Tienda B): <strong><?= $stock_local ?> unidades</strong></li>
            <li>Stock Remoto (Tienda A via cURL): <strong><?= $stock_remoto ?> unidades</strong></li>
            <li class="total">Stock Total Combinado: <?= $stock_total ?> unidades</li>
        </ul>
    </div>
</body>
</html>
