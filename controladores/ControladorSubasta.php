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

class ControladorSubasta
{

    private $bd;
    private $modeloObra;
    private $modeloPuja;

    public function __construct()
    {
        $this->bd = BaseDatos::getInstance()->getConnection();
        $this->modeloObra = new Obra($this->bd);
        $this->modeloPuja = new Puja($this->bd);
    }

    /**
     * Devuelve el catálogo de obras activas en formato JSON.
     */
    public function obtenerCatalogo()
    {
        $catalogo = $this->modeloObra->obtenerCatalogoActivo();

        header('Content-Type: application/json');
        echo json_encode($catalogo);
        exit();
    }

   /**
     * Procesa el formulario del Taller del Artista (Subida de imagen y datos de la obra).
     * VERSIÓN BLINDADA CONTRA RCE.
     */
    public function subirObra()
    {
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
            $precio = (float) $_POST['precio'];
            $fecha_fin = $_POST['fecha_fin'];

            // 1. Verificar si el archivo llegó y no hay errores a nivel de PHP
            if (!isset($_FILES["imagen"]) || $_FILES["imagen"]["error"] !== UPLOAD_ERR_OK) {
                throw new Exception("El archivo de imagen es obligatorio o la subida falló.");
            }

            $archivo_tmp = $_FILES["imagen"]["tmp_name"];

            // 2. Límite de peso: 5MB (5 * 1024 * 1024 bytes)
            $peso_maximo = 5242880; 
            if ($_FILES["imagen"]["size"] > $peso_maximo) {
                throw new Exception("La obra es demasiado pesada. El límite de la bóveda es de 5MB.");
            }

            // 3. Inspección MIME (El núcleo de la seguridad)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_real = finfo_file($finfo, $archivo_tmp);
            finfo_close($finfo);

            // 4. Lista blanca de formatos permitidos y asignación de extensión segura
            $formatos_permitidos = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            if (!array_key_exists($mime_real, $formatos_permitidos)) {
                throw new Exception("Formato no válido. Solo se admiten lienzos en JPG, PNG o WEBP.");
            }

            $extension_segura = $formatos_permitidos[$mime_real];

            // 5. Generación de nombre único y seguro
            // Ejemplo de salida: aureus_65e4a3b2c1d4e5.12345678.jpg
            $nombre_archivo = uniqid('aureus_', true) . '.' . $extension_segura;

            $ruta_fisica = "public/uploads/" . $nombre_archivo;
            $ruta_base_datos = "uploads/" . $nombre_archivo; // La ruta que leerá el frontend

            if (!is_dir('public/uploads/')) {
                mkdir('public/uploads/', 0777, true);
            }

            // 6. Movimiento físico y registro en la base de datos
            if (move_uploaded_file($archivo_tmp, $ruta_fisica)) {
                
                // Llamamos a nuestro modelo para guardar el registro
                $exito = $this->modeloObra->crearObra($id_vendedor, $titulo, $desc, $precio, $fecha_fin, $ruta_base_datos);

                if ($exito) {
                    echo json_encode(["success" => true, "message" => "Obra registrada. Pendiente de validación por el Senado."]);
                } else {
                    // Si la BD falla, borramos el archivo huérfano por limpieza
                    unlink($ruta_fisica);
                    throw new Exception("Error interno al registrar la obra en el libro mayor.");
                }
            } else {
                throw new Exception("Error del sistema al almacenar el archivo físico en la bóveda.");
            }

        } catch (Exception $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        exit();
    }

    /**
     * Devuelve las obras en estado pendiente para la validación del Administrador.
     */
    public function obtenerPendientes()
    {
        header('Content-Type: application/json');
        $pendientes = $this->modeloObra->obtenerPendientes();
        echo json_encode($pendientes);
        exit();
    }

    /**
     * Devuelve los detalles de una obra específica junto con su historial de pujas.
     */
    public function obtenerDetalleObra()
    {
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
            "biografia_artista" => $obra['biografia'] ?? "Sin biografía disponible.",
            "history" => $historial_formateado,
            "id_vendedor" => $obra['id_vendedor'] 
        ]);
        exit();
        exit();
    }

    /**
     * Inicia el proceso transaccional (ACID) para registrar una nueva puja.
     */
    public function sellarTransaccion()
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["success" => false, "message" => "Autorización denegada. Sesión inactiva."]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $id_obra = (int) $datos['id_obra'];
        $monto_puja = (float) $datos['monto'];
        $id_usuario = $_SESSION['user_id'];
        $ip_usuario = $_SERVER['REMOTE_ADDR'];

        $resultado = $this->modeloPuja->realizarPuja($id_obra, $id_usuario, $monto_puja, $ip_usuario);

        // Si la puja fue exitosa, borramos el caché para que el Ticker se actualice
        if ($resultado['success'] === true) {
            $archivo_cache = 'public/uploads/ticker_cache.json';
            if (file_exists($archivo_cache)) {
                unlink($archivo_cache); // Elimina el archivo
            }
        }

        echo json_encode($resultado);
        exit();
    }

    /**
     * Provee los datos del Ticker. OPTIMIZADO con Caché de Archivo.
     */
    public function obtenerTickerGlobal()
    {
        header('Content-Type: application/json');
        $archivo_cache = 'public/uploads/ticker_cache.json'; // Usamos la carpeta uploads que ya existe

        // 1. Si el archivo caché existe, lo enviamos directamente ¡CERO consultas a la BD!
        if (file_exists($archivo_cache)) {
            echo file_get_contents($archivo_cache);
            exit();
        }

        // 2. Si no existe (porque es la primera vez o hubo una puja nueva), consultamos a la BD
        $pujas = $this->modeloPuja->obtenerTickerGlobal();

        $pujas = array_map(function ($p) {
            $p['monto'] = (float) $p['monto'];
            return $p;
        }, $pujas);

        $json_pujas = json_encode($pujas);

        // 3. Guardamos el resultado en el archivo para que el próximo usuario lo lea de ahí
        file_put_contents($archivo_cache, $json_pujas);

        echo $json_pujas;
        exit();
    }

    /**
     * Devuelve los datos del Taller para los Artistas.
     */
    public function obtenerTaller()
    {
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

// Reemplaza desde "public function obtenerDetalleRevision()" hacia abajo con esto:

    public function obtenerDetalleRevision() {
        session_start();
        header('Content-Type: application/json');
        
        if ($_SESSION['user_rol'] !== 'admin') { 
            echo json_encode(["error" => "No autorizado"]); 
            exit(); 
        }

        $id = $_GET['id'] ?? 0;
        $obra = $this->modeloObra->obtenerDetalleCompleto($id);
        
        echo json_encode($obra);
        exit();
    }

    public function aprobarObra() {
        session_start();
        header('Content-Type: application/json');

        if ($_SESSION['user_rol'] !== 'admin') { 
            echo json_encode(["success" => false, "message" => "Acceso denegado"]); 
            exit(); 
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $id_obra = (int)($datos['id_obra'] ?? 0);

        // Aquí llamamos al método unificado del modelo
        $exito = $this->modeloObra->cambiarEstadoObra($id_obra, 'ACTIVA');
        
        echo json_encode(["success" => $exito]);
        exit();
    }

    /**
     * ENDPOINT: Liquidar Subastas Vencidas
     * ------------------------------------
     * Llamaremos a esto de fondo (background) desde JS cuando alguien entre a la app,
     * para mantener el estado de las obras siempre al día sin necesidad de CRON jobs.
     */
    public function liquidarVencidas() {
        header('Content-Type: application/json');
        
        // Llamamos al modelo para que haga el trabajo sucio
        $liquidadas = $this->modeloObra->liquidarSubastasVencidas();
        
        // Devolvemos el resultado al frontend
        echo json_encode(["success" => true, "liquidadas" => $liquidadas]);
        exit();
    }

    /**
     * ENDPOINT: Confirmar Recepción (Liberar Fondos)
     * Recibe una petición POST desde la "Bóveda" del usuario cuando hace clic en "Confirmar Recepción".
     */
    public function confirmarRecepcionObra() {
        session_start();
        header('Content-Type: application/json');

        // Blindaje: Solo usuarios logueados pueden tocar el sistema financiero.
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["success" => false, "message" => "Sesión no válida o caducada."]);
            exit();
        }

        // Leemos el JSON que nos manda el script.js a través de fetch
        $datos = json_decode(file_get_contents("php://input"), true);
        $id_obra = (int) $datos['id_obra'];
        
        // Usamos el ID de la sesión por seguridad, nunca confiamos en un ID enviado desde el frontend
        $id_comprador = $_SESSION['user_id'];

        // Llamamos al motor transaccional del modelo Puja
        $resultado = $this->modeloPuja->confirmarRecepcion($id_obra, $id_comprador);
        
        // Devolvemos si fue un éxito o si el motor escupió un error (Exception)
        echo json_encode($resultado);
        exit();
    }
}
?>