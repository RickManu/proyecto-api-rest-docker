# Mini API REST Distribuida con PHP y MariaDB — versión Docker

Adaptación a Docker del ejercicio original (que usaba XAMPP en macOS). La lógica de PHP
es la misma; lo único que cambia es *dónde* corre cada pieza y *cómo* se comunican los
contenedores entre sí.

## Qué cambia respecto a XAMPP

| Concepto | XAMPP | Docker |
|---|---|---|
| Servidor web + PHP | Proceso de XAMPP en tu Mac | Contenedor `tiendaA` y contenedor `tiendaB` (imagen `php:8.2-apache`) |
| MariaDB | Proceso de XAMPP | Contenedor `db` (imagen oficial `mariadb:11`) |
| Carpeta de proyecto | `/Applications/XAMPP/xamppfiles/htdocs/...` | Carpeta local `tiendaA/` y `tiendaB/`, montada dentro del contenedor |
| Host de conexión a la BD en PHP | `127.0.0.1` | `db` (nombre del servicio en `docker-compose.yml`; Docker resuelve el nombre por DNS interno) |
| URL que usa cURL desde Tienda B | `http://localhost/tiendaA/api/stock.php` | `http://tiendaA/api/stock.php` (nombre del servicio + puerto interno 80, no el puerto publicado al host) |
| Acceso desde el navegador | `http://localhost/tiendaA/...` | `http://localhost:8081/...` (Tienda A) y `http://localhost:8082/...` (Tienda B) |

## Estructura del proyecto

```
proyecto-api-rest-docker/
├── docker-compose.yml
├── php.Dockerfile
├── db-init/
│   └── init.sql          # crea tienda_a y tienda_b, y las llena con datos de prueba
├── tiendaA/
│   └── api/
│       └── stock.php
└── tiendaB/
    └── consulta.php
```

## Paso 1: Requisitos

Instala Docker Desktop (incluye Docker Compose) desde https://www.docker.com/products/docker-desktop/
No necesitas instalar PHP ni MariaDB en tu máquina: todo corre dentro de los contenedores.

## Paso 2: Levantar los contenedores

Desde la carpeta `proyecto-api-rest-docker/`:

```bash
docker compose up -d --build
```

Esto hace tres cosas:
1. Construye la imagen de PHP con la extensión `mysqli` habilitada (`php.Dockerfile`).
2. Levanta MariaDB y ejecuta automáticamente `db-init/init.sql` la primera vez que arranca
   (crea `tienda_a` y `tienda_b` con su producto de prueba, igual que el script SQL original).
3. Levanta `tiendaA` en el puerto 8081 y `tiendaB` en el puerto 8082.

Verifica que los tres contenedores estén corriendo:

```bash
docker compose ps
```

## Paso 3: Prueba de Tienda A (API GET)

Abre en tu navegador:

```
http://localhost:8081/api/stock.php?id=1
```

Respuesta esperada:
```json
{"id":1,"nombre":"Camisa","stock":15}
```

## Paso 4: Prueba de Tienda B (cURL + suma)

Abre en tu navegador:

```
http://localhost:8082/consulta.php
```

Resultado esperado en pantalla:
- Stock Local (Tienda B): 10 unidades
- Stock Remoto (Tienda A via cURL): 15 unidades
- Stock Total Combinado: 25 unidades

## Paso 4b: Backend en VPS + frontend local

El backend (`tiendaA`, `tiendaB` y `db`) corre en un VPS con Docker; el frontend
(`frontend/`) corre en la máquina local y consume la API por HTTP.

**En el VPS** (Ubuntu con Docker instalado):

```bash
git clone https://github.com/RickManu/proyecto-api-rest-docker.git
cd proyecto-api-rest-docker
docker compose up -d --build
```

Abre los puertos **8081** y **8082** en el firewall del VPS. El puerto 3306 de MariaDB
queda ligado a `127.0.0.1`, así que la base de datos no es accesible desde internet.

**En tu máquina local:**

1. Edita `frontend/config.js` y cambia `SERVIDOR` por la IP de tu VPS.
2. Sirve la carpeta del frontend:
   ```bash
   cd frontend
   python3 -m http.server 5500
   ```
3. Abre `http://localhost:5500` en el navegador.

Los endpoints de la API envían `Access-Control-Allow-Origin: *` para que el frontend
local pueda consumirlos desde otro origen (CORS).

## Puntos de Evaluación (igual que el ejercicio original)

- **API GET funcional (30%)**: Tienda A responde con JSON estructurado al consultar el ID del producto.
- **cURL entre nodos (30%)**: Tienda B realiza una llamada HTTP GET exitosa al servicio de Tienda A (contenedor a contenedor, por nombre de servicio).
- **Suma de stock correcta (25%)**: 10 + 15 = 25.
- El 15% restante normalmente evalúa orden del repo, README y buenas prácticas — por eso conviene subir también `docker-compose.yml` y este README.

## Paso 5: Subir a GitHub

```bash
cd proyecto-api-rest-docker
git init
git add .
git commit -m "Solución ejercicio práctico API distribuida y cURL (Docker)"
git branch -M main
git remote add origin <URL_DE_TU_REPOSITORIO_GITHUB>
git push -u origin main
```

Un `.gitignore` ya está incluido para no subir datos de MariaDB ni archivos temporales.

## Usando Claude Code para este ejercicio

Si quieres que Claude Code te acompañe mientras trabajas en tu máquina (en vez de solo leer
este resultado), instálalo y ábrelo dentro de esta misma carpeta del proyecto:

```bash
npm install -g @anthropic-ai/claude-code
cd proyecto-api-rest-docker
claude
```

Desde ahí puedes pedirle cosas como:
- "Levanta los contenedores con docker compose y revisa los logs si algo falla"
- "Explícame línea por línea qué hace stock.php"
- "Agrega un segundo producto de prueba y un endpoint que liste todos los productos"
- "Escribe las pruebas para verificar que la suma de stock sea correcta"

Claude Code puede ejecutar `docker compose up`, leer los logs de los contenedores y editar
los archivos PHP directamente en tu entorno, lo cual es más rápido que copiar/pegar comandos
manualmente en la terminal.
