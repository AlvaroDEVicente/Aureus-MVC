<?php
/**
 * AUREUS - Proyecto Intermodular
 * Capa: MODELO (Lógica de Datos)
 */
class Obra {
    
    private $db;

    public function __construct($conexion) {
        $this->db = $conexion;
    }

public function obtenerCatalogoActivo() {
        // AÑADIDO: id_categoria
        $sql = "SELECT id_obra, titulo, imagen_url, precio_actual, fecha_fin, id_categoria 
                FROM obra 
                WHERE estado = 'ACTIVA' 
                ORDER BY id_obra DESC";
        $resultado = $this->db->query($sql);
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerPorId($id_obra) {
        $sql = "SELECT o.id_obra, o.titulo, o.descripcion, o.imagen_url, o.precio_actual, o.fecha_fin, u.biografia, o.id_vendedor 
                FROM obra o 
                JOIN usuario u ON o.id_vendedor = u.id_usuario 
                WHERE o.id_obra = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_obra);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function crearObra($id_vendedor, $titulo, $descripcion, $precio, $fecha_fin, $imagen_url) {
        $sql = "INSERT INTO obra (id_vendedor, titulo, descripcion, precio_inicial, precio_actual, fecha_fin, imagen_url, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDIENTE')";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("issddss", $id_vendedor, $titulo, $descripcion, $precio, $precio, $fecha_fin, $imagen_url);
        return $stmt->execute();
    }

    public function obtenerPendientes() {
        $sql = "SELECT o.id_obra, o.titulo, o.precio_inicial, u.nombre as nombre_artista, o.imagen_url 
                FROM obra o 
                JOIN usuario u ON o.id_vendedor = u.id_usuario 
                WHERE o.estado = 'PENDIENTE'";
        $resultado = $this->db->query($sql);
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    public function cambiarEstadoObra($id_obra, $nuevo_estado) {
        $sql = "UPDATE obra SET estado = ? WHERE id_obra = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $nuevo_estado, $id_obra);
        return $stmt->execute();
    }

    public function obtenerTallerArtista($id_artista) {
        $sql_stats = "SELECT 
                        COUNT(CASE WHEN estado = 'ACTIVA' THEN 1 END) as activas,
                        COUNT(CASE WHEN estado = 'PENDIENTE' THEN 1 END) as pendientes
                      FROM obra WHERE id_vendedor = ?";
        $stmt_stats = $this->db->prepare($sql_stats);
        $stmt_stats->bind_param("i", $id_artista);
        $stmt_stats->execute();
        $stats = $stmt_stats->get_result()->fetch_assoc();

        $sql_inv = "SELECT id_obra, titulo, precio_actual, estado, fecha_fin, imagen_url FROM obra WHERE id_vendedor = ? ORDER BY id_obra DESC";
        $stmt_inv = $this->db->prepare($sql_inv);
        $stmt_inv->bind_param("i", $id_artista);
        $stmt_inv->execute();
        $artworks = $stmt_inv->get_result()->fetch_all(MYSQLI_ASSOC);

        return [
            "stats" => [
                "activas" => (int)$stats['activas'],
                "pendientes" => (int)$stats['pendientes'],
                "total_ventas" => 0
            ],
            "artworks" => $artworks
        ];
    }

    public function obtenerDetalleCompleto($id_obra) {
        $sql = "SELECT o.*, u.nombre as artista_nombre 
                FROM obra o 
                JOIN usuario u ON o.id_vendedor = u.id_usuario 
                WHERE o.id_obra = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_obra);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Liquida las subastas cuya fecha ha vencido.
     * Cambia el estado a 'FINALIZADA' y asigna el id_comprador.
     */
   public function liquidarSubastasVencidas() {
        // 1. Buscamos todas las obras que siguen marcadas como 'ACTIVA' 
        // pero cuya fecha de finalización ya es menor o igual al momento actual (NOW()).
        $sql = "SELECT id_obra, titulo FROM obra WHERE estado = 'ACTIVA' AND fecha_fin <= NOW()";
        $resultado = $this->db->query($sql);
        $obras_caducadas = $resultado->fetch_all(MYSQLI_ASSOC);

        $obras_liquidadas = 0;

        // Iteramos sobre cada obra caducada que hemos encontrado
        foreach ($obras_caducadas as $obra) {
            $id_obra = $obra['id_obra'];

            // 2. AVERIGUAR EL GANADOR:
            // Buscamos en el historial de pujas la oferta más alta para esta obra en concreto.
            // Usamos 'ORDER BY monto DESC LIMIT 1' para obtener solo la puja ganadora.
            $sql_ganador = "SELECT id_usuario, monto FROM puja WHERE id_obra = ? ORDER BY monto DESC LIMIT 1";
            $stmt_ganador = $this->db->prepare($sql_ganador);
            $stmt_ganador->bind_param("i", $id_obra);
            $stmt_ganador->execute();
            $ganador = $stmt_ganador->get_result()->fetch_assoc();

            // Si hay un ganador, extraemos su ID. Si nadie pujó, se queda como null (la obra quedó desierta).
            $id_comprador = $ganador ? $ganador['id_usuario'] : null;

            // 3. ACTUALIZAR EL ESTADO DE LA OBRA:
            // Pasamos la obra a estado 'FINALIZADA' y le asignamos el ID del comprador ganador.
            // Todavía no movemos el dinero. El dinero del comprador sigue en su 'saldo_bloqueado'.
            if ($id_comprador) {
                // Si hay ganador, vinculamos los dos parámetros enteros ("ii")
                $sql_update = "UPDATE obra SET estado = 'FINALIZADA', id_comprador = ? WHERE id_obra = ?";
                $stmt_update = $this->db->prepare($sql_update);
                $stmt_update->bind_param("ii", $id_comprador, $id_obra);
            } else {
                // Si está desierta (null), le pasamos el NULL directamente en el texto del SQL
                $sql_update = "UPDATE obra SET estado = 'FINALIZADA', id_comprador = NULL WHERE id_obra = ?";
                $stmt_update = $this->db->prepare($sql_update);
                $stmt_update->bind_param("i", $id_obra);
            }
            
            if ($stmt_update->execute()) {
                // 4. AUDITORÍA (Logs):
                // Dejamos un rastro de papel (log) de cada cambio de estado automático.
                $detalle = "Subasta finalizada automáticamente. Obra ID: {$id_obra}. " . 
                           ($id_comprador ? "Ganador adjudicado ID: {$id_comprador}" : "Subasta desierta (Sin pujas).");
                
                // Usamos NULL en vez de 0 para no violar la Foreign Key
                $sql_log = "INSERT INTO log_sistema (id_usuario, accion, detalle, ip, fecha) VALUES (NULL, 'CIERRE_SUBASTA', ?, '127.0.0.1', NOW())";
                $stmt_log = $this->db->prepare($sql_log);
                $stmt_log->bind_param("s", $detalle);
                $stmt_log->execute();
                
                $obras_liquidadas++; // Sumamos al contador de éxito
            }
        }

        return $obras_liquidadas;
    }
}
?>