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
}
?>