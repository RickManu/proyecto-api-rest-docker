<?php
// API REST de productos compartida por Tienda A y Tienda B.
// Cada tienda la incluye indicando su propia base de datos:
//   require '/var/www/comun/productos_api.php';
//   atenderProductos('tienda_a');

const LIMITES = ['nombre' => 100, 'categoria' => 50, 'descripcion' => 255];
const PRECIO_MAX = 99999999.99;
const STOCK_MAX = 1000000;

function atenderProductos(string $baseDatos): void {
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'OPTIONS') {
        http_response_code(204);
        return;
    }

    try {
        $conn = new mysqli("db", "root", "root", $baseDatos);
        $conn->set_charset("utf8mb4");
    } catch (mysqli_sql_exception $e) {
        responder(500, ["error" => "Error de conexión con la base de datos"]);
        return;
    }

    try {
        switch ($method) {
            case 'GET':    listarOObtener($conn); break;
            case 'POST':   crear($conn); break;
            case 'PUT':    actualizar($conn); break;
            case 'DELETE': eliminar($conn); break;
            default:
                header("Allow: GET, POST, PUT, DELETE, OPTIONS");
                responder(405, ["error" => "Método no permitido"]);
        }
    } catch (mysqli_sql_exception $e) {
        responder(500, ["error" => "Error interno en la base de datos"]);
    }
    $conn->close();
}

// ---------- Operaciones ----------

function listarOObtener(mysqli $conn): void {
    if (isset($_GET['id'])) {
        $id = idDeUrl();
        if ($id === null) return;
        $producto = buscarPorId($conn, $id);
        if ($producto) responder(200, $producto);
        else responder(404, ["error" => "Producto no encontrado"]);
        return;
    }

    $result = $conn->query("SELECT id, nombre, precio, categoria, descripcion, stock FROM productos ORDER BY id");
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = formatear($row);
    }
    responder(200, $productos);
}

function crear(mysqli $conn): void {
    $data = leerJson();
    if ($data === null) return;

    [$campos, $errores] = validar($data, true);
    if ($errores) {
        responder(422, ["error" => "Datos inválidos", "detalles" => $errores]);
        return;
    }
    if (nombreDuplicado($conn, $campos['nombre'])) {
        responder(409, ["error" => "Ya existe un producto con ese nombre", "detalles" => ["nombre" => "Ya existe un producto con ese nombre"]]);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO productos (nombre, precio, categoria, descripcion, stock) VALUES (?, ?, ?, ?, ?)");
    $precio = $campos['precio'] ?? 0;
    $categoria = $campos['categoria'] ?? null;
    $descripcion = $campos['descripcion'] ?? null;
    $stmt->bind_param("sdssi", $campos['nombre'], $precio, $categoria, $descripcion, $campos['stock']);
    $stmt->execute();
    $stmt->close();

    responder(201, ["mensaje" => "Producto creado", "producto" => buscarPorId($conn, $conn->insert_id)]);
}

function actualizar(mysqli $conn): void {
    $id = idDeUrl();
    if ($id === null) return;
    $data = leerJson();
    if ($data === null) return;

    if (!buscarPorId($conn, $id)) {
        responder(404, ["error" => "Producto no encontrado"]);
        return;
    }

    [$campos, $errores] = validar($data, false);
    if ($errores) {
        responder(422, ["error" => "Datos inválidos", "detalles" => $errores]);
        return;
    }
    if (!$campos) {
        responder(400, ["error" => "No se envió ningún campo para actualizar"]);
        return;
    }
    if (isset($campos['nombre']) && nombreDuplicado($conn, $campos['nombre'], $id)) {
        responder(409, ["error" => "Ya existe un producto con ese nombre", "detalles" => ["nombre" => "Ya existe un producto con ese nombre"]]);
        return;
    }

    // Construye el UPDATE solo con los campos recibidos (los nombres vienen de validar(), no del cliente)
    $tipos = ['nombre' => 's', 'precio' => 'd', 'categoria' => 's', 'descripcion' => 's', 'stock' => 'i'];
    $sets = [];
    $valores = [];
    $tiposBind = '';
    foreach ($campos as $campo => $valor) {
        $sets[] = "$campo = ?";
        $valores[] = $valor;
        $tiposBind .= $tipos[$campo];
    }
    $valores[] = $id;
    $tiposBind .= 'i';

    $stmt = $conn->prepare("UPDATE productos SET " . implode(', ', $sets) . " WHERE id = ?");
    $stmt->bind_param($tiposBind, ...$valores);
    $stmt->execute();
    $stmt->close();

    responder(200, ["mensaje" => "Producto actualizado", "producto" => buscarPorId($conn, $id)]);
}

function eliminar(mysqli $conn): void {
    $id = idDeUrl();
    if ($id === null) return;

    $stmt = $conn->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $borrados = $stmt->affected_rows;
    $stmt->close();

    if ($borrados > 0) responder(200, ["mensaje" => "Producto eliminado", "id" => $id]);
    else responder(404, ["error" => "Producto no encontrado"]);
}

// ---------- Validación ----------

// Devuelve [campos limpios, errores por campo]. En creación exige nombre y stock.
function validar(array $data, bool $esCreacion): array {
    $campos = [];
    $errores = [];

    if (array_key_exists('nombre', $data)) {
        $nombre = is_string($data['nombre']) ? trim($data['nombre']) : '';
        if ($nombre === '') $errores['nombre'] = "El nombre es obligatorio";
        elseif (mb_strlen($nombre) > LIMITES['nombre']) $errores['nombre'] = "Máximo " . LIMITES['nombre'] . " caracteres";
        else $campos['nombre'] = $nombre;
    } elseif ($esCreacion) {
        $errores['nombre'] = "El nombre es obligatorio";
    }

    if (array_key_exists('stock', $data)) {
        $stock = $data['stock'];
        if (!is_int($stock) && !(is_string($stock) && preg_match('/^\d+$/', $stock))) $errores['stock'] = "El stock debe ser un número entero";
        elseif ((int)$stock < 0) $errores['stock'] = "El stock no puede ser negativo";
        elseif ((int)$stock > STOCK_MAX) $errores['stock'] = "El stock no puede superar " . STOCK_MAX;
        else $campos['stock'] = (int)$stock;
    } elseif ($esCreacion) {
        $errores['stock'] = "El stock es obligatorio";
    }

    if (array_key_exists('precio', $data)) {
        $precio = $data['precio'];
        if (!is_int($precio) && !is_float($precio) && !(is_string($precio) && is_numeric($precio))) $errores['precio'] = "El precio debe ser un número";
        elseif ((float)$precio < 0) $errores['precio'] = "El precio no puede ser negativo";
        elseif ((float)$precio > PRECIO_MAX) $errores['precio'] = "El precio es demasiado alto";
        elseif (round((float)$precio, 2) != (float)$precio) $errores['precio'] = "Máximo 2 decimales";
        else $campos['precio'] = round((float)$precio, 2);
    }

    foreach (['categoria', 'descripcion'] as $campo) {
        if (!array_key_exists($campo, $data)) continue;
        $valor = $data[$campo];
        if ($valor !== null && !is_string($valor)) { $errores[$campo] = "Debe ser texto"; continue; }
        $valor = $valor === null ? '' : trim($valor);
        if (mb_strlen($valor) > LIMITES[$campo]) $errores[$campo] = "Máximo " . LIMITES[$campo] . " caracteres";
        else $campos[$campo] = $valor === '' ? null : $valor;
    }

    return [$campos, $errores];
}

function nombreDuplicado(mysqli $conn, string $nombre, int $excluirId = 0): bool {
    $stmt = $conn->prepare("SELECT 1 FROM productos WHERE LOWER(nombre) = LOWER(?) AND id <> ?");
    $stmt->bind_param("si", $nombre, $excluirId);
    $stmt->execute();
    $existe = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $existe;
}

// ---------- Utilidades ----------

function buscarPorId(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT id, nombre, precio, categoria, descripcion, stock FROM productos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? formatear($row) : null;
}

function formatear(array $row): array {
    return [
        "id"          => (int)$row['id'],
        "nombre"      => $row['nombre'],
        "precio"      => (float)$row['precio'],
        "categoria"   => $row['categoria'],
        "descripcion" => $row['descripcion'],
        "stock"       => (int)$row['stock']
    ];
}

function idDeUrl(): ?int {
    $id = $_GET['id'] ?? '';
    if (!preg_match('/^[1-9]\d*$/', (string)$id)) {
        responder(400, ["error" => "Se requiere un 'id' válido en la URL (?id=1)"]);
        return null;
    }
    return (int)$id;
}

function leerJson(): ?array {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) {
        responder(400, ["error" => "El cuerpo debe ser un JSON válido"]);
        return null;
    }
    return $data;
}

function responder(int $codigo, array $cuerpo): void {
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
}
