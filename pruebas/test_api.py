"""
Pruebas automáticas de la API REST de productos (Tienda A y Tienda B).

Uso (desde la carpeta del proyecto):
    python3 pruebas/test_api.py                 # prueba contra el VPS (IP de frontend/config.js)
    python3 pruebas/test_api.py localhost       # prueba contra Docker en tu máquina

Crea productos de prueba, los modifica y los elimina al final;
no toca los productos que ya existían.
"""
import json
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path


def servidor_por_defecto():
    config = Path(__file__).resolve().parent.parent / "frontend" / "config.js"
    m = re.search(r"const SERVIDOR = '([^']+)'", config.read_text(encoding="utf-8"))
    return m.group(1) if m else "localhost"


SERVIDOR = sys.argv[1] if len(sys.argv) > 1 else servidor_por_defecto()
TIENDAS = {
    "Tienda A": f"http://{SERVIDOR}:8081/api/productos.php",
    "Tienda B": f"http://{SERVIDOR}:8082/productos.php",
}
SUFIJO = str(int(time.time()))  # nombres únicos para no chocar con datos reales

VERDE, ROJO, GRIS, NEGRITA, FIN = "\033[32m", "\033[31m", "\033[90m", "\033[1m", "\033[0m"
resultados = {"ok": 0, "fallo": 0}


def pedir(metodo, url, cuerpo=None, crudo=None):
    """Hace la petición y devuelve (código HTTP, JSON de respuesta)."""
    datos = crudo.encode() if crudo is not None else (json.dumps(cuerpo).encode() if cuerpo is not None else None)
    req = urllib.request.Request(url, data=datos, method=metodo)
    if datos is not None:
        req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req, timeout=10) as r:
            codigo, texto = r.status, r.read().decode()
    except urllib.error.HTTPError as e:
        codigo, texto = e.code, e.read().decode()
    try:
        return codigo, json.loads(texto) if texto else None
    except json.JSONDecodeError:
        return codigo, texto


def verificar(descripcion, condicion, detalle=""):
    if condicion:
        resultados["ok"] += 1
        print(f"  {VERDE}✔{FIN} {descripcion}")
    else:
        resultados["fallo"] += 1
        print(f"  {ROJO}✘ {descripcion}{FIN}")
        if detalle:
            print(f"    {GRIS}{detalle}{FIN}")


def probar_tienda(nombre_tienda, url):
    print(f"\n{NEGRITA}== {nombre_tienda} ({url}) =={FIN}")
    creados = []
    try:
        # ---------- Crear (POST) ----------
        print(f"{GRIS}Registro (POST){FIN}")
        nuevo = {"nombre": f"Prueba Mochila {SUFIJO}", "precio": 249.99, "categoria": "Accesorios",
                 "descripcion": "Producto creado por test_api.py", "stock": 7}
        codigo, r = pedir("POST", url, nuevo)
        verificar("crea un producto válido → 201", codigo == 201, f"{codigo} {r}")
        producto = (r or {}).get("producto") or {}
        pid = producto.get("id")
        if pid:
            creados.append(pid)
        verificar("devuelve el producto con todos sus campos",
                  all(producto.get(k) == v for k, v in nuevo.items()), f"{producto}")

        codigo, r = pedir("POST", url, {"nombre": f"Prueba Minimo {SUFIJO}", "stock": 0})
        verificar("crea solo con nombre y stock (precio 0 por defecto) → 201",
                  codigo == 201 and r["producto"]["precio"] == 0, f"{codigo} {r}")
        if codigo == 201:
            creados.append(r["producto"]["id"])

        codigo, r = pedir("POST", url, {"nombre": f"prueba mochila {SUFIJO}".upper(), "precio": 1, "stock": 1})
        verificar("rechaza nombre duplicado (sin importar mayúsculas) → 409", codigo == 409, f"{codigo} {r}")

        codigo, r = pedir("POST", url, {"nombre": "   ", "precio": -5, "stock": "diez"})
        detalles = (r or {}).get("detalles", {})
        verificar("rechaza datos inválidos → 422", codigo == 422, f"{codigo} {r}")
        verificar("indica el error de cada campo (nombre, precio, stock)",
                  {"nombre", "precio", "stock"} <= set(detalles), f"{detalles}")

        codigo, r = pedir("POST", url, {"nombre": f"Prueba Decimales {SUFIJO}", "precio": 10.555, "stock": 1})
        verificar("rechaza precio con más de 2 decimales → 422", codigo == 422, f"{codigo} {r}")

        codigo, r = pedir("POST", url, {"nombre": "x" * 101, "stock": 1})
        verificar("rechaza nombre de más de 100 caracteres → 422", codigo == 422, f"{codigo} {r}")

        codigo, r = pedir("POST", url, {"nombre": f"Prueba Stock {SUFIJO}", "stock": 1.5})
        verificar("rechaza stock con decimales → 422", codigo == 422, f"{codigo} {r}")

        codigo, r = pedir("POST", url, {"stock": 3})
        verificar("rechaza producto sin nombre → 422", codigo == 422, f"{codigo} {r}")

        codigo, r = pedir("POST", url, crudo="{esto no es json")
        verificar("rechaza JSON mal formado → 400", codigo == 400, f"{codigo} {r}")

        # ---------- Consultar (GET) ----------
        print(f"{GRIS}Consulta (GET){FIN}")
        codigo, lista = pedir("GET", url)
        verificar("lista todos los productos → 200", codigo == 200 and isinstance(lista, list), f"{codigo}")
        verificar("el producto creado aparece en la lista",
                  any(p["id"] == pid for p in lista or []), "no se encontró en la lista")

        codigo, r = pedir("GET", f"{url}?id={pid}")
        verificar("obtiene un producto por id → 200", codigo == 200 and r.get("id") == pid, f"{codigo} {r}")

        codigo, r = pedir("GET", f"{url}?id=999999")
        verificar("producto inexistente → 404", codigo == 404, f"{codigo} {r}")

        codigo, r = pedir("GET", f"{url}?id=abc")
        verificar("id no numérico → 400", codigo == 400, f"{codigo} {r}")

        # ---------- Modificar (PUT) ----------
        print(f"{GRIS}Cambios (PUT){FIN}")
        cambios = {"nombre": f"Prueba Mochila XL {SUFIJO}", "precio": 299.5, "categoria": "Viaje",
                   "descripcion": "Editado", "stock": 12}
        codigo, r = pedir("PUT", f"{url}?id={pid}", cambios)
        verificar("actualiza todos los campos → 200", codigo == 200, f"{codigo} {r}")
        codigo, r = pedir("GET", f"{url}?id={pid}")
        verificar("los cambios quedaron guardados",
                  all(r.get(k) == v for k, v in cambios.items()), f"{r}")

        codigo, r = pedir("PUT", f"{url}?id={pid}", {"stock": 3})
        verificar("actualización parcial (solo stock) → 200", codigo == 200, f"{codigo} {r}")
        codigo, r = pedir("GET", f"{url}?id={pid}")
        verificar("cambia solo el stock y conserva lo demás",
                  r.get("stock") == 3 and r.get("nombre") == cambios["nombre"] and r.get("precio") == 299.5, f"{r}")

        codigo, r = pedir("PUT", f"{url}?id={pid}", {"stock": 3})
        verificar("guardar sin cambios no da error → 200", codigo == 200, f"{codigo} {r}")

        codigo, r = pedir("PUT", f"{url}?id={pid}", {"categoria": "", "descripcion": None})
        verificar("permite vaciar categoría y descripción",
                  codigo == 200 and r["producto"]["categoria"] is None and r["producto"]["descripcion"] is None,
                  f"{codigo} {r}")

        otro = creados[1] if len(creados) > 1 else None
        if otro:
            codigo, r = pedir("PUT", f"{url}?id={otro}", {"nombre": cambios["nombre"]})
            verificar("no permite renombrar a un nombre que ya existe → 409", codigo == 409, f"{codigo} {r}")

        codigo, r = pedir("PUT", f"{url}?id={pid}", {"stock": -1})
        verificar("rechaza stock negativo → 422", codigo == 422, f"{codigo} {r}")

        codigo, r = pedir("PUT", f"{url}?id={pid}", {})
        verificar("rechaza actualización sin campos → 400", codigo == 400, f"{codigo} {r}")

        codigo, r = pedir("PUT", f"{url}?id=999999", {"stock": 1})
        verificar("actualizar inexistente → 404", codigo == 404, f"{codigo} {r}")

        codigo, r = pedir("PUT", url, {"stock": 1})
        verificar("actualizar sin id en la URL → 400", codigo == 400, f"{codigo} {r}")

        # ---------- Eliminar (DELETE) ----------
        print(f"{GRIS}Eliminación (DELETE){FIN}")
        for i in list(creados):
            codigo, r = pedir("DELETE", f"{url}?id={i}")
            verificar(f"elimina el producto #{i} → 200", codigo == 200, f"{codigo} {r}")
            if codigo == 200:
                creados.remove(i)

        codigo, r = pedir("GET", f"{url}?id={pid}")
        verificar("el producto eliminado ya no existe → 404", codigo == 404, f"{codigo} {r}")

        codigo, r = pedir("DELETE", f"{url}?id={pid}")
        verificar("eliminarlo otra vez → 404", codigo == 404, f"{codigo} {r}")

        # ---------- Otros ----------
        print(f"{GRIS}Otros{FIN}")
        codigo, r = pedir("PATCH", url, {"stock": 1})
        verificar("método no soportado (PATCH) → 405", codigo == 405, f"{codigo} {r}")

        req = urllib.request.Request(url, method="OPTIONS")
        req.add_header("Origin", "http://localhost:5500")
        req.add_header("Access-Control-Request-Method", "PUT")
        with urllib.request.urlopen(req, timeout=10) as res:
            permite = res.headers.get("Access-Control-Allow-Origin")
            verificar("CORS: el frontend local puede usar la API", res.status == 204 and permite == "*",
                      f"{res.status} Allow-Origin={permite}")
    except Exception as e:  # p. ej. servidor apagado
        verificar("la prueba terminó sin errores inesperados", False, repr(e))
    finally:
        for i in creados:  # limpieza si algo falló a mitad de camino
            pedir("DELETE", f"{url}?id={i}")


def probar_inventario():
    print(f"\n{NEGRITA}== Inventario combinado (cURL entre tiendas) =={FIN}")
    try:
        _, a = pedir("GET", f"http://{SERVIDOR}:8081/api/stock.php?id=1")
        _, b = pedir("GET", f"http://{SERVIDOR}:8082/productos.php?id=1")
        codigo, inv = pedir("GET", f"http://{SERVIDOR}:8082/inventario.php?id=1")
        verificar("inventario.php responde → 200", codigo == 200, f"{codigo} {inv}")
        verificar(f"stock total = Tienda B ({b['stock']}) + Tienda A ({a['stock']}) = {b['stock'] + a['stock']}",
                  inv["stock_local"] == b["stock"] and inv["stock_remoto"] == a["stock"]
                  and inv["stock_total"] == a["stock"] + b["stock"], f"{inv}")
    except Exception as e:
        verificar("la prueba terminó sin errores inesperados", False, repr(e))


if __name__ == "__main__":
    print(f"{NEGRITA}Probando la API en {SERVIDOR}{FIN}")
    for nombre, url in TIENDAS.items():
        probar_tienda(nombre, url)
    probar_inventario()

    total = resultados["ok"] + resultados["fallo"]
    color = VERDE if resultados["fallo"] == 0 else ROJO
    print(f"\n{color}{NEGRITA}{resultados['ok']}/{total} pruebas correctas, {resultados['fallo']} fallos{FIN}")
    sys.exit(1 if resultados["fallo"] else 0)
