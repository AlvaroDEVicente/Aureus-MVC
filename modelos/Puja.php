<?php
/**
 * AUREUS - Proyecto Intermodular
 * Capa: MODELO (Lógica de Datos y Transacciones)
 * Archivo: modelos/Puja.php
 * Descripción: Gestiona el historial de pujas y la transacción financiera crítica (ACID)
 * asegurando la consistencia del libro mayor y evitando Race Conditions.
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
     * Sustituye a vuestro antiguo 'api/place_bid.php'
     */
    public function realizarPuja($id_obra, $id_usuario, $monto_puja, $ip_usuario)
    {

        // INICIO TRANSACCIÓN ACID
        // Apagamos el auto-guardado. A partir de aquí, si algo falla, nada se guarda.
        $this->db->begin_transaction();

        try {
            // PASO 1: Bloqueo Pesimista de Cartera (Evita que el usuario gaste el dinero dos veces)
            $sql_saldo = "SELECT saldo_disponible, saldo_bloqueado FROM usuario WHERE id_usuario = ? FOR UPDATE";
            $stmt_saldo = $this->db->prepare($sql_saldo);
            $stmt_saldo->bind_param("i", $id_usuario);
            $stmt_saldo->execute();
            $user_bd = $stmt_saldo->get_result()->fetch_assoc();

            if ($monto_puja > $user_bd['saldo_disponible']) {
                throw new Exception("Saldo Insuficiente en la bóveda. Recargue su cartera.");
            }

            // PASO 2: Bloqueo Pesimista de la Obra (Nadie más puede pujar por ella en este milisegundo)
            $sql_obra = "SELECT precio_actual, estado FROM obra WHERE id_obra = ? FOR UPDATE";
            $stmt_obra = $this->db->prepare($sql_obra);
            $stmt_obra->bind_param("i", $id_obra);
            $stmt_obra->execute();
            $obra_bd = $stmt_obra->get_result()->fetch_assoc();

            if (!$obra_bd || $obra_bd['estado'] !== 'ACTIVA') {
                throw new Exception("La obra de arte no está disponible para licitación.");
            }

            // PASO 3: Validación del salto mínimo exigido (+50€)
            $precio_a_batir = $obra_bd['precio_actual'];
            if ($monto_puja < ($precio_a_batir + 50)) {
                throw new Exception("La oferta debe superar los {$precio_a_batir}€ en al menos +50€.");
            }

            // PASO 4: Búsqueda del mecenas destronado y devolución de sus fondos bloqueados
            $sql_max = "SELECT id_usuario, monto FROM puja WHERE id_obra = ? ORDER BY monto DESC LIMIT 1 FOR UPDATE";
            $stmt_max = $this->db->prepare($sql_max);
            $stmt_max->bind_param("i", $id_obra);
            $stmt_max->execute();
            $puja_maxima = $stmt_max->get_result()->fetch_assoc();

            if ($puja_maxima && $puja_maxima['id_usuario'] != $id_usuario) {
                $sql_dev = "UPDATE usuario SET saldo_bloqueado = saldo_bloqueado - ?, saldo_disponible = saldo_disponible + ? WHERE id_usuario = ?";
                $stmt_dev = $this->db->prepare($sql_dev);
                $stmt_dev->bind_param("ddi", $puja_maxima['monto'], $puja_maxima['monto'], $puja_maxima['id_usuario']);
                $stmt_dev->execute();
            }

            // PASO 5: Congelación de fondos del nuevo pujador ganador
            $nuevo_disponible = $user_bd['saldo_disponible'] - $monto_puja;
            $nuevo_bloqueado = $user_bd['saldo_bloqueado'] + $monto_puja;

            $sql_cobro = "UPDATE usuario SET saldo_disponible = ?, saldo_bloqueado = ? WHERE id_usuario = ?";
            $stmt_cobro = $this->db->prepare($sql_cobro);
            $stmt_cobro->bind_param("ddi", $nuevo_disponible, $nuevo_bloqueado, $id_usuario);
            $stmt_cobro->execute();

            // PASO 6: Actualización de la cotización y SISTEMA ANTI-SNIPING (Vía SQL)
            // Actualizamos el precio. Además, si a la fecha_fin le quedan menos de 5 minutos,
            // MySQL automáticamente le suma 5 minutos desde este momento exacto.
            $sql_update_obra = "UPDATE obra 
                                SET precio_actual = ?, 
                                    fecha_fin = IF(fecha_fin < DATE_ADD(NOW(), INTERVAL 5 MINUTE), DATE_ADD(NOW(), INTERVAL 5 MINUTE), fecha_fin) 
                                WHERE id_obra = ?";

            $stmt_update_obra = $this->db->prepare($sql_update_obra);
            $stmt_update_obra->bind_param("di", $monto_puja, $id_obra);
            $stmt_update_obra->execute();

            // PASO 7: Registro histórico en el libro de Pujas
            $sql_insert = "INSERT INTO puja (id_obra, id_usuario, monto, fecha) VALUES (?, ?, ?, NOW())";
            $stmt_insert = $this->db->prepare($sql_insert);
            $stmt_insert->bind_param("iid", $id_obra, $id_usuario, $monto_puja);
            $stmt_insert->execute();

            // PASO 8: Auditoría de Seguridad (Log del sistema)
            $detalle_log = "Licitación de $monto_puja € por la obra ID: $id_obra";
            $sql_log = "INSERT INTO log_sistema (id_usuario, accion, detalle, ip, fecha) VALUES (?, 'PUJA', ?, ?, NOW())";
            $stmt_log = $this->db->prepare($sql_log);
            $stmt_log->bind_param("iss", $id_usuario, $detalle_log, $ip_usuario);
            $stmt_log->execute();

            // COMMIT: Si hemos llegado hasta aquí sin errores, guardamos todo definitivamente.
            $this->db->commit();

            // Devolvemos el éxito y los nuevos saldos para actualizar la UI del usuario
            return [
                "success" => true,
                "nuevo_saldo_disponible" => $nuevo_disponible,
                "nuevo_saldo_bloqueado" => $nuevo_bloqueado
            ];

        } catch (Exception $e) {
            // ROLLBACK: Hubo un error (ej. saldo insuficiente o colisión). Deshacemos todo.
            $this->db->rollback();
            return [
                "success" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    /**
     * Ticker Global: Devuelve las últimas 10 pujas de toda la plataforma.
     * Sustituye a 'api/get_global_bids.php'
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
     * Historial de una obra: Devuelve todas las pujas de una obra concreta.
     * Es la segunda parte de vuestro antiguo 'api/get_artwork.php'
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
     * Obtiene las pujas de un usuario para su Bóveda Personal (Mejorado para Escrow).
     */
    public function obtenerPujasMecenas($id_usuario)
    {
        // Añadimos o.id_obra, o.estado y o.id_comprador al SELECT
        // Usamos un CASE para manejar los distintos estados de la obra en lugar de un simple IF
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
        // Pasamos el id_usuario 3 veces porque ahora el SQL tiene tres interrogaciones (?)
        $stmt->bind_param("iii", $id_usuario, $id_usuario, $id_usuario);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    /*
    * MOTOR TRANSACCIONAL PARA ESCROW (Liberación de fondos)
    */
    public function confirmarRecepcion($id_obra, $id_comprador) {
        
        // INICIO DE TRANSACCIÓN ACID
        // A partir de esta línea, MySQL no guardará los cambios permanentemente hasta que hagamos 'commit()'.
        $this->db->begin_transaction();

        try {
            // 1. BLOQUEO PESIMISTA Y VALIDACIÓN DE SEGURIDAD
            // Usamos 'FOR UPDATE' al final del SELECT. Esto bloquea esta fila de la obra en MySQL.
            // Evita que el usuario, haciendo doble clic rápido o trampas, ejecute esta función 2 veces a la vez.
            $sql_obra = "SELECT precio_actual, id_vendedor, estado, id_comprador FROM obra WHERE id_obra = ? FOR UPDATE";
            $stmt_obra = $this->db->prepare($sql_obra);
            $stmt_obra->bind_param("i", $id_obra);
            $stmt_obra->execute();
            $obra_bd = $stmt_obra->get_result()->fetch_assoc();

            // Blindaje 1: Comprobar que la obra existe y está esperando ser entregada ('FINALIZADA')
            if (!$obra_bd || $obra_bd['estado'] !== 'FINALIZADA') {
                throw new Exception("Operación rechazada. La obra no existe o ya fue liquidada.");
            }
            // Blindaje 2: Evitar que un usuario libere los fondos de una obra que no ha comprado él.
            if ($obra_bd['id_comprador'] != $id_comprador) {
                throw new Exception("Seguridad: Autorización denegada. No eres el adjudicatario de esta obra.");
            }

            $precio_final = $obra_bd['precio_actual'];
            $id_vendedor = $obra_bd['id_vendedor'];

            // 2. COBRO AL COMPRADOR
            // Le restamos el precio final de su 'saldo_bloqueado'. El dinero sale de su cuenta definitivamente.
            $sql_comprador = "UPDATE usuario SET saldo_bloqueado = saldo_bloqueado - ? WHERE id_usuario = ?";
            $stmt_comprador = $this->db->prepare($sql_comprador);
            $stmt_comprador->bind_param("di", $precio_final, $id_comprador);
            $stmt_comprador->execute();

            // AUREUS se queda un 5% de comisión por el servicio. El artista se lleva el 95% restante.
            $comision = $precio_final * 0.05;
            $pago_vendedor = $precio_final * 0.95;

            // 4. PAGO AL VENDEDOR (ARTISTA)
            // Se lo sumamos a su 'saldo_disponible' para que pueda retirarlo a su banco o usarlo para pujar.
            $sql_vendedor = "UPDATE usuario SET saldo_disponible = saldo_disponible + ? WHERE id_usuario = ?";
            $stmt_vendedor = $this->db->prepare($sql_vendedor);
            $stmt_vendedor->bind_param("di", $pago_vendedor, $id_vendedor);
            $stmt_vendedor->execute();

            // 5. MARCAR COMO FINALIZADA DEFINITIVAMENTE
            // Cambiamos a 'ENTREGADA'. Así evitamos que este script se pueda volver a ejecutar para esta obra.
            $sql_estado = "UPDATE obra SET estado = 'ENTREGADA' WHERE id_obra = ?";
            $stmt_estado = $this->db->prepare($sql_estado);
            $stmt_estado->bind_param("i", $id_obra);
            $stmt_estado->execute();

            // Guardamos el recibo de la transacción para el administrador en el log.
            $detalle_log = "Fondos liberados (Escrow): Obra #{$id_obra}. Vendedor #{$id_vendedor} recibe {$pago_vendedor}€. Comisión AUREUS: {$comision}€.";
            $sql_log = "INSERT INTO log_sistema (id_usuario, accion, detalle, fecha) VALUES (?, 'LIBERACION_FONDOS', ?, NOW())";
            $stmt_log = $this->db->prepare($sql_log);
            $stmt_log->bind_param("is", $id_comprador, $detalle_log);
            $stmt_log->execute();

            // Si todo ha ido bien, guardamos todos los cambios (updates de saldos, estados y logs) de golpe en la BD.
            $this->db->commit();
            
            return ["success" => true, "message" => "Fondos liberados y transferidos al artista. ¡Disfruta de tu nueva obra!"];

        } catch (Exception $e) {
            // Si ha fallado algo deshacemos CUALQUIER cambio que se haya intentado hacer en este bloque.
            // Nadie pierde dinero por errores del servidor.
            $this->db->rollback();
            return ["success" => false, "message" => $e->getMessage()];
        }
    }
}
?>