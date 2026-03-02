<?php
/**
 * AUREUS - Proyecto Intermodular
 * Capa: FRONT CONTROLLER (Enrutador Principal)
 * Archivo: index.php
 * Descripción: Recibe TODAS las peticiones del cliente (tanto formularios clásicos 
 * como peticiones Fetch/JSON), instancia el controlador adecuado y ejecuta la acción.
 */

// 1. Importamos los controladores que hemos construido
require_once 'controladores/ControladorAcceso.php';
require_once 'controladores/ControladorSubasta.php'; // Ya está activo para el catálogo

// 2. Capturamos la 'accion' que nos pide el Frontend por la URL.
// Si no nos pasan ninguna, asumimos que quieren ir al 'inicio'.
$accion = $_GET['accion'] ?? 'inicio';

// 3. El semáforo (Switch) que dirige el tráfico de Aureus
switch ($accion) {

    // =======================================================
    // BLOQUE 1: RUTAS DE IDENTIFICACIÓN (ControladorAcceso)
    // =======================================================
    case 'procesar_login':
        $controlador = new ControladorAcceso();
        $controlador->procesarLogin();
        break;

    case 'logout':
        $controlador = new ControladorAcceso();
        $controlador->cerrarSesion();
        break;

    case 'obtener_usuario':
        $controlador = new ControladorAcceso();
        $controlador->obtenerUsuario();
        break;

    case 'ingresar_fondos':
        $controlador = new ControladorAcceso();
        $controlador->ingresarFondos();
        break;

    case 'capturar_pago_paypal':
        $controlador = new ControladorAcceso();
        $controlador->capturarPagoPayPal();
        break;

    case 'procesar_registro':
        $controlador = new ControladorAcceso();
        $controlador->procesarRegistro();
        break;

    case 'obtener_usuarios':
        $controlador = new ControladorAcceso();
        $controlador->obtenerUsuarios();
        break;

    case 'cambiar_rol_usuario':
        $controlador = new ControladorAcceso();
        $controlador->cambiarRolUsuario();
        break;

    case 'eliminar_usuario':
        $controlador = new ControladorAcceso();
        $controlador->eliminarUsuario();
        break;

    // =======================================================
    // BLOQUE 2: RUTAS DE OBRAS Y TALLER (ControladorSubasta)
    // =======================================================
    case 'obtener_catalogo':
        $controlador = new ControladorSubasta();
        $controlador->obtenerCatalogo();
        break;

    case 'subir_obra':
        $controlador = new ControladorSubasta();
        $controlador->subirObra();
        break;

    case 'obtener_pendientes':
        $controlador = new ControladorSubasta();
        $controlador->obtenerPendientes();
        break;

    case 'aprobar_obra':
        $controlador = new ControladorSubasta();
        $controlador->aprobarObra();
        break;

    case 'obtener_taller':
        $controlador = new ControladorSubasta();
        $controlador->obtenerTaller();
        break;

    case 'obtener_mis_pujas':
        $controlador = new ControladorSubasta();
        $controlador->obtenerMisPujas();
        break;

    // --- ESTOS SON LOS 3 CASOS QUE FALTABAN/FALLABAN ---
    case 'obtener_detalle':
        $controlador = new ControladorSubasta();
        // ¡Cuidado! En vuestro controlador se llama obtenerDetalleObra
        $controlador->obtenerDetalleObra();
        break;

    case 'sellar_transaccion':
        $controlador = new ControladorSubasta();
        $controlador->sellarTransaccion();
        break;

    case 'obtener_ticker':
        $controlador = new ControladorSubasta();
        // ¡Cuidado! En vuestro controlador se llama obtenerTickerGlobal
        $controlador->obtenerTickerGlobal();
        break;

    // =======================================================
    // BLOQUE 3: RUTA POR DEFECTO (Redirección a la SPA)
    // =======================================================
    case 'inicio':
    default:
        // Si entran a "localhost/aureus/" sin pedir ninguna acción concreta,
        // los redirigimos directamente a la interfaz visual (SPA).
        header("Location: public/index.html");
        exit();
        break;
}
?>