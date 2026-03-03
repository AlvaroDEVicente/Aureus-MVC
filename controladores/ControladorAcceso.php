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

class ControladorAcceso
{

    // Propiedades para guardar nuestras herramientas
    private $bd;
    private $modeloUsuario;

    /**
     * El constructor prepara el terreno cada vez que el enrutador llama a este controlador.
     */
    public function __construct()
    {
        // Pedimos la conexión única (Singleton) a nuestra Base de Datos
        $this->bd = BaseDatos::getInstance()->getConnection();

        // Creamos una instancia de nuestro modelo de Usuario, dándole la conexión
        $this->modeloUsuario = new Usuario($this->bd);
    }

    /**
     * Procesa la petición POST del formulario login.php
     */
    public function procesarLogin()
    {
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
    public function cerrarSesion()
    {
        session_start();
        session_destroy();

        // Redirigimos al login con un mensaje de éxito
        header("Location: public/login.php?msg=sesion_cerrada");
        exit();
    }

    /**
     * Devuelve los datos de sesión y saldos al Frontend.
     */
    public function obtenerUsuario()
    {
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
     * Procesa la recarga de saldo de un Mecenas (Simulada antigua).
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
        $monto = (float) $datos['monto'];

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
     * Ahora fuerza a que todos los nuevos usuarios sean 'compradores'.
     */
    public function procesarRegistro() {
        $nombre = $_POST['nombre'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $dni = $_POST['dni'] ?? '';
        $telefono = $_POST['telefono'] ?? '';

        // FORZAMOS EL ROL: Nadie nace siendo artista.
        $rol = 'comprador';

        // OJO: Tu modelo registrarUsuario() probablemente espere también el campo es_artista.
        // Asumiendo que tu modelo.php lo necesite, le pasamos un 0. Si tu modelo
        // solo espera los 6 parámetros (nombre, email, pass, rol, dni, tlf), el código de abajo funciona perfecto.
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

    public function obtenerUsuarios()
    {
        session_start();
        header('Content-Type: application/json');

        // BLINDAJE ACL: Solo el administrador puede ver esto
        if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
            echo json_encode(["error" => "Acceso denegado. Exclusivo del Senado."]);
            exit();
        }

        echo json_encode($this->modeloUsuario->obtenerTodos());
        exit();
    }

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
     * Valida la transacción con PayPal y actualiza el saldo del Mecenas de forma segura.
     * (Versión adaptada para XAMPP local por Robertus)
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
            echo json_encode(["success" => false, "message" => "No se recibió el identificador de la orden."]);
            exit();
        }

        // --- TUS CREDENCIALES DE PAYPAL ---
        $clientId = "AV6U32dmQP_D374qGRyhD3_THQPhwQ_HOOSdoVDljYflDlc4UqniKdwYWV9tCjANmc_niIpZHUfgoW_Q";
        $secret = "ELLrBLpsfk4EIMMnxP-HVMAxSult3foEed0CGsbnJOe8Ot_Ath_wdsarl6p16WIuzagtJnNSuVVz-2yE";

        // 1. Pedir a PayPal el Token de Acceso
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api-m.sandbox.paypal.com/v1/oauth2/token");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json", "Accept-Language: en_US"));
        curl_setopt($ch, CURLOPT_USERPWD, $clientId . ":" . $secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // PARCHE XAMPP: Desactivar verificación estricta de SSL en local
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $res_token = json_decode(curl_exec($ch), true);
        $access_token = $res_token['access_token'] ?? null;

        if (!$access_token) {
            echo json_encode(["success" => false, "message" => "Fallo de autenticación con la pasarela de PayPal (Posible error de credenciales o SSL)."]);
            exit();
        }

        // 2. Comprobar cómo está la Orden usando ese Token
        curl_setopt($ch, CURLOPT_URL, "https://api-m.sandbox.paypal.com/v2/checkout/orders/" . $orderID);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Bearer " . $access_token
        ));
        curl_setopt($ch, CURLOPT_POST, false);

        // PARCHE XAMPP: Desactivar verificación estricta de SSL de nuevo
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $orden = json_decode(curl_exec($ch), true);
        curl_close($ch);

        // 3. Decisión final
        if (isset($orden['status']) && ($orden['status'] === 'APPROVED' || $orden['status'] === 'COMPLETED')) {
            $monto_pagado = (float) $orden['purchase_units'][0]['amount']['value'];

            // Usamos el modelo Usuario para guardar los fondos
            $exito = $this->modeloUsuario->actualizarFondos($_SESSION['user_id'], $monto_pagado);

            if ($exito) {
                echo json_encode(["success" => true, "monto_anadido" => $monto_pagado]);
            } else {
                echo json_encode(["success" => false, "message" => "Pago verificado, pero falló la escritura en la bóveda de datos."]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "El pago no consta como finalizado en los registros de PayPal."]);
        }
    }

    /**
     * Procesa el pago de 19,99€ y asciende al ciudadano a Artista.
     * Creado por Alvarus en la rama de mejoras finales.
     */
    public function ascenderArtista() {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["success" => false, "message" => "Sesión caducada."]); 
            exit();
        }

        // Llamamos al modelo para hacer la magia financiera
        $resultado = $this->modeloUsuario->pagarLicenciaArtista($_SESSION['user_id']);
        
        if ($resultado === true) {
            // Actualizamos la sesión del servidor para que sepa que ya es artista
            $_SESSION['user_rol'] = 'artista';
            echo json_encode(["success" => true]);
        } else {
            // Si devuelve un texto, es que no hay dinero o hubo un error
            echo json_encode(["success" => false, "message" => $resultado]);
        }
        exit();
    }

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
}
?>