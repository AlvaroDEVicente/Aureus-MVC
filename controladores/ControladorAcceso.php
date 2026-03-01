<?php
/**
 * AUREUS - Proyecto Intermodular
 * Capa: CONTROLADOR (Lógica de Negocio)
 * Archivo: controladores/ControladorAcceso.php
 * Descripción: Orquesta las acciones de autenticación (Login/Logout).
 * Actúa como intermediario entre la vista (formularios) y el modelo (base de datos).
 */

// Importamos el "Músculo" que este "Cerebro" va a necesitar
require_once 'modelos/BaseDatos.php';
require_once 'modelos/Usuario.php';

class ControladorAcceso {
    
    // Propiedades para guardar nuestras herramientas
    private $bd;
    private $modeloUsuario;

    /**
     * El constructor prepara el terreno cada vez que el enrutador llama a este controlador.
     */
    public function __construct() {
        // Pedimos la conexión única (Singleton) a nuestra Base de Datos
        $this->bd = BaseDatos::getInstance()->getConnection();
        
        // Creamos una instancia de nuestro modelo de Usuario, dándole la conexión
        $this->modeloUsuario = new Usuario($this->bd);
    }

    /**
     * Procesa la petición POST del formulario login.php
     */
        public function procesarLogin() {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $usuario = $this->modeloUsuario->login($email, $password);

        if ($usuario) {
            // NUEVO: Comprobamos si el usuario ha sido borrado lógicamente
            if ($usuario['activo'] == 0) {
                // Lo enviamos de vuelta con un código de error específico para baneados
                header("Location: public/login.php?error=baneado");
                exit();
            }

            // Login correcto (código original)
            session_start();
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_nombre'] = $usuario['nombre'];
            $_SESSION['user_rol'] = $usuario['rol'];

            header("Location: public/index.html");
            exit();
        } else {
            header("Location: public/login.php?error=1");
            exit();
        }
    }

    /**
     * Destruye la sesión actual y devuelve al usuario a la pantalla de acceso.
     */
    public function cerrarSesion() {
        session_start();
        session_destroy();
        
        // Redirigimos al login con un mensaje de éxito
        header("Location: public/login.php?msg=sesion_cerrada");
        exit();
    }

    /**
     * Devuelve los datos de sesión y saldos al Frontend.
     */
    public function obtenerUsuario() {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["error" => "No autorizado"]); 
            exit();
        }

        $datos = $this->modeloUsuario->obtenerPorId($_SESSION['user_id']);
        echo json_encode($datos);
        exit();
    }

    /**
     * Procesa la recarga de saldo de un Mecenas.
     */
    public function ingresarFondos() {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["success" => false, "message" => "Sesión no válida"]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $monto = (float)$datos['monto'];

        if ($monto <= 0) {
            echo json_encode(["success" => false, "message" => "Cuantía inválida."]);
            exit();
        }

        $exito = $this->modeloUsuario->actualizarFondos($_SESSION['user_id'], $monto);
        
        echo json_encode(["success" => $exito]);
        exit();
    }

    /**
     * Procesa la solicitud POST del formulario de registro.
     */
    public function procesarRegistro() {
        $nombre = $_POST['nombre'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'comprador';
        $dni = $_POST['dni'] ?? '';
        $telefono = $_POST['telefono'] ?? '';

        $exito = $this->modeloUsuario->registrarUsuario($nombre, $email, $password, $rol, $dni, $telefono);

        if ($exito) {
            // Redirigimos al login con mensaje de éxito
            header("Location: public/login.php?registro=exito");
        } else {
            // Redirigimos al registro con mensaje de error (email duplicado)
            header("Location: public/registro.php?error=duplicado");
        }
        exit();
    }

    public function obtenerUsuarios() {
        session_start();
        header('Content-Type: application/json');
        
        // BLINDAJE ACL: Solo el administrador puede ver esto
        if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["error" => "Acceso denegado. Exclusivo del Senado."]); exit();
        }
        
        echo json_encode($this->modeloUsuario->obtenerTodos());
        exit();
    }

    public function cambiarRolUsuario() {
        session_start();
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["success" => false, "message" => "Acceso denegado."]); exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $exito = $this->modeloUsuario->actualizarRol($datos['id_usuario'], $datos['rol']);
        
        echo json_encode(["success" => $exito]);
        exit();
    }

    public function eliminarUsuario() {
        session_start();
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["success" => false, "message" => "Acceso denegado."]); exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $exito = $this->modeloUsuario->borrarUsuario($datos['id_usuario']);
        
        echo json_encode(["success" => $exito]);
        exit();
    }
}
?>