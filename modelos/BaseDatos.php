<?php
/**
 * Proyecto Intermodular: AUREUS
 * Capa: Modelo (Infraestructura)
 * Archivo: modelos/BaseDatos.php
 * Descripción:
 * Implementa el patrón de diseño Singleton para garantizar una única 
 * instancia de conexión a la base de datos durante el ciclo de vida de la petición.
 * Utiliza la extensión MySQLi orientada a objetos.
 */

class BaseDatos {
    
    /** @var BaseDatos|null Instancia estática única de la clase. */
    private static $instancia = null;
    
    /** @var mysqli Objeto de conexión a la base de datos. */
    private $conexion;

    /** @var string Dirección del servidor de base de datos. */
    private $host = "localhost";
    
    /** @var string Usuario de la base de datos. */
    private $usuario = "root";
    
    /** @var string Contraseña de la base de datos. */
    private $password = "";
    
    /** @var string Nombre de la base de datos. */
    private $base_datos = "aureus_db";

    /**
     * Constructor privado para prevenir la instanciación directa.
     * Inicializa la conexión y establece el conjunto de caracteres.
     * Lanza una excepción fatal si la conexión falla para proteger la integridad de la app.
     */
    private function __construct() {
        // Configuración para el reporte estricto de errores en MySQLi
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->conexion = new mysqli($this->host, $this->usuario, $this->password, $this->base_datos);
            $this->conexion->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Error crítico de infraestructura: No se pudo conectar a la base de datos. Detalles: " . $e->getMessage());
        }
    }

    /**
     * Devuelve la instancia única de la clase (Patrón Singleton).
     * * @return BaseDatos Instancia de la clase.
     */
    public static function getInstance() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /**
     * Proporciona acceso al objeto de conexión nativo.
     * * @return mysqli Objeto de conexión MySQLi.
     */
    public function getConnection() {
        return $this->conexion;
    }
}
?>