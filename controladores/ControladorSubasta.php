<?php
/**
 * Proyecto Intermodular: AUREUS
 * Capa: Controlador (Lógica de Negocio y API REST)
 * Archivo: controladores/ControladorSubasta.php
 * * Descripción:
 * Controlador central para la gestión del catálogo de subastas, procesamiento 
 * de pujas y operaciones CRUD de las obras. Incluye métodos protegidos 
 * para la mitigación de vulnerabilidades relacionadas con la carga de archivos.
 */

require_once 'modelos/BaseDatos.php';
require_once 'modelos/Obra.php';
require_once 'modelos/Puja.php';

class ControladorSubasta
{
    /** @var mysqli Instancia de la conexión a la base de datos. */
    private $bd;

    /** @var Obra Instancia del modelo Obra. */
    private $modeloObra;

    /** @var Puja Instancia del modelo Puja. */
    private $modeloPuja;

    /**
     * Constructor de la clase.
     * Establece la conexión e inicializa los modelos dependientes.
     */
    public function __construct()
    {
        $this->bd = BaseDatos::getInstance()->getConnection();
        $this->modeloObra = new Obra($this->bd);
        $this->modeloPuja = new Puja($this->bd);
    }

    /**
     * Endpoint: Devuelve el listado completo de obras catalogadas activas y recientes.
     * * @return void
     */
    public function obtenerCatalogo()
    {
        $catalogo = $this->modeloObra->obtenerCatalogoActivo();
        header('Content-Type: application/json');
        echo json_encode($catalogo);
        exit();
    }

    /**
     * Endpoint: Gestiona la recepción y almacenamiento seguro de una nueva obra.
     * Implementa validaciones exhaustivas de tipos MIME y tamaño para prevenir
     * ataques de Ejecución de Código Remoto (RCE).
     * * @return void
     */
    public function subirObra()
    {
        session_start();
        header('Content-Type: application/json');

        // Control de acceso RBAC
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_rol'] != 'artista' && $_SESSION['user_rol'] != 'admin')) {
            echo json_encode(["success" => false, "message" => "Acceso denegado. Se requieren privilegios de creador."]);
            exit();
        }

        try {
            $id_vendedor = $_SESSION['user_id'];
            $titulo = $_POST['titulo'];
            $desc = $_POST['descripcion'];
            $precio = (float) $_POST['precio'];
            $fecha_fin = $_POST['fecha_fin'];

            // Validación de la regla de negocio: Valor mínimo de tasación
            if ($precio < 50) {
                throw new Exception("El valor de tasación inicial debe ser igual o superior a 50,00 € para cumplir con los estándares de la plataforma.");
            }

            // Verificación de integridad en la transmisión del archivo
            if (!isset($_FILES["imagen"]) || $_FILES["imagen"]["error"] !== UPLOAD_ERR_OK) {
                throw new Exception("El archivo adjunto es obligatorio o el proceso de transmisión ha fallado.");
            }

            $archivo_tmp = $_FILES["imagen"]["tmp_name"];
            $peso_maximo = 20971520; // 20 MB expresados en bytes

            if ($_FILES["imagen"]["size"] > $peso_maximo) {
                throw new Exception("El tamaño del archivo excede el límite permitido de 20 MB.");
            }

            // Análisis estricto del tipo MIME utilizando la extensión finfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_real = finfo_file($finfo, $archivo_tmp);
            finfo_close($finfo);

            $formatos_permitidos = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            if (!array_key_exists($mime_real, $formatos_permitidos)) {
                throw new Exception("Formato de archivo denegado. Se admiten extensiones JPG, PNG o WEBP.");
            }

            // Asignación de un identificador único para prevenir colisiones y ocultar el origen
            $extension_segura = $formatos_permitidos[$mime_real];
            $nombre_archivo = uniqid('aureus_', true) . '.' . $extension_segura;
            $ruta_fisica = "public/uploads/" . $nombre_archivo;
            $ruta_base_datos = "uploads/" . $nombre_archivo;

            if (!is_dir('public/uploads/')) {
                mkdir('public/uploads/', 0777, true);
            }

            // Traslado del archivo a persistencia local y registro en base de datos
            if (move_uploaded_file($archivo_tmp, $ruta_fisica)) {
                $exito = $this->modeloObra->crearObra($id_vendedor, $titulo, $desc, $precio, $fecha_fin, $ruta_base_datos);
                
                if ($exito) {
                    echo json_encode(["success" => true, "message" => "La obra ha sido registrada y se encuentra pendiente de revisión administrativa."]);
                } else {
                    unlink($ruta_fisica); // Reversión (rollback manual) en caso de fallo en BD
                    throw new Exception("Error interno al persistir los datos de la obra.");
                }
            } else {
                throw new Exception("Error de I/O al almacenar el archivo en el servidor.");
            }

        } catch (Exception $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        exit();
    }

    /**
     * Endpoint administrativo: Retorna las obras cuyo estado sea 'PENDIENTE' de moderación.
     * * @return void
     */
    public function obtenerPendientes()
    {
        header('Content-Type: application/json');
        $pendientes = $this->modeloObra->obtenerPendientes();
        echo json_encode($pendientes);
        exit();
    }

    /**
     * Endpoint: Compila y devuelve la información íntegra de una obra y su historial de licitación.
     * * @return void
     */
    public function obtenerDetalleObra()
    {
        header('Content-Type: application/json');
        
        $id_obra = $_GET['id'] ?? null;
        if (!$id_obra) {
            echo json_encode(["error" => "Parámetro identificador requerido."]);
            exit();
        }

        $obra = $this->modeloObra->obtenerPorId($id_obra);
        if (!$obra) {
            echo json_encode(["error" => "El registro solicitado no existe."]);
            exit();
        }

        $historial = $this->modeloPuja->obtenerHistorialPorObra($id_obra);
        
        // Conversión estricta de tipos para el frontend
        $historial_formateado = array_map(function ($h) {
            $h['monto'] = (float) $h['monto'];
            return $h;
        }, $historial);

        echo json_encode([
            "id_obra" => $obra['id_obra'],
            "titulo" => $obra['titulo'],
            "descripcion" => $obra['descripcion'],
            "imagen_url" => $obra['imagen_url'],
            "precio_actual" => (float) $obra['precio_actual'],
            "fecha_fin" => $obra['fecha_fin'],
            "biografia_artista" => $obra['biografia'] ?? "Información no disponible.",
            "history" => $historial_formateado,
            "id_vendedor" => $obra['id_vendedor'] 
        ]);
        exit();
    }

    /**
     * Endpoint: Registra una nueva puja delegando en el modelo transaccional.
     * Implementa recolección segura de direcciones IP, truncando a 45 caracteres 
     * para asegurar la compatibilidad con el estándar IPv6 en la base de datos.
     * * @return void
     */
    public function sellarTransaccion()
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["success" => false, "message" => "Autenticación requerida para operar."]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $id_obra = (int) $datos['id_obra'];
        $monto_puja = (float) $datos['monto'];
        $id_usuario = $_SESSION['user_id'];
        
        // Manejo seguro de la longitud de la IP (Prevención de truncamiento en MySQL)
        $ip_usuario = substr($_SERVER['REMOTE_ADDR'], 0, 45); 

        // Ejecución de la transacción ACID
        $resultado = $this->modeloPuja->realizarPuja($id_obra, $id_usuario, $monto_puja, $ip_usuario);

        // Invalida la caché del Ticker global en caso de éxito
        if ($resultado['success'] === true) {
            $archivo_cache = 'public/uploads/ticker_cache.json';
            if (file_exists($archivo_cache)) {
                unlink($archivo_cache); 
            }
        }

        echo json_encode($resultado);
        exit();
    }

    /**
     * Endpoint: Devuelve las pujas más recientes.
     * Implementa una estrategia de almacenamiento en caché basada en archivos estáticos
     * para mitigar la carga de consultas sobre la base de datos ante peticiones recurrentes (Polling).
     * * @return void
     */
    public function obtenerTickerGlobal()
    {
        header('Content-Type: application/json');
        $archivo_cache = 'public/uploads/ticker_cache.json'; 

        if (file_exists($archivo_cache)) {
            echo file_get_contents($archivo_cache);
            exit();
        }

        $pujas = $this->modeloPuja->obtenerTickerGlobal();
        
        $pujas = array_map(function ($p) {
            $p['monto'] = (float) $p['monto'];
            return $p;
        }, $pujas);

        $json_pujas = json_encode($pujas);
        file_put_contents($archivo_cache, $json_pujas);
        
        echo $json_pujas;
        exit();
    }

    /**
     * Endpoint: Obtiene el inventario y métricas relacionadas al usuario 'artista'.
     * * @return void
     */
    public function obtenerTaller()
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["error" => "Autenticación requerida."]);
            exit();
        }

        $datos = $this->modeloObra->obtenerTallerArtista($_SESSION['user_id']);
        echo json_encode($datos);
        exit();
    }

    /**
     * Endpoint: Proporciona el registro de licitaciones activas e históricas del usuario.
     * * @return void
     */
    public function obtenerMisPujas()
    {
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

    /**
     * Endpoint administrativo: Devuelve el detalle exhaustivo para la moderación de una obra.
     * * @return void
     */
    public function obtenerDetalleRevision() 
    {
        session_start();
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["error" => "Acceso denegado."]); 
            exit(); 
        }

        $id = (int)($_GET['id'] ?? 0);
        $obra = $this->modeloObra->obtenerDetalleCompleto($id);
        
        echo json_encode($obra);
        exit();
    }

    /**
     * Endpoint administrativo: Autoriza la publicación de una obra en el catálogo.
     * * @return void
     */
    public function aprobarObra() 
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["success" => false, "message" => "Acceso denegado"]); 
            exit(); 
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $id_obra = (int)($datos['id_obra'] ?? 0);

        $exito = $this->modeloObra->cambiarEstadoObra($id_obra, 'ACTIVA');
        echo json_encode(["success" => $exito]);
        exit();
    }

    /**
     * Endpoint administrativo: Deniega y oculta una obra previamente en evaluación.
     * * @return void
     */
    public function rechazarObra() 
    {
        session_start();
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'admin') { 
            echo json_encode(["success" => false]); 
            exit(); 
        }
        
        $datos = json_decode(file_get_contents("php://input"), true);
        $exito = $this->modeloObra->cambiarEstadoObra((int)$datos['id_obra'], 'RECHAZADA');
        echo json_encode(["success" => $exito]);
        exit();
    }

    /**
     * Endpoint interno: Evalúa y transiciona automáticamente el estado de las subastas 
     * cuya fecha de expiración ha sido superada.
     * * @return void
     */
    public function liquidarVencidas() 
    {
        header('Content-Type: application/json');
        $liquidadas = $this->modeloObra->liquidarSubastasVencidas();
        echo json_encode(["success" => true, "liquidadas" => $liquidadas]);
        exit();
    }

    /**
     * Endpoint: Desencadena el contrato inteligente simulado (Escrow) 
     * para la liberación final de los fondos retenidos hacia el vendedor y la plataforma.
     * * @return void
     */
    public function confirmarRecepcionObra() 
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["success" => false, "message" => "Sesión inactiva."]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $id_obra = (int) $datos['id_obra'];
        $id_comprador = $_SESSION['user_id'];

        $resultado = $this->modeloPuja->confirmarRecepcion($id_obra, $id_comprador);
        echo json_encode($resultado);
        exit();
    }

    // ==========================================================
    // OPERACIONES EXCEPCIONALES DE ADMINISTRACIÓN (MODO OVERRIDE)
    // ==========================================================

    /**
     * Endpoint administrativo: Modifica forzosamente el periodo de finalización de una subasta.
     * Empleado en entornos de evaluación o rectificaciones técnicas.
     * * @return void
     */
    public function adminModificarTiempo() 
    {
        session_start();
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["success" => false, "message" => "Autorización denegada."]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $id_obra = (int)($datos['id_obra'] ?? 0);
        $fecha_fin = $datos['fecha_fin'] ?? '';

        if ($id_obra <= 0 || empty($fecha_fin)) {
            echo json_encode(["success" => false, "message" => "Parámetros inválidos."]);
            exit();
        }

        $exito = $this->modeloObra->adminAlterarReloj($id_obra, $fecha_fin);
        echo json_encode(["success" => $exito, "message" => $exito ? "Parámetro temporal actualizado." : "Fallo en base de datos."]);
        exit();
    }

    /**
     * Endpoint administrativo: Ejecuta una eliminación física en cascada de un registro de obra.
     * * @return void
     */
    public function adminBorrarObra() 
    {
        session_start();
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["success" => false, "message" => "Autorización denegada."]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $id_obra = (int)($datos['id_obra'] ?? 0);

        if ($id_obra <= 0) {
            echo json_encode(["success" => false, "message" => "Identificador no válido."]);
            exit();
        }

        $exito = $this->modeloObra->adminFulminarObra($id_obra);
        echo json_encode(["success" => $exito, "message" => $exito ? "Registro purgado satisfactoriamente." : "Fallo en la persistencia."]);
        exit();
    }
}
?>