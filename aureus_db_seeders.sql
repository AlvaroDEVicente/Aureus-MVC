-- =========================================================================
-- AUREUS - PROYECTO INTERMODULAR (2º DAW)
-- Script de Construcción y Poblado (Seeders) para Pruebas E2E
-- =========================================================================

DROP DATABASE IF EXISTS aureus_db;
CREATE DATABASE aureus_db CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE aureus_db;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+01:00";

-- --------------------------------------------------------
-- 1. TABLA CATEGORÍA
-- --------------------------------------------------------
CREATE TABLE `categoria` (
  `id_categoria` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB;

INSERT INTO `categoria` (`nombre`, `descripcion`) VALUES
('Pintura Clásica', 'Óleos, acuarelas y frescos de estilo neoclásico.'),
('Escultura Digital', 'Modelos 3D y representaciones escultóricas NFT.'),
('Arte Generativo (IA)', 'Obras generadas mediante algoritmos.');

-- --------------------------------------------------------
-- 2. TABLA USUARIO
-- (Contraseña para todos: 'password')
-- --------------------------------------------------------
CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `avatar_url` varchar(255) DEFAULT 'default_avatar.png',
  `biografia` text DEFAULT NULL,
  `rol` enum('visitante','comprador','artista','admin') DEFAULT 'visitante',
  `es_artista` tinyint(1) NOT NULL DEFAULT 0,
  `saldo_disponible` decimal(10,2) NOT NULL DEFAULT 0.00,
  `saldo_bloqueado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `iban` varchar(34) DEFAULT NULL,
  `dni` varchar(20) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`)
) ENGINE=InnoDB;

INSERT INTO `usuario` (`id_usuario`, `nombre`, `email`, `password`, `rol`, `es_artista`, `saldo_disponible`, `saldo_bloqueado`, `dni`, `telefono`) VALUES
(1, 'Admin Aureus', 'admin@aureus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0, 0.00, 0.00, '99999999Z', '600000000'),
(2, 'Leonardo Digital', 'artista@aureus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'artista', 1, 1000.00, 0.00, '88888888X', '600111111'),
(3, 'Comprador Robertus', 'robertus@aureus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'comprador', 0, 5000.00, 1550.00, '77777777R', '600222222'),
(4, 'Comprador Alvarus', 'alvarus@aureus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'comprador', 0, 8000.00, 0.00, '66666666A', '600333333');

-- --------------------------------------------------------
-- 3. TABLA OBRA
-- --------------------------------------------------------
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
  `estado` enum('PENDIENTE','ACTIVA','FINALIZADA') NOT NULL DEFAULT 'PENDIENTE',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_obra`),
  CONSTRAINT `fk_obra_vendedor` FOREIGN KEY (`id_vendedor`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_obra_comprador` FOREIGN KEY (`id_comprador`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_obra_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id_categoria`) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO `obra` (`id_obra`, `id_vendedor`, `id_comprador`, `id_categoria`, `titulo`, `descripcion`, `imagen_url`, `precio_inicial`, `precio_actual`, `fecha_fin`, `estado`) VALUES
(1, 2, NULL, 3, 'El Ocaso de Roma', 'Una representación digital de la caída del imperio. Obra muy cotizada.', 'https://images.unsplash.com/photo-1578301978018-3005759f48f7', 1000.00, 1550.00, DATE_ADD(NOW(), INTERVAL 5 DAY), 'ACTIVA'),
(2, 2, NULL, 2, 'David Cibernético', 'Reimaginación de la escultura clásica.', 'https://images.unsplash.com/photo-1536924940846-227afb31e2a5', 3200.00, 3200.00, DATE_ADD(NOW(), INTERVAL 10 DAY), 'ACTIVA'),
(3, 2, NULL, 1, 'Borrador del Artista', 'Esta obra aún no ha sido validada por el administrador.', 'https://images.unsplash.com/photo-1515405295579-ba7b45403062?auto=format&fit=crop&q=80&w=800', 500.00, 500.00, DATE_ADD(NOW(), INTERVAL 30 DAY), 'PENDIENTE'),
(4, 2, 4, 1, 'Reliquia del Pasado', 'Subasta ya terminada y ganada por Alvarus.', 'https://images.unsplash.com/photo-1533090161767-e6ffed986c88?auto=format&fit=crop&q=80&w=800', 800.00, 1200.00, '2023-01-01 12:00:00', 'FINALIZADA');

-- --------------------------------------------------------
-- 4. TABLA PUJA
-- --------------------------------------------------------
CREATE TABLE `puja` (
  `id_puja` int(11) NOT NULL AUTO_INCREMENT,
  `id_obra` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_puja`),
  CONSTRAINT `fk_puja_obra` FOREIGN KEY (`id_obra`) REFERENCES `obra` (`id_obra`) ON DELETE CASCADE,
  CONSTRAINT `fk_puja_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Simulamos una guerra de pujas por la Obra 1 (El Ocaso de Roma)
INSERT INTO `puja` (`id_obra`, `id_usuario`, `monto`, `fecha`) VALUES
(1, 4, 1050.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),  -- Alvarus pujo 1050
(1, 3, 1100.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),  -- Robertus subio a 1100
(1, 4, 1500.00, DATE_SUB(NOW(), INTERVAL 5 HOUR)), -- Alvarus contraataco con 1500
(1, 3, 1550.00, DATE_SUB(NOW(), INTERVAL 1 HOUR)); -- Robertus va ganando con 1550 (Por eso tiene 1550 bloqueados en su usuario)

-- --------------------------------------------------------
-- 5. TABLA TRANSACCION
-- --------------------------------------------------------
CREATE TABLE `transaccion` (
  `id_transaccion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `tipo` enum('DEPOSITO','RETIRO','COBRO','PAGO') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_transaccion`),
  CONSTRAINT `fk_trans_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 6. TABLA LOG_SISTEMA
-- --------------------------------------------------------
CREATE TABLE `log_sistema` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `detalle` text NOT NULL,
  `ip` varchar(45) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  CONSTRAINT `fk_log_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB;

COMMIT;