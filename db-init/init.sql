-- Base de datos Tienda A
CREATE DATABASE IF NOT EXISTS tienda_a;
USE tienda_a;

CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    stock INT
);

INSERT INTO productos (id, nombre, stock) VALUES (1, 'Camisa', 15);

-- Base de datos Tienda B
CREATE DATABASE IF NOT EXISTS tienda_b;
USE tienda_b;

CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    stock INT
);

INSERT INTO productos (id, nombre, stock) VALUES (1, 'Camisa', 10);
