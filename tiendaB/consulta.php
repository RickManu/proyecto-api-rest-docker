<?php
$producto_id = 1;

$host = "db";
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

$url = "http://tiendaa/api/stock.php?id=" . $producto_id;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
curl_close($ch);

$data_a = json_decode($response, true);
$stock_remoto = isset($data_a['stock']) ? (int)$data_a['stock'] : 0;

$stock_total = $stock_local + $stock_remoto;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario Combinado - Tienda B</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 40px 20px;
            background: #f1f5f9;
            color: #1e293b;
        }
        .wrap { max-width: 480px; margin: 0 auto; }
        header { margin-bottom: 24px; }
        header h1 { font-size: 1.4em; margin: 0 0 4px; color: #1e293b; }
        header p { margin: 0; color: #64748b; font-size: 0.9em; }
        .card {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card h2 {
            margin: 0 0 16px;
            font-size: 1.05em;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tag {
            font-size: 0.7em;
            font-weight: normal;
            background: #eef2ff;
            color: #4338ca;
            padding: 2px 8px;
            border-radius: 999px;
        }
        ul { list-style: none; padding: 0; margin: 0; }
        li {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .total {
            border-bottom: none;
            margin-top: 4px;
            padding-top: 14px;
            font-weight: bold;
            color: #4338ca;
            font-size: 1.1em;
        }
        label { display: block; font-size: 0.85em; color: #475569; margin-bottom: 6px; }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 1em;
            margin-bottom: 14px;
            background: #fff;
        }
        input:focus, select:focus { outline: none; border-color: #4338ca; }
        button {
            width: 100%;
            padding: 12px;
            background: #4338ca;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #372aa8; }
        button:disabled { background: #a5a3d9; cursor: not-allowed; }
        #mensaje {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.9em;
            display: none;
        }
        #mensaje.ok { display: block; background: #ecfdf5; color: #047857; }
        #mensaje.err { display: block; background: #fef2f2; color: #b91c1c; }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <h1>Control de Stock - Tienda A y Tienda B</h1>
            <p>Proyecto de API REST con Docker</p>
        </header>

        <div class="card">
            <h2>Inventario: <?= htmlspecialchars($nombre_prod) ?> <span class="tag">ID <?= $producto_id ?></span></h2>
            <ul>
                <li><span>Stock Local (Tienda B)</span><strong><?= $stock_local ?></strong></li>
                <li><span>Stock Remoto (Tienda A vía cURL)</span><strong><?= $stock_remoto ?></strong></li>
                <li class="total"><span>Stock Total Combinado</span><span><?= $stock_total ?></span></li>
            </ul>
        </div>

        <div class="card">
            <h2>Actualizar stock</h2>
            <form id="editForm">
                <label>Tienda</label>
                <select name="tienda" id="tiendaSelect">
                    <option value="b">Tienda B (local)</option>
                    <option value="a">Tienda A (remoto, vía cURL)</option>
                </select>

                <label>ID producto</label>
                <input type="number" name="id" value="<?= $producto_id ?>" required>

                <label>Nuevo stock</label>
                <input type="number" name="stock" id="stockInput" min="0" value="<?= $stock_local ?>" required>

                <button type="submit" id="btnActualizar">Actualizar</button>
            </form>
            <div id="mensaje"></div>
        </div>
    </div>

    <script>
        const stockActual = { a: <?= $stock_remoto ?>, b: <?= $stock_local ?> };
        const endpoints = {
            a: 'http://localhost:8081/api/update.php',
            b: 'update.php'
        };

        const select = document.getElementById('tiendaSelect');
        const stockInput = document.getElementById('stockInput');
        const form = document.getElementById('editForm');
        const btn = document.getElementById('btnActualizar');
        const mensaje = document.getElementById('mensaje');

        select.addEventListener('change', () => {
            stockInput.value = stockActual[select.value];
        });

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const tienda = select.value;
            const formData = new FormData();
            formData.append('id', form.id.value);
            formData.append('stock', stockInput.value);

            btn.disabled = true;
            btn.textContent = 'Actualizando...';
            mensaje.className = '';

            try {
                const res = await fetch(endpoints[tienda], { method: 'POST', body: formData });
                const data = await res.json();
                if (res.ok) {
                    mensaje.textContent = 'Actualizado correctamente. Recargando...';
                    mensaje.className = 'ok';
                    setTimeout(() => location.reload(), 800);
                } else {
                    mensaje.textContent = 'Error: ' + (data.error || 'desconocido');
                    mensaje.className = 'err';
                    btn.disabled = false;
                    btn.textContent = 'Actualizar';
                }
            } catch (err) {
                mensaje.textContent = 'No se pudo conectar con el servidor';
                mensaje.className = 'err';
                btn.disabled = false;
                btn.textContent = 'Actualizar';
            }
        });
    </script>
</body>
</html>