-- Agrega precio, categoría y descripción a los productos de ambas tiendas.
-- Se ejecuta sola al crear la BD desde cero (después de init.sql, por orden alfabético).
-- Es seguro correrla de nuevo sobre una BD existente:
--   docker compose exec -T db mariadb -uroot -proot < db-init/migracion_campos.sql

ALTER TABLE tienda_a.productos
    ADD COLUMN IF NOT EXISTS precio DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER nombre,
    ADD COLUMN IF NOT EXISTS categoria VARCHAR(50) NULL AFTER precio,
    ADD COLUMN IF NOT EXISTS descripcion VARCHAR(255) NULL AFTER categoria;

ALTER TABLE tienda_b.productos
    ADD COLUMN IF NOT EXISTS precio DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER nombre,
    ADD COLUMN IF NOT EXISTS categoria VARCHAR(50) NULL AFTER precio,
    ADD COLUMN IF NOT EXISTS descripcion VARCHAR(255) NULL AFTER categoria;

-- Datos de ejemplo para el producto inicial (solo si aún no tiene categoría)
UPDATE tienda_a.productos SET precio = 120.00, categoria = 'Ropa', descripcion = 'Camisa de algodón manga larga'
    WHERE id = 1 AND categoria IS NULL;
UPDATE tienda_b.productos SET precio = 120.00, categoria = 'Ropa', descripcion = 'Camisa de algodón manga larga'
    WHERE id = 1 AND categoria IS NULL;
