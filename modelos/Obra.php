<?php
/**
 * Proyecto Intermodular: AUREUS
 * Capa: Modelo (Lógica de Datos)
 * Archivo: modelos/Obra.php
 * Descripción:
 * Gestiona las operaciones CRUD y la lógica de negocio relacionada con las obras de arte,
 * incluyendo la publicación, catalogación y resolución de subastas finalizadas.
 */
class Obra {
    
    /** @var mysqli Objeto de conexión a la base de datos. */
    private $db;

    /**
     * Constructor de la clase.
     * @param mysqli $conexion Conexión activa a la base de datos.
     */
    public function __construct($conexion) {
        $this->db = $conexion;
    }

    /**
     * Obtiene el catálogo de obras filtrando por estados visibles para el usuario.
     * @return array Lista asociativa de obras.
     */
    public function obtenerCatalogoActivo() {
        $sql = "SELECT id_obra, titulo, imagen_url, precio_inicial, precio_actual, fecha_fin, id_categoria, estado 
                FROM obra 
                WHERE estado IN ('ACTIVA', 'FINALIZADA', 'ENTREGADA', 'DESIERTA') 
                ORDER BY estado = 'ACTIVA' DESC, fecha_fin DESC";
        $resultado = $this->db->query($sql);
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene los detalles completos de una obra mediante su identificador.
     * @param int $id_obra Identificador de la obra.
     * @return array|null Datos de la obra o null si no existe.
     */
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

    /**
     * Inserta un nuevo registro de obra en estado 'PENDIENTE'.
     * @param int $id_vendedor Identificador del usuario creador.
     * @param string $titulo Título de la obra.
     * @param string $descripcion Descripción detallada.
     * @param float $precio Precio base de salida.
     * @param string $fecha_fin Fecha y hora de finalización prevista.
     * @param string $imagen_url Ruta relativa de la imagen almacenada.
     * @return bool True en caso de éxito, False en caso contrario.
     */
    public function crearObra($id_vendedor, $titulo, $descripcion, $precio, $fecha_fin, $imagen_url) {
        $sql = "INSERT INTO obra (id_vendedor, titulo, descripcion, precio_inicial, precio_actual, fecha_fin, imagen_url, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDIENTE')";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("issddss", $id_vendedor, $titulo, $descripcion, $precio, $precio, $fecha_fin, $imagen_url);
        return $stmt->execute();
    }

    /**
     * Obtiene las obras pendientes de moderación administrativa.
     * @return array Lista asociativa de obras pendientes.
     */
    public function obtenerPendientes() {
        $sql = "SELECT o.id_obra, o.titulo, o.precio_inicial, u.nombre as nombre_artista, o.imagen_url 
                FROM obra o 
                JOIN usuario u ON o.id_vendedor = u.id_usuario 
                WHERE o.estado = 'PENDIENTE'";
        $resultado = $this->db->query($sql);
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Modifica el estado operativo de una obra específica.
     * @param int $id_obra Identificador de la obra.
     * @param string $nuevo_estado Estado a aplicar (ej. 'ACTIVA', 'RECHAZADA').
     * @return bool
     */
    public function cambiarEstadoObra($id_obra, $nuevo_estado) {
        $sql = "UPDATE obra SET estado = ? WHERE id_obra = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $nuevo_estado, $id_obra);
        return $stmt->execute();
    }

    /**
     * Obtiene el inventario y las estadísticas de un artista.
     * @param int $id_artista Identificador del usuario artista.
     * @return array Estructura con contadores estadísticos y lista de obras.
     */
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

    /**
     * Obtiene el detalle exhaustivo de una obra incluyendo el nombre del autor.
     * Utilizado para los procesos de moderación.
     * @param int $id_obra Identificador de la obra.
     * @return array|null Datos completos.
     */
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
     * Procedimiento por lotes: Identifica obras cuya fecha límite ha expirado 
     * y resuelve la subasta asignando el ganador o declarándola desierta.
     * @return int Número de obras liquidadas exitosamente.
     */
    public function liquidarSubastasVencidas() {
        $sql = "SELECT id_obra, titulo FROM obra WHERE estado = 'ACTIVA' AND fecha_fin <= NOW()";
        $resultado = $this->db->query($sql);
        $obras_caducadas = $resultado->fetch_all(MYSQLI_ASSOC);

        $obras_liquidadas = 0;

        foreach ($obras_caducadas as $obra) {
            $id_obra = $obra['id_obra'];

            $sql_ganador = "SELECT id_usuario, monto FROM puja WHERE id_obra = ? ORDER BY monto DESC LIMIT 1";
            $stmt_ganador = $this->db->prepare($sql_ganador);
            $stmt_ganador->bind_param("i", $id_obra);
            $stmt_ganador->execute();
            $ganador = $stmt_ganador->get_result()->fetch_assoc();

            $id_comprador = $ganador ? $ganador['id_usuario'] : null;

            if ($id_comprador) {
                $sql_update = "UPDATE obra SET estado = 'FINALIZADA', id_comprador = ? WHERE id_obra = ?";
                $stmt_update = $this->db->prepare($sql_update);
                $stmt_update->bind_param("ii", $id_comprador, $id_obra);
            } else {
                $sql_update = "UPDATE obra SET estado = 'DESIERTA', id_comprador = NULL WHERE id_obra = ?";
                $stmt_update = $this->db->prepare($sql_update);
                $stmt_update->bind_param("i", $id_obra);
            }
            
            if ($stmt_update->execute()) {
                $detalle = "Subasta finalizada automáticamente. Obra ID: {$id_obra}. " . 
                           ($id_comprador ? "Ganador adjudicado ID: {$id_comprador}" : "Subasta desierta (Sin pujas).");
                
                // Implementación de la captura dinámica de IP (Bloque 2)
                $ip_sistema = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1'; // IP del servidor ejecutando el cron simulado
                
                $sql_log = "INSERT INTO log_sistema (id_usuario, accion, detalle, ip, fecha) VALUES (NULL, 'CIERRE_SUBASTA', ?, ?, NOW())";
                $stmt_log = $this->db->prepare($sql_log);
                $stmt_log->bind_param("ss", $detalle, $ip_sistema);
                $stmt_log->execute();
                
                $obras_liquidadas++; 
            }
        }
        return $obras_liquidadas;
    }

    // ==========================================================
    // OPERACIONES ADMINISTRATIVAS DIRECTAS
    // ==========================================================

    /**
     * Fuerza una modificación en la fecha de expiración de una obra.
     * @param int $id_obra Identificador de la obra.
     * @param string $nueva_fecha Fecha y hora en formato ISO.
     * @return bool
     */
    public function adminAlterarReloj($id_obra, $nueva_fecha) {
        $sql = "UPDATE obra SET fecha_fin = ? WHERE id_obra = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $nueva_fecha, $id_obra);
        return $stmt->execute();
    }

    /**
     * Elimina físicamente un registro de obra de la base de datos.
     * @param int $id_obra Identificador de la obra.
     * @return bool
     */
    public function adminFulminarObra($id_obra) {
        $sql = "DELETE FROM obra WHERE id_obra = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_obra);
        return $stmt->execute();
    }
}
?>