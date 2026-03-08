<?php
/**
 * Proyecto Intermodular: AUREUS
 * Capa: Modelo (Lógica de Datos y Transacciones)
 * Archivo: modelos/Puja.php
 * Descripción:
 * Gestiona el historial de pujas y la lógica transaccional financiera (ACID).
 * Asegura la consistencia del libro mayor mediante "Partida Doble" y la 
 * correcta aplicación de retenciones y comisiones.
 */

class Puja
{
    /** @var mysqli Objeto de conexión a la base de datos. */
    private $db;

    /**
     * Constructor de la clase.
     * @param mysqli $conexion Conexión activa a la base de datos.
     */
    public function __construct($conexion)
    {
        $this->db = $conexion;
    }

    /**
     * Ejecuta el proceso de licitación mediante una transacción ACID.
     * Utiliza bloqueos pesimistas (FOR UPDATE) para prevenir condiciones de carrera.
     * Aplica automáticamente una retención del 12% (Prima del Comprador).
     * @param int $id_obra Identificador de la obra.
     * @param int $id_usuario Identificador del postor.
     * @param float $monto_puja Valor ofertado por la obra.
     * @param string $ip_usuario Dirección IP del cliente para registro de auditoría.
     * @return array Estado de la transacción y nuevos saldos en caso de éxito.
     */
    public function realizarPuja($id_obra, $id_usuario, $monto_puja, $ip_usuario)
    {
        $this->db->begin_transaction();

        try {
            $total_a_bloquear = $monto_puja * 1.12;

            // Bloqueo pesimista sobre el saldo del usuario
            $sql_saldo = "SELECT saldo_disponible, saldo_bloqueado FROM usuario WHERE id_usuario = ? FOR UPDATE";
            $stmt_saldo = $this->db->prepare($sql_saldo);
            $stmt_saldo->bind_param("i", $id_usuario);
            $stmt_saldo->execute();
            $user_bd = $stmt_saldo->get_result()->fetch_assoc();

            if ($total_a_bloquear > $user_bd['saldo_disponible']) {
                throw new Exception("Saldo insuficiente. Requiere " . number_format($total_a_bloquear, 2) . " € (incluye 12% de retención).");
            }

            // Bloqueo pesimista sobre el estado de la obra
            $sql_obra = "SELECT precio_actual, estado FROM obra WHERE id_obra = ? FOR UPDATE";
            $stmt_obra = $this->db->prepare($sql_obra);
            $stmt_obra->bind_param("i", $id_obra);
            $stmt_obra->execute();
            $obra_bd = $stmt_obra->get_result()->fetch_assoc();

            if (!$obra_bd || $obra_bd['estado'] !== 'ACTIVA') {
                throw new Exception("La obra no se encuentra disponible para licitación.");
            }

            // Obtención de la puja líder actual
            $sql_max = "SELECT id_usuario, monto FROM puja WHERE id_obra = ? ORDER BY monto DESC LIMIT 1 FOR UPDATE";
            $stmt_max = $this->db->prepare($sql_max);
            $stmt_max->bind_param("i", $id_obra);
            $stmt_max->execute();
            $puja_maxima = $stmt_max->get_result()->fetch_assoc();

            $precio_a_batir = $obra_bd['precio_actual'];

            // Validación de incremento mínimo y liberación de retención al postor superado
            if (!$puja_maxima) {
                if ($monto_puja < $precio_a_batir) {
                    throw new Exception("La oferta inicial debe igualar o superar la tasación base ({$precio_a_batir}€).");
                }
            } else {
                if ($monto_puja < ($precio_a_batir + 50)) {
                    throw new Exception("La nueva oferta requiere un incremento mínimo de 50€ sobre el valor actual.");
                }
                
                if ($puja_maxima['id_usuario'] != $id_usuario) {
                    $monto_a_devolver = $puja_maxima['monto'] * 1.12;
                    $sql_dev = "UPDATE usuario SET saldo_bloqueado = saldo_bloqueado - ?, saldo_disponible = saldo_disponible + ? WHERE id_usuario = ?";
                    $stmt_dev = $this->db->prepare($sql_dev);
                    $stmt_dev->bind_param("ddi", $monto_a_devolver, $monto_a_devolver, $puja_maxima['id_usuario']);
                    $stmt_dev->execute();
                }
            }

            // Actualización de saldos del postor actual
            $nuevo_disponible = $user_bd['saldo_disponible'] - $total_a_bloquear;
            $nuevo_bloqueado = $user_bd['saldo_bloqueado'] + $total_a_bloquear;

            $sql_cobro = "UPDATE usuario SET saldo_disponible = ?, saldo_bloqueado = ? WHERE id_usuario = ?";
            $stmt_cobro = $this->db->prepare($sql_cobro);
            $stmt_cobro->bind_param("ddi", $nuevo_disponible, $nuevo_bloqueado, $id_usuario);
            $stmt_cobro->execute();

            // Actualización del valor de la obra e implementación de prevención Anti-Sniping
            $sql_update_obra = "UPDATE obra 
                                SET precio_actual = ?, 
                                    fecha_fin = IF(fecha_fin < DATE_ADD(NOW(), INTERVAL 5 MINUTE), DATE_ADD(NOW(), INTERVAL 5 MINUTE), fecha_fin) 
                                WHERE id_obra = ?";
            $stmt_update_obra = $this->db->prepare($sql_update_obra);
            $stmt_update_obra->bind_param("di", $monto_puja, $id_obra);
            $stmt_update_obra->execute();

            // Persistencia del historial de licitaciones
            $sql_insert = "INSERT INTO puja (id_obra, id_usuario, monto, fecha) VALUES (?, ?, ?, NOW())";
            $stmt_insert = $this->db->prepare($sql_insert);
            $stmt_insert->bind_param("iid", $id_obra, $id_usuario, $monto_puja);
            $stmt_insert->execute();

            // Registro de auditoría en el sistema
            $detalle_log = "Licitación registrada: $monto_puja € (Retención total: $total_a_bloquear €) por el lote ID: $id_obra";
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

    /**
     * Extrae las 10 últimas pujas registradas a nivel global.
     * @return array Conjunto de resultados.
     */
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

    /**
     * Retorna el registro histórico completo de ofertas realizadas sobre una obra.
     * @param int $id_obra Identificador del lote.
     * @return array Conjunto de resultados.
     */
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

    /**
     * Evalúa y devuelve el estado de participación de un usuario en las subastas.
     * @param int $id_usuario Identificador del cliente.
     * @return array Conjunto de resultados con la resolución del estado.
     */
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

    /**
     * Procesa la finalización contractual de la adquisición (Escrow) aplicando el 
     * modelo de Partida Doble para garantizar trazabilidad en ambos libros mayores.
     * @param int $id_obra Identificador de la obra adjudicada.
     * @param int $id_comprador Identificador del adjudicatario legítimo.
     * @return array Resolución de la transacción.
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
                throw new Exception("Operación rechazada. La liquidación no es aplicable en el estado actual.");
            }
            if ($obra_bd['id_comprador'] != $id_comprador) {
                throw new Exception("Vulneración de seguridad detectada: Identidad del adjudicatario no coincide.");
            }

            $precio_final = $obra_bd['precio_actual'];
            $id_vendedor = $obra_bd['id_vendedor'];

            $total_bloqueado = $precio_final * 1.12; 
            $pago_vendedor = $precio_final * 0.92;
            $comision_aureus = $precio_final * 0.20;

            // 1. Deducción del saldo retenido al comprador
            $sql_comprador = "UPDATE usuario SET saldo_bloqueado = saldo_bloqueado - ? WHERE id_usuario = ?";
            $stmt_comprador = $this->db->prepare($sql_comprador);
            $stmt_comprador->bind_param("di", $total_bloqueado, $id_comprador);
            $stmt_comprador->execute();

            // 2. Transferencia de fondos netos al autor
            $sql_vendedor = "UPDATE usuario SET saldo_disponible = saldo_disponible + ? WHERE id_usuario = ?";
            $stmt_vendedor = $this->db->prepare($sql_vendedor);
            $stmt_vendedor->bind_param("di", $pago_vendedor, $id_vendedor);
            $stmt_vendedor->execute();

            // 3. Asignación de honorarios a la plataforma (Senado)
            $sql_aureus = "UPDATE usuario SET saldo_disponible = saldo_disponible + ? WHERE rol = 'admin' LIMIT 1";
            $stmt_aureus = $this->db->prepare($sql_aureus);
            $stmt_aureus->bind_param("d", $comision_aureus);
            $stmt_aureus->execute();

            // 4. Actualización de trazabilidad del lote
            $sql_estado = "UPDATE obra SET estado = 'ENTREGADA' WHERE id_obra = ?";
            $stmt_estado = $this->db->prepare($sql_estado);
            $stmt_estado->bind_param("i", $id_obra);
            $stmt_estado->execute();

            $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            // 5. REGISTRO CONTABLE (PARTIDA DOBLE)
            
            // 5.A - Recibo de salida para el COMPRADOR (Etiqueta Azul: Escrow)
            $detalle_comprador = "Liquidación de contrato Escrow: Lote #{$id_obra}. Transferido al artista: " . number_format($pago_vendedor, 2) . "€. Comisión AUREUS de intermediación (12%): " . number_format($total_bloqueado - $precio_final, 2) . "€.";
            $sql_log_c = "INSERT INTO log_sistema (id_usuario, accion, detalle, ip, fecha) VALUES (?, 'LIBERACION_FONDOS', ?, ?, NOW())";
            $stmt_log_c = $this->db->prepare($sql_log_c);
            $stmt_log_c->bind_param("iss", $id_comprador, $detalle_comprador, $ip_usuario);
            $stmt_log_c->execute();

            // 5.B - Recibo de entrada para el ARTISTA (Etiqueta Verde: Depósito)
            $detalle_artista = "Resolución de venta: Lote #{$id_obra} entregado con éxito al mecenas. Ingreso neto de " . number_format($pago_vendedor, 2) . "€ (Canon de plataforma deducido).";
            $sql_log_v = "INSERT INTO log_sistema (id_usuario, accion, detalle, ip, fecha) VALUES (?, 'INGRESO', ?, ?, NOW())";
            $stmt_log_v = $this->db->prepare($sql_log_v);
            $stmt_log_v->bind_param("iss", $id_vendedor, $detalle_artista, $ip_usuario);
            $stmt_log_v->execute();

            $this->db->commit();
            
            return ["success" => true, "message" => "Transacción finalizada. Fondos distribuidos exitosamente a todas las partes."];

        } catch (Exception $e) {
            $this->db->rollback();
            return ["success" => false, "message" => $e->getMessage()];
        }
    }
}
?>