-- ==========================================================
-- ESTRUCTURA Y DATOS DE LA BÓVEDA AUREUS
-- Generado para despliegue del proyecto Final
-- ==========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. CREACIÓN DE LA BASE DE DATOS
CREATE DATABASE IF NOT EXISTS `aureus_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE `aureus_db`;

-- --------------------------------------------------------
-- ESTRUCTURA DE TABLAS
-- --------------------------------------------------------

CREATE TABLE `categoria` (
  `id_categoria` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `avatar_url` varchar(255) DEFAULT 'https://static.vecteezy.com/system/resources/thumbnails/057/883/614/small/elegant-artistic-ancient-roman-bust-element-png.png',
  `biografia` text DEFAULT NULL,
  `rol` enum('visitante','comprador','artista','admin') DEFAULT 'visitante',
  `es_artista` tinyint(1) NOT NULL DEFAULT 0,
  `saldo_disponible` decimal(10,2) NOT NULL DEFAULT 0.00,
  `saldo_bloqueado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `iban` varchar(34) DEFAULT NULL,
  `dni` varchar(20) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `obra` (
  `id_obra` int(11) NOT NULL AUTO_INCREMENT,
  `id_vendedor` int(11) NOT NULL,
  `id_comprador` int(11) DEFAULT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen_url` varchar(255) NOT NULL,
  `precio_inicial` decimal(10,2) NOT NULL,
  `precio_actual` decimal(10,2) NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `estado` enum('PENDIENTE','ACTIVA','FINALIZADA','DESIERTA','ENTREGADA','RECHAZADA') NOT NULL DEFAULT 'PENDIENTE',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_obra`),
  KEY `fk_obra_vendedor` (`id_vendedor`),
  KEY `fk_obra_comprador` (`id_comprador`),
  KEY `fk_obra_categoria` (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `puja` (
  `id_puja` int(11) NOT NULL AUTO_INCREMENT,
  `id_obra` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_puja`),
  KEY `fk_puja_obra` (`id_obra`),
  KEY `fk_puja_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `log_sistema` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `detalle` text NOT NULL,
  `ip` varchar(45) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `fk_log_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------
-- SEEDERS: VOLCADO DE DATOS MAESTROS
-- --------------------------------------------------------

-- Solo dejamos una categoría: Pintura
INSERT INTO `categoria` (`nombre`, `descripcion`) VALUES
('Pintura', 'Óleos, acuarelas, frescos y obras pictóricas exclusivas.');

-- Modificamos los seeders para incluir biografías (Lore de Aureus)
INSERT INTO `usuario` (`nombre`, `email`, `password`, `biografia`, `rol`, `es_artista`, `saldo_disponible`, `saldo_bloqueado`, `dni`, `telefono`, `activo`) VALUES
('Admin Aureus', 'admin@aureus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Primer Cónsul del Senado de Aureus. Guardián de las transacciones y curador del catálogo imperial.', 'admin', 0, 0.00, 0.00, '99999999Z', '600000000', 1),
('Leonardo Digital', 'artista@aureus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maestro del lienzo y el píxel. Mis obras buscan la luz en la oscuridad del neoclasicismo digital.', 'artista', 1, 400000.00, 0.00, '88888888X', '600111111', 1),
('Comprador Alvarus', 'alvarus@aureus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mecenas empedernido. Buscando siempre la próxima obra maestra para mi colección privada.', 'comprador', 0, 4500.00, 3500.00, '66666666A', '600333333', 1),
('Paula', 'paula@aureus.com', '$2y$10$qLZMY3jFCIuRaynYW80Fw.L1zuvFykUitehlkKoXpkO9H1XHmP.E2', NULL, 'comprador', 0, 0.00, 0.00, '123124', '123123', 1);

-- Inyectamos las 9 obras forzando que todas pertenezcan a la categoría 1 (Pintura)
INSERT INTO `obra` (`id_vendedor`, `id_comprador`, `id_categoria`, `titulo`, `descripcion`, `imagen_url`, `precio_inicial`, `precio_actual`, `fecha_fin`, `estado`) VALUES
(2, NULL, 1, 'El Trazo Absoluto', 'Obra abstracta contemporánea. Un grueso y expresivo trazo blanco.', 'https://images.unsplash.com/photo-1622542796254-5b9c46ab0d2f?auto=format&fit=crop&q=80&w=800', 4200.00, 4250.00, DATE_ADD(NOW(), INTERVAL 24 HOUR), 'ACTIVA'),
(2, NULL, 1, 'Estudio de Sombras', 'Obra clásica de luces y sombras. El contraste define la crudeza de la época.', 'https://images.unsplash.com/flagged/photo-1572392640988-ba48d1a74457?auto=format&fit=crop&q=80&w=800', 14500.00, 16050.00, DATE_ADD(NOW(), INTERVAL 6 DAY), 'ACTIVA'),
(2, NULL, 1, 'Visión Cromática de David', 'Intervención pop sobre la escultura clásica.', 'https://images.unsplash.com/photo-1536924940846-227afb31e2a5?auto=format&fit=crop&q=80&w=800', 45000.00, 52050.00, DATE_ADD(NOW(), INTERVAL 11 DAY), 'ACTIVA'),
(2, NULL, 1, 'Profundidad Oceánica', 'Lienzo contemporáneo de gran formato.', 'https://images.unsplash.com/photo-1515405295579-ba7b45403062?auto=format&fit=crop&q=80&w=800', 1500.00, 3000.00, DATE_ADD(NOW(), INTERVAL 4 DAY), 'ACTIVA'),
(2, NULL, 1, 'Ruinas del Viejo Mundo', 'Imponente pintura romántica.', 'https://images.unsplash.com/photo-1578301978018-3005759f48f7?auto=format&fit=crop&q=80&w=800', 25000.00, 35000.00, DATE_ADD(NOW(), INTERVAL 2 DAY), 'ACTIVA'),
(2, NULL, 1, 'Ríos de Oro y Ónice', 'Abstracción de fluidos preciosos.', 'https://plus.unsplash.com/premium_photo-1664111766922-9477d493893e?auto=format&fit=crop&q=80&w=800', 120000.00, 155000.00, DATE_ADD(NOW(), INTERVAL 23 HOUR), 'ACTIVA'),
(2, NULL, 1, 'El Valle del Acueducto', 'Paisaje clásico del siglo XIX.', 'https://images.unsplash.com/photo-1549289524-06cf8837ace5?auto=format&fit=crop&q=80&w=800', 8500.00, 11000.00, DATE_ADD(NOW(), INTERVAL 100 MINUTE), 'ACTIVA'),
(2, NULL, 1, 'Bodegón de la Abundancia', 'Naturaleza muerta con frutos y flores.', 'https://images.unsplash.com/photo-1579783902915-f0b0de2c2eb3?auto=format&fit=crop&q=80&w=800', 18000.00, 22000.00, DATE_SUB(NOW(), INTERVAL 1 HOUR), 'DESIERTA'),
(2, 3, 1, 'Tempestad en Empaste', 'Textura que simula el choque violento de las olas.', 'https://plus.unsplash.com/premium_photo-1664013263421-91e3a8101259?auto=format&fit=crop&q=80&w=800', 3500.00, 3500.00, DATE_SUB(NOW(), INTERVAL 2 HOUR), 'FINALIZADA');

-- Insertamos la puja para que la BD sea 100% coherente (Alvarus pujó por la obra ID 9)
INSERT INTO `puja` (`id_obra`, `id_usuario`, `monto`, `fecha`) VALUES
(9, 3, 3500.00, DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- --------------------------------------------------------
-- RESTRICCIONES (FKs)
-- --------------------------------------------------------

ALTER TABLE `obra`
  ADD CONSTRAINT `fk_obra_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id_categoria`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_obra_comprador` FOREIGN KEY (`id_comprador`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_obra_vendedor` FOREIGN KEY (`id_vendedor`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

ALTER TABLE `puja`
  ADD CONSTRAINT `fk_puja_obra` FOREIGN KEY (`id_obra`) REFERENCES `obra` (`id_obra`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_puja_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

ALTER TABLE `log_sistema`
  ADD CONSTRAINT `fk_log_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL;

COMMIT;