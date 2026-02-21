<?php
/**
 * AUREUS - Proyecto Intermodular
 * Capa: MODELO (Infraestructura)
 * Archivo: modelos/BaseDatos.php
 * Descripción: Implementa el patrón Singleton para garantizar una única 
 * conexión a la base de datos usando MYSQLI Orientado a Objetos.
 * Evita la saturación del servidor al no abrir conexiones duplicadas.
 */

class BaseDatos {
    
    // 1. Instancia estática única (El "Singleton")
    private static $instancia = null;
    
    // 2. Objeto de conexión real a MySQL
    private $conexion;

    // 3. Credenciales del servidor (MVP)
    private $host = "localhost";
    private $usuario = "root";
    private $password = "";
    private $base_datos = "aureus_db";

    /**
     * El constructor es PRIVADO. 
     * Nadie desde fuera puede instanciar esta clase directamente.
     */
    private function __construct() {
        // Activamos el reporte estricto de errores de mysqli para depuración
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            // Instanciamos mysqli en su formato Orientado a Objetos
            $this->conexion = new mysqli($this->host, $this->usuario, $this->password, $this->base_datos);
            
            // Forzamos la codificación para evitar problemas con tildes y caracteres especiales
            $this->conexion->set_charset("utf8mb4");

        } catch (Exception $e) {
            // Si el servidor de BD está caído, detenemos la ejecución de forma segura
            die("Error crítico de infraestructura: No se pudo conectar a la base de datos de Aureus. Detalles: " . $e->getMessage());
        }
    }

    /**
     * Método público y estático para obtener la instancia.
     * Si no existe la conexión, la crea. Si ya existe, devuelve la guardada.
     * * @return BaseDatos La instancia única de esta clase.
     */
    public static function getInstance() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /**
     * Devuelve el objeto mysqli nativo para ejecutar consultas (prepare, query, etc.).
     * * @return mysqli Objeto de conexión a la base de datos.
     */
    public function getConnection() {
        return $this->conexion;
    }
}
?>