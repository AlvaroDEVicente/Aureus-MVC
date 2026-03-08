<?php
/**
 * Proyecto Intermodular: AUREUS
 * Capa: Controlador (Lógica de Negocio y API REST)
 * Archivo: controladores/ControladorAcceso.php
 * * Descripción:
 * Controlador encargado de la gestión de la autenticación de usuarios, 
 * control de sesiones, operaciones financieras (depósitos y pagos) 
 * y gestión de perfiles. Implementa un sistema de Control de Acceso 
 * Basado en Roles (RBAC) para proteger los endpoints administrativos.
 */

require_once 'modelos/BaseDatos.php';
require_once 'modelos/Usuario.php';

class ControladorAcceso
{
    /** @var mysqli Instancia de la conexión a la base de datos. */
    private $bd;

    /** @var Usuario Instancia del modelo Usuario. */
    private $modeloUsuario;

    /**
     * Constructor de la clase.
     * Inicializa la conexión a la base de datos mediante el patrón Singleton 
     * e instancia el modelo correspondiente.
     */
    public function __construct()
    {
        $this->bd = BaseDatos::getInstance()->getConnection();
        $this->modeloUsuario = new Usuario($this->bd);
    }

    /**
     * Procesa la solicitud de inicio de sesión de un usuario.
     * Evalúa las credenciales y verifica el estado lógico del registro (baneo).
     * * @return void Redirige a la interfaz principal o devuelve un error por URL.
     */
    public function procesarLogin()
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $usuario = $this->modeloUsuario->login($email, $password);

        if ($usuario) {
            // Verificación de borrado lógico (usuario inactivo/baneado)
            if ($usuario['activo'] == 0) {
                header("Location: public/login.php?error=baneado");
                exit();
            }

            // Inicialización segura de la sesión
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
     * Destruye la sesión activa del usuario y limpia los datos de autenticación.
     * * @return void
     */
    public function cerrarSesion()
    {
        session_start();
        session_destroy();
        header("Location: public/login.php?msg=sesion_cerrada");
        exit();
    }

    /**
     * Endpoint: Obtiene los datos del usuario autenticado en formato JSON.
     * Utilizado para inicializar el estado del Frontend (SPA).
     * * @return void
     */
    public function obtenerUsuario()
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["error" => "No autorizado. Sesión inactiva."]);
            exit();
        }

        $datos = $this->modeloUsuario->obtenerPorId($_SESSION['user_id']);
        echo json_encode($datos);
        exit();
    }

    /**
     * Endpoint: Permite añadir fondos al saldo disponible del usuario.
     * * @return void Devuelve un JSON con el resultado de la operación.
     */
    public function ingresarFondos()
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["success" => false, "message" => "Sesión no válida"]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $monto = (float) ($datos['monto'] ?? 0);

        if ($monto <= 0) {
            echo json_encode(["success" => false, "message" => "Cuantía inválida."]);
            exit();
        }

        $exito = $this->modeloUsuario->actualizarFondos($_SESSION['user_id'], $monto);
        echo json_encode(["success" => $exito]);
        exit();
    }

    /**
     * Procesa la inserción de un nuevo usuario en el sistema.
     * Implementa el principio de mínimo privilegio asignando el rol base ('comprador').
     * * @return void Redirige según el resultado de la inserción.
     */
    public function procesarRegistro() 
    {
        $nombre = $_POST['nombre'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $dni = $_POST['dni'] ?? '';
        $telefono = $_POST['telefono'] ?? '';

        $rol = 'comprador';

        $exito = $this->modeloUsuario->registrarUsuario($nombre, $email, $password, $rol, $dni, $telefono);

        if ($exito) {
            header("Location: public/login.php?registro=exito");
        } else {
            header("Location: public/registro.php?error=duplicado");
        }
        exit();
    }

    /**
     * Endpoint administrativo: Retorna la totalidad de usuarios registrados.
     * Implementa protección RBAC (exclusivo 'admin').
     * * @return void
     */
    public function obtenerUsuarios()
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["error" => "Acceso denegado. Se requieren privilegios de administración."]);
            exit();
        }

        echo json_encode($this->modeloUsuario->obtenerTodos());
        exit();
    }

    /**
     * Endpoint administrativo: Modifica el rol de un usuario en el sistema.
     * * @return void
     */
    public function cambiarRolUsuario()
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["success" => false, "message" => "Acceso denegado."]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $exito = $this->modeloUsuario->actualizarRol($datos['id_usuario'], $datos['rol']);

        echo json_encode(["success" => $exito]);
        exit();
    }

    /**
     * Endpoint administrativo: Aplica un borrado lógico (desactivación) a un usuario.
     * * @return void
     */
    public function eliminarUsuario()
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["success" => false, "message" => "Acceso denegado."]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $exito = $this->modeloUsuario->borrarUsuario($datos['id_usuario']);

        echo json_encode(["success" => $exito]);
        exit();
    }

    /**
     * Endpoint: Gestiona la captura y verificación de un pago procesado mediante la API de PayPal.
     * Establece una comunicación segura server-to-server mediante OAuth2.
     * * @return void
     */
    public function capturarPagoPayPal()
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["success" => false, "message" => "Sesión no válida o caducada."]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $orderID = $datos['orderID'] ?? '';

        if (empty($orderID)) {
            echo json_encode(["success" => false, "message" => "Identificador de orden no proporcionado."]);
            exit();
        }

        // Credenciales del entorno Sandbox de PayPal
        $clientId = "AV6U32dmQP_D374qGRyhD3_THQPhwQ_HOOSdoVDljYflDlc4UqniKdwYWV9tCjANmc_niIpZHUfgoW_Q";
        $secret = "ELLrBLpsfk4EIMMnxP-HVMAxSult3foEed0CGsbnJOe8Ot_Ath_wdsarl6p16WIuzagtJnNSuVVz-2yE";

        // Fase 1: Obtención del Token de Acceso (OAuth2)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api-m.sandbox.paypal.com/v1/oauth2/token");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json", "Accept-Language: en_US"));
        curl_setopt($ch, CURLOPT_USERPWD, $clientId . ":" . $secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $res_token = json_decode(curl_exec($ch), true);
        $access_token = $res_token['access_token'] ?? null;

        if (!$access_token) {
            echo json_encode(["success" => false, "message" => "Fallo de autenticación con la pasarela de pago."]);
            exit();
        }

        // Fase 2: Verificación de la Orden de Pago
        curl_setopt($ch, CURLOPT_URL, "https://api-m.sandbox.paypal.com/v2/checkout/orders/" . $orderID);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Bearer " . $access_token
        ));
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $orden = json_decode(curl_exec($ch), true);
        curl_close($ch);

        // Fase 3: Validación del estado y actualización de la base de datos
        if (isset($orden['status']) && ($orden['status'] === 'APPROVED' || $orden['status'] === 'COMPLETED')) {
            $monto_pagado = (float) $orden['purchase_units'][0]['amount']['value'];
            $exito = $this->modeloUsuario->actualizarFondos($_SESSION['user_id'], $monto_pagado);

            if ($exito) {
                echo json_encode(["success" => true, "monto_anadido" => $monto_pagado]);
            } else {
                echo json_encode(["success" => false, "message" => "El pago fue verificado, pero ocurrió un error interno de persistencia."]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "La transacción no consta como finalizada en el procesador."]);
        }
    }

    /**
     * Endpoint: Procesa el pago de la licencia y la actualización de rol a 'artista'.
     * * @return void
     */
    public function ascenderArtista() 
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["success" => false, "message" => "Sesión caducada."]); 
            exit();
        }

        $resultado = $this->modeloUsuario->pagarLicenciaArtista($_SESSION['user_id']);
        
        if ($resultado === true) {
            $_SESSION['user_rol'] = 'artista';
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => $resultado]);
        }
        exit();
    }

    /**
     * Endpoint administrativo: Revierte el borrado lógico de un usuario.
     * * @return void
     */
    public function amnistiarUsuario()
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["success" => false, "message" => "Acceso denegado."]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $exito = $this->modeloUsuario->amnistiarUsuario($datos['id_usuario']);

        echo json_encode(["success" => $exito]);
        exit();
    }

    /**
     * Endpoint: Devuelve la información extendida del perfil del usuario autenticado.
     * * @return void
     */
    public function obtenerMiPerfil() 
    {
        session_start();
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["error" => true, "message" => "No autorizado"]); 
            exit();
        }
        
        $perfil = $this->modeloUsuario->obtenerPerfil($_SESSION['user_id']);
        echo json_encode($perfil);
        exit();
    }

    /**
     * Endpoint: Actualiza el campo de biografía del usuario.
     * * @return void
     */
    public function guardarBiografia() 
    {
        session_start();
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["success" => false]); 
            exit();
        }
        
        $datos = json_decode(file_get_contents("php://input"), true);
        $exito = $this->modeloUsuario->actualizarBiografiaBD($_SESSION['user_id'], $datos['biografia']);
        
        echo json_encode(["success" => $exito]);
        exit();
    }

    /**
     * Endpoint administrativo: Inyecta saldo arbitrario en la cuenta de un usuario.
     * Evade las pasarelas de pago. Exclusivo para operaciones de testing y soporte.
     * * @return void
     */
    public function adminSumarFondos() 
    {
        session_start();
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["success" => false, "message" => "Permisos insuficientes."]);
            exit();
        }

        $datos = json_decode(file_get_contents("php://input"), true);
        $id_usuario = (int)($datos['id_usuario'] ?? 0);
        $cantidad = (float)($datos['cantidad'] ?? 0);

        if ($id_usuario <= 0 || $cantidad <= 0) {
            echo json_encode(["success" => false, "message" => "Parámetros de entrada inválidos."]);
            exit();
        }

        $exito = $this->modeloUsuario->adminInyectarFondos($id_usuario, $cantidad);
        echo json_encode(["success" => $exito, "message" => $exito ? "Fondos inyectados correctamente." : "Fallo en la persistencia de datos."]);
        exit();
    }

    /**
     * Endpoint: Extrae el registro de auditoría (Libro Mayor) de un usuario específico.
     * * @return void
     */
    public function obtenerHistorialFinanciero() 
    {
        session_start();
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode([]); 
            exit();
        }
        
        $historial = $this->modeloUsuario->obtenerHistorialTransacciones($_SESSION['user_id']);
        echo json_encode($historial);
        exit();
    }
}
?>