<?php
/**
 * AUREUS - Proyecto Intermodular
 * Capa: CONTROLADOR (Lógica de Negocio)
 * Archivo: controladores/ControladorSubasta.php
 * Descripción: Orquesta todo lo relacionado con el catálogo de obras de arte y 
 * el sistema de transacciones (pujas). Recibe peticiones de la SPA, consulta 
 * a los modelos correspondientes y devuelve respuestas en formato JSON.
 */

require_once 'modelos/BaseDatos.php';
require_once 'modelos/Obra.php';
require_once 'modelos/Puja.php';

class ControladorSubasta {
    
    private $bd;
    private $modeloObra;
    private $modeloPuja;

    public function __construct() {
        $this->bd = BaseDatos::getInstance()->getConnection();
        $this->modeloObra = new Obra($this->bd);
        $this->modeloPuja = new Puja($this->bd);
    }

    /**
     * Devuelve el catálogo de obras activas en formato JSON.
     */
    public function obtenerCatalogo() {
        $catalogo = $this->modeloObra->obtenerCatalogoActivo();
        
        header('Content-Type: application/json');
        echo json_encode($catalogo);
        exit();
    }

    /**
     * Procesa el formulario del Taller del Artista (Subida de imagen y datos de la obra).
     */
    public function subirObra() {
        session_start();
        header('Content-Type: application/json');

        // Control de autorización: Solo roles 'artista' o 'admin'
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_rol'] != 'artista' && $_SESSION['user_rol'] != 'admin')) {
            echo json_encode(["success" => false, "message" => "Acceso denegado. Se requiere privilegios de Artista."]);
            exit();
        }

        try {
            $id_vendedor = $_SESSION['user_id']; 
            $titulo = $_POST['titulo'];
            $desc = $_POST['descripcion'];
            $precio = (float)$_POST['precio'];
            $fecha_fin = $_POST['fecha_fin'];

            if (!isset($_FILES["imagen"]) || $_FILES["imagen"]["error"] !== 0) {
                throw new Exception("El archivo de imagen es obligatorio.");
            }

            $nombre_archivo = time() . "_" . preg_replace("/[^A-Z0-9._-]/i", "_", basename($_FILES["imagen"]["name"]));
            
            $ruta_fisica = "public/uploads/" . $nombre_archivo; 
            $ruta_base_datos = "uploads/" . $nombre_archivo;

            if (!is_dir('public/uploads/')) {
                mkdir('public/uploads/', 0777, true);
            }

            if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $ruta_fisica)) {
                $exito = $this->modeloObra->crearObra($id_vendedor, $titulo, $desc, $precio, $fecha_fin, $ruta_base_datos);
                
                if ($exito) {
                    echo json_encode(["success" => true, "message" => "Obra registrada. Pendiente de validación."]);
                } else {
                    throw new Exception("Error interno al registrar la obra en la base de datos.");
                }
            } else {
                throw new Exception("Error del sistema al almacenar el archivo físico.");
            }

        } catch (Exception $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        exit();
    }

    /**
     * Devuelve las obras en estado pendiente para la validación del Administrador.
     */
    public function obtenerPendientes() {
        header('Content-Type: application/json');
        $pendientes = $this->modeloObra->obtenerPendientes();
        echo json_encode($pendientes);
        exit();
    }

    /**
     * Cambia el estado de una obra a 'ACTIVA' tras la validación del Administrador.
     */
   public function aprobarObra() {
        session_start();
        header('Content-Type: application/json');
        
        // Blindaje: Comprobar que hay sesión y que el rol es 'admin'
        if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["success" => false, "message" => "Acceso denegado. Se requiere rango de Senador."]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $id_obra = (int)$datos['id_obra'];

        $exito = $this->modeloObra->aprobarObra($id_obra);

        if ($exito) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Fallo en la actualización del estado."]);
        }
        exit();
    }

    /**
     * Devuelve los detalles de una obra específica junto con su historial de pujas.
     */
    public function obtenerDetalleObra() {
        header('Content-Type: application/json');
        
        $id_obra = $_GET['id'] ?? null;
        if (!$id_obra) {
            echo json_encode(["error" => "Identificador de obra no proporcionado."]); 
            exit();
        }

        $obra = $this->modeloObra->obtenerPorId($id_obra);
        if (!$obra) { 
            echo json_encode(["error" => "Obra no localizada en los registros."]); 
            exit(); 
        }

        $historial = $this->modeloPuja->obtenerHistorialPorObra($id_obra);

        $historial_formateado = array_map(function($h) {
            $h['monto'] = (float)$h['monto'];
            return $h;
        }, $historial);

        echo json_encode([
            "id_obra" => $obra['id_obra'],
            "titulo" => $obra['titulo'],
            "descripcion" => $obra['descripcion'],
            "imagen_url" => $obra['imagen_url'],
            "precio_actual" => (float)$obra['precio_actual'],
            "fecha_fin" => $obra['fecha_fin'],
            "biografia_artista" => $obra['biografia'] ?? "Sin biografía disponible.",
            "history" => $historial_formateado
        ]);
        exit();
    }

    /**
     * Inicia el proceso transaccional (ACID) para registrar una nueva puja.
     */
    public function sellarTransaccion() {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["success" => false, "message" => "Autorización denegada. Sesión inactiva."]); 
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $id_obra = (int)$datos['id_obra'];
        $monto_puja = (float)$datos['monto'];
        $id_usuario = $_SESSION['user_id'];
        $ip_usuario = $_SERVER['REMOTE_ADDR'];

        $resultado = $this->modeloPuja->realizarPuja($id_obra, $id_usuario, $monto_puja, $ip_usuario);
        
        echo json_encode($resultado);
        exit();
    }

    /**
     * Provee los datos de las últimas transacciones globales para el componente visual del ticker.
     */
    public function obtenerTickerGlobal() {
        header('Content-Type: application/json');
        
        $pujas = $this->modeloPuja->obtenerTickerGlobal();
        
        $pujas = array_map(function($p) {
            $p['monto'] = (float)$p['monto'];
            return $p;
        }, $pujas);

        echo json_encode($pujas);
        exit();
    }

/**
     * Devuelve los datos del Taller para los Artistas.
     */
    public function obtenerTaller() {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["error" => "Identificación requerida."]); 
            exit();
        }

        $datos = $this->modeloObra->obtenerTallerArtista($_SESSION['user_id']);
        echo json_encode($datos);
        exit();
    }

    /**
     * Devuelve las posiciones activas en la Bóveda del Mecenas.
     */
    public function obtenerMisPujas() {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode([]); 
            exit();
        }

        $pujas = $this->modeloPuja->obtenerPujasMecenas($_SESSION['user_id']);
        echo json_encode($pujas);
        exit();
    }

}
?>