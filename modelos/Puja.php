<?php
/**
 * AUREUS - Proyecto Intermodular
 * Capa: MODELO (Lógica de Datos y Transacciones)
 * Archivo: modelos/Puja.php
 * Descripción: Gestiona el historial de pujas y la transacción financiera crítica (ACID)
 * asegurando la consistencia del libro mayor y la correcta aplicación de comisiones.
 */

class Puja
{

    private $db;

    public function __construct($conexion)
    {
        $this->db = $conexion;
    }

    /**
     * MOTOR TRANSACCIONAL ACID: Procesa una puja de forma segura.
     * Aplica el 12% de Prima del Comprador a los fondos bloqueados.
     */
    public function realizarPuja($id_obra, $id_usuario, $monto_puja, $ip_usuario)
    {
        $this->db->begin_transaction();

        try {
            // 💰 CÁLCULO DE LA PRIMA: Lo que realmente sale de la cartera (Puja + 12%)
            $total_a_bloquear = $monto_puja * 1.12;

            // PASO 1: Bloqueo Pesimista de Cartera
            $sql_saldo = "SELECT saldo_disponible, saldo_bloqueado FROM usuario WHERE id_usuario = ? FOR UPDATE";
            $stmt_saldo = $this->db->prepare($sql_saldo);
            $stmt_saldo->bind_param("i", $id_usuario);
            $stmt_saldo->execute();
            $user_bd = $stmt_saldo->get_result()->fetch_assoc();

            // Verificamos si tiene saldo para la puja + el 12% de comisión
            if ($total_a_bloquear > $user_bd['saldo_disponible']) {
                throw new Exception("Saldo Insuficiente. Necesita " . number_format($total_a_bloquear, 2) . " € (incluye 12% de prima) en la bóveda.");
            }

            // PASO 2: Bloqueo Pesimista de la Obra
            $sql_obra = "SELECT precio_actual, estado FROM obra WHERE id_obra = ? FOR UPDATE";
            $stmt_obra = $this->db->prepare($sql_obra);
            $stmt_obra->bind_param("i", $id_obra);
            $stmt_obra->execute();
            $obra_bd = $stmt_obra->get_result()->fetch_assoc();

            if (!$obra_bd || $obra_bd['estado'] !== 'ACTIVA') {
                throw new Exception("La obra de arte no está disponible para licitación.");
            }

            // PASO 3: Averiguar si hay pujas previas
            $sql_max = "SELECT id_usuario, monto FROM puja WHERE id_obra = ? ORDER BY monto DESC LIMIT 1 FOR UPDATE";
            $stmt_max = $this->db->prepare($sql_max);
            $stmt_max->bind_param("i", $id_obra);
            $stmt_max->execute();
            $puja_maxima = $stmt_max->get_result()->fetch_assoc();

            $precio_a_batir = $obra_bd['precio_actual'];

            // PASO 4: Validación dinámica del mínimo exigido
            if (!$puja_maxima) {
                if ($monto_puja < $precio_a_batir) {
                    throw new Exception("La primera oferta debe ser al menos el precio de salida ({$precio_a_batir}€).");
                }
            } else {
                if ($monto_puja < ($precio_a_batir + 50)) {
                    throw new Exception("La oferta debe superar los {$precio_a_batir}€ en al menos +50€.");
                }
                
                // Si llegamos aquí, devolvemos los fondos al usuario destronado (Devolvemos su puja + su 12%)
                if ($puja_maxima['id_usuario'] != $id_usuario) {
                    $monto_a_devolver = $puja_maxima['monto'] * 1.12;
                    $sql_dev = "UPDATE usuario SET saldo_bloqueado = saldo_bloqueado - ?, saldo_disponible = saldo_disponible + ? WHERE id_usuario = ?";
                    $stmt_dev = $this->db->prepare($sql_dev);
                    $stmt_dev->bind_param("ddi", $monto_a_devolver, $monto_a_devolver, $puja_maxima['id_usuario']);
                    $stmt_dev->execute();
                }
            }

            // PASO 5: Congelación de fondos del nuevo pujador ganador (Puja + 12%)
            $nuevo_disponible = $user_bd['saldo_disponible'] - $total_a_bloquear;
            $nuevo_bloqueado = $user_bd['saldo_bloqueado'] + $total_a_bloquear;

            $sql_cobro = "UPDATE usuario SET saldo_disponible = ?, saldo_bloqueado = ? WHERE id_usuario = ?";
            $stmt_cobro = $this->db->prepare($sql_cobro);
            $stmt_cobro->bind_param("ddi", $nuevo_disponible, $nuevo_bloqueado, $id_usuario);
            $stmt_cobro->execute();

            // PASO 6: Actualización de la cotización y SISTEMA ANTI-SNIPING
            $sql_update_obra = "UPDATE obra 
                                SET precio_actual = ?, 
                                    fecha_fin = IF(fecha_fin < DATE_ADD(NOW(), INTERVAL 5 MINUTE), DATE_ADD(NOW(), INTERVAL 5 MINUTE), fecha_fin) 
                                WHERE id_obra = ?";
            $stmt_update_obra = $this->db->prepare($sql_update_obra);
            $stmt_update_obra->bind_param("di", $monto_puja, $id_obra);
            $stmt_update_obra->execute();

            // PASO 7: Registro histórico en el libro de Pujas (Se registra el valor puro, sin prima)
            $sql_insert = "INSERT INTO puja (id_obra, id_usuario, monto, fecha) VALUES (?, ?, ?, NOW())";
            $stmt_insert = $this->db->prepare($sql_insert);
            $stmt_insert->bind_param("iid", $id_obra, $id_usuario, $monto_puja);
            $stmt_insert->execute();

            // PASO 8: Auditoría de Seguridad
            $detalle_log = "Licitación de $monto_puja € (Bloqueados: $total_a_bloquear €) por la obra ID: $id_obra";
            $sql_log = "INSERT INTO log_sistema (id_usuario, accion, detalle, ip, fecha) VALUES (?, 'PUJA', ?, ?, NOW())";
            $stmt_log = $this->db->prepare($sql_log);
            $stmt_log->bind_param("iss", $id_usuario, $detalle_log, $ip_usuario);
            $stmt_log->execute();

            $this->db->commit();

            return [
                "success" => true,
                "nuevo_saldo_disponible" => $nuevo_disponible,
                "nuevo_saldo_bloqueado" => $nuevo_bloqueado
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public function obtenerTickerGlobal()
    {
        $sql = "SELECT o.titulo AS titulo_obra, u.nombre AS nombre_usuario, p.monto, p.fecha
                FROM puja p
                INNER JOIN obra o ON p.id_obra = o.id_obra
                INNER JOIN usuario u ON p.id_usuario = u.id_usuario
                ORDER BY p.fecha DESC LIMIT 10";

        $resultado = $this->db->query($sql);
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerHistorialPorObra($id_obra)
    {
        $sql = "SELECT u.nombre AS nombre_usuario, p.monto, p.fecha 
                FROM puja p 
                JOIN usuario u ON p.id_usuario = u.id_usuario 
                WHERE p.id_obra = ? 
                ORDER BY p.monto DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_obra);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerPujasMecenas($id_usuario)
    {
        $sql = "SELECT o.id_obra, o.titulo, o.precio_actual, o.fecha_fin, o.estado, o.id_comprador,
                       MAX(p.monto) as mi_monto,
                       CASE
                           WHEN o.estado = 'ACTIVA' AND MAX(p.monto) >= o.precio_actual THEN 'Ganando'
                           WHEN o.estado = 'ACTIVA' AND MAX(p.monto) < o.precio_actual THEN 'Superado'
                           WHEN o.estado = 'FINALIZADA' AND o.id_comprador = ? THEN 'Adjudicada (En tránsito)'
                           WHEN o.estado = 'ENTREGADA' AND o.id_comprador = ? THEN 'En Propiedad'
                           ELSE 'Perdida'
                       END as estado_puja
                FROM puja p
                JOIN obra o ON p.id_obra = o.id_obra
                WHERE p.id_usuario = ?
                GROUP BY o.id_obra, o.titulo, o.precio_actual, o.fecha_fin, o.estado, o.id_comprador
                ORDER BY o.fecha_fin DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iii", $id_usuario, $id_usuario, $id_usuario);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /*
     * MOTOR TRANSACCIONAL PARA ESCROW (Liberación de fondos)
     * Aplica el split: 12% prima pagada, 8% comisión descontada, 20% total para AUREUS.
     */
    public function confirmarRecepcion($id_obra, $id_comprador) {
        $this->db->begin_transaction();

        try {
            $sql_obra = "SELECT precio_actual, id_vendedor, estado, id_comprador FROM obra WHERE id_obra = ? FOR UPDATE";
            $stmt_obra = $this->db->prepare($sql_obra);
            $stmt_obra->bind_param("i", $id_obra);
            $stmt_obra->execute();
            $obra_bd = $stmt_obra->get_result()->fetch_assoc();

            if (!$obra_bd || $obra_bd['estado'] !== 'FINALIZADA') {
                throw new Exception("Operación rechazada. La obra no existe o ya fue liquidada.");
            }
            if ($obra_bd['id_comprador'] != $id_comprador) {
                throw new Exception("Seguridad: Autorización denegada. No eres el adjudicatario de esta obra.");
            }

            $precio_final = $obra_bd['precio_actual'];
            $id_vendedor = $obra_bd['id_vendedor'];

            // 🧮 MATEMÁTICAS DE COMISIONES (Ej: 100€)
            // Comprador tenía bloqueado el precio + 12% (112€)
            $total_bloqueado = $precio_final * 1.12; 
            
            // Vendedor recibe el precio - 8% de comisión (92€)
            $pago_vendedor = $precio_final * 0.92;
            
            // AUREUS recibe la suma de las dos partes (12% + 8% = 20%)
            $comision_aureus = $precio_final * 0.20;

            // 1. COBRO AL COMPRADOR (Destruimos el saldo bloqueado)
            $sql_comprador = "UPDATE usuario SET saldo_bloqueado = saldo_bloqueado - ? WHERE id_usuario = ?";
            $stmt_comprador = $this->db->prepare($sql_comprador);
            $stmt_comprador->bind_param("di", $total_bloqueado, $id_comprador);
            $stmt_comprador->execute();

            // 2. PAGO AL VENDEDOR (Se le inyecta el 92% a su saldo disponible)
            $sql_vendedor = "UPDATE usuario SET saldo_disponible = saldo_disponible + ? WHERE id_usuario = ?";
            $stmt_vendedor = $this->db->prepare($sql_vendedor);
            $stmt_vendedor->bind_param("di", $pago_vendedor, $id_vendedor);
            $stmt_vendedor->execute();

            // 3. PAGO A AUREUS (El 20% va a la cuenta del Admin)
            $sql_aureus = "UPDATE usuario SET saldo_disponible = saldo_disponible + ? WHERE rol = 'admin' LIMIT 1";
            $stmt_aureus = $this->db->prepare($sql_aureus);
            $stmt_aureus->bind_param("d", $comision_aureus);
            $stmt_aureus->execute();

            // 4. MARCAR COMO ENTREGADA
            $sql_estado = "UPDATE obra SET estado = 'ENTREGADA' WHERE id_obra = ?";
            $stmt_estado = $this->db->prepare($sql_estado);
            $stmt_estado->bind_param("i", $id_obra);
            $stmt_estado->execute();

            // 5. REGISTRO EN LOG
            $detalle_log = "Fondos liberados: Obra #{$id_obra}. Vendedor #{$id_vendedor} recibe {$pago_vendedor}€. Comisión AUREUS: {$comision_aureus}€.";
            $sql_log = "INSERT INTO log_sistema (id_usuario, accion, detalle, fecha) VALUES (?, 'LIBERACION_FONDOS', ?, NOW())";
            $stmt_log = $this->db->prepare($sql_log);
            $stmt_log->bind_param("is", $id_comprador, $detalle_log);
            $stmt_log->execute();

            $this->db->commit();
            
            return ["success" => true, "message" => "Fondos liberados y transferidos al artista. ¡Disfruta de tu nueva obra!"];

        } catch (Exception $e) {
            $this->db->rollback();
            return ["success" => false, "message" => $e->getMessage()];
        }
    }
}
?>