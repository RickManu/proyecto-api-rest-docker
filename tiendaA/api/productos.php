<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$host = "db";
$user = "root";
$pass = "root";
$db   = "tienda_a";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión con la base de datos"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':    handleGet($conn); break;
    case 'POST':   handlePost($conn); break;
    case 'PUT':    handlePut($conn); break;
    case 'DELETE': handleDelete($conn); break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Método no permitido"]);
}

function handleGet($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    if ($id) {
        $stmt = $conn->prepare("SELECT id, nombre, stock FROM productos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                "id" => (int)$row['id'],
                "nombre" => $row['nombre'],
                "stock" => (int)$row['stock']
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Producto no encontrado"]);
        }
        $stmt->close();
    } else {
        $result = $conn->query("SELECT id, nombre, stock FROM productos");
        $productos = [];
        while ($row = $result->fetch_assoc()) {
            $productos[] = [
                "id" => (int)$row['id'],
                "nombre" => $row['nombre'],
                "stock" => (int)$row['stock']
            ];
        }
        echo json_encode($productos, JSON_UNESCAPED_UNICODE);
    }
}

function handlePost($conn) {
    $data = json_decode(file_get_contents("php://input"), true);
    $nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
    $stock  = isset($data['stock']) ? intval($data['stock']) : null;

    if ($nombre === '' || $stock === null || $stock < 0) {
        http_response_code(400);
        echo json_encode(["error" => "Se requiere 'nombre' y 'stock' válidos"]);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO productos (nombre, stock) VALUES (?, ?)");
    $stmt->bind_param("si", $nombre, $stock);
    $stmt->execute();

    http_response_code(201);
    echo json_encode(["mensaje" => "Producto creado", "id" => $conn->insert_id, "nombre" => $nombre, "stock" => $stock], JSON_UNESCAPED_UNICODE);
    $stmt->close();
}

function handlePut($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $data = json_decode(file_get_contents("php://input"), true);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Se requiere 'id' en la URL"]);
        return;
    }

    $nombre = $data['nombre'] ?? null;
    $stock  = isset($data['stock']) ? intval($data['stock']) : null;

    if ($nombre === null && $stock === null) {
        http_response_code(400);
        echo json_encode(["error" => "Se requiere al menos 'nombre' o 'stock'"]);
        return;
    }

    if ($nombre !== null && $stock !== null) {
        $stmt = $conn->prepare("UPDATE productos SET nombre = ?, stock = ? WHERE id = ?");
        $stmt->bind_param("sii", $nombre, $stock, $id);
    } elseif ($nombre !== null) {
        $stmt = $conn->prepare("UPDATE productos SET nombre = ? WHERE id = ?");
        $stmt->bind_param("si", $nombre, $id);
    } else {
        $stmt = $conn->prepare("UPDATE productos SET stock = ? WHERE id = ?");
        $stmt->bind_param("ii", $stock, $id);
    }
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(["mensaje" => "Producto actualizado", "id" => $id]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Producto no encontrado o sin cambios"]);
    }
    $stmt->close();
}

function handleDelete($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Se requiere 'id' en la URL"]);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(["mensaje" => "Producto eliminado", "id" => $id]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Producto no encontrado"]);
    }
    $stmt->close();
}

$conn->close();
?>
