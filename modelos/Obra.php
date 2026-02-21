<?php
/**
 * AUREUS - Proyecto Intermodular
 * Capa: MODELO (Lógica de Datos)
 * Archivo: modelos/Obra.php
 * Descripción: Centraliza todas las operaciones CRUD (Crear, Leer, Actualizar, Borrar) 
 * de la tabla 'obra'. 
 */

class Obra {
    
    private $db;

    /**
     * Inyectamos la conexión a la base de datos al instanciar la clase.
     */
    public function __construct($conexion) {
        $this->db = $conexion;
    }

    /**
     * Obtiene el catálogo público (Solo obras ACTIVAS).
     * Reemplaza a vuestro antiguo 'api/get_catalog.php'
     */
    public function obtenerCatalogoActivo() {
        $sql = "SELECT id_obra, titulo, imagen_url, precio_actual, fecha_fin 
                FROM obra 
                WHERE estado = 'ACTIVA' 
                ORDER BY id_obra DESC";
        
        $resultado = $this->db->query($sql);
        
        // Devolvemos un array asociativo limpio
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene el detalle de una obra específica y la biografía de su creador.
     * Reemplaza a la primera parte de vuestro 'api/get_artwork.php'
     */
    public function obtenerPorId($id_obra) {
        $sql = "SELECT o.id_obra, o.titulo, o.descripcion, o.imagen_url, o.precio_actual, o.fecha_fin, u.biografia 
                FROM obra o 
                JOIN usuario u ON o.id_vendedor = u.id_usuario 
                WHERE o.id_obra = ?";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_obra);
        $stmt->execute();
        $resultado = $stmt->get_result();

        return $resultado->fetch_assoc(); // Devuelve 1 sola fila o null
    }

    /**
     * Inserta una nueva obra forjada por un artista (Estado: PENDIENTE).
     * Reemplaza a la consulta SQL de vuestro 'actions/procesar_obra.php'
     */
    public function crearObra($id_vendedor, $titulo, $descripcion, $precio, $fecha_fin, $imagen_url) {
        $sql = "INSERT INTO obra (id_vendedor, titulo, descripcion, precio_inicial, precio_actual, fecha_fin, imagen_url, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDIENTE')";
        
        $stmt = $this->db->prepare($sql);
        // "issddss" -> integer, string, string, double, double, string, string
        $stmt->bind_param("issddss", $id_vendedor, $titulo, $descripcion, $precio, $precio, $fecha_fin, $imagen_url);
        
        return $stmt->execute(); // Devuelve true si el INSERT fue exitoso
    }

    /**
     * Obtiene las obras pendientes para la Mesa del Senado (Admin).
     * Reemplaza a 'api/get_pending_works.php'
     */
    public function obtenerPendientes() {
        $sql = "SELECT o.id_obra, o.titulo, o.precio_inicial, u.nombre as nombre_artista 
                FROM obra o 
                JOIN usuario u ON o.id_vendedor = u.id_usuario 
                WHERE o.estado = 'PENDIENTE'";
                
        $resultado = $this->db->query($sql);
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Valida una obra para que pase a estar disponible en el catálogo.
     * Reemplaza a 'api/approve_work.php'
     */
    public function aprobarObra($id_obra) {
        $sql = "UPDATE obra SET estado = 'ACTIVA' WHERE id_obra = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_obra);
        return $stmt->execute();
    }

    /**
     * Obtiene el inventario y las estadísticas de un artista.
     */
    public function obtenerTallerArtista($id_artista) {
        // 1. KPIs (Estadísticas)
        $sql_stats = "SELECT 
                        COUNT(CASE WHEN estado = 'ACTIVA' THEN 1 END) as activas,
                        COUNT(CASE WHEN estado = 'PENDIENTE' THEN 1 END) as pendientes
                      FROM obra WHERE id_vendedor = ?";
        $stmt_stats = $this->db->prepare($sql_stats);
        $stmt_stats->bind_param("i", $id_artista);
        $stmt_stats->execute();
        $stats = $stmt_stats->get_result()->fetch_assoc();

        // 2. Ventas totales
        $sql_ventas = "SELECT SUM(precio_actual) as total_ventas FROM obra WHERE id_vendedor = ? AND estado = 'FINALIZADA'";
        $stmt_ventas = $this->db->prepare($sql_ventas);
        $stmt_ventas->bind_param("i", $id_artista);
        $stmt_ventas->execute();
        $ventas = $stmt_ventas->get_result()->fetch_assoc();

        // 3. Inventario
        $sql_inv = "SELECT id_obra, titulo, precio_actual, estado, fecha_fin FROM obra WHERE id_vendedor = ? ORDER BY id_obra DESC";
        $stmt_inv = $this->db->prepare($sql_inv);
        $stmt_inv->bind_param("i", $id_artista);
        $stmt_inv->execute();
        $artworks = $stmt_inv->get_result()->fetch_all(MYSQLI_ASSOC);

        return [
            "stats" => [
                "activas" => (int)$stats['activas'],
                "pendientes" => (int)$stats['pendientes'],
                "total_ventas" => (float)($ventas['total_ventas'] ?? 0)
            ],
            "artworks" => $artworks
        ];
    }
}
?>