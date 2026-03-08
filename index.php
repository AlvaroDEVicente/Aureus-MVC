<?php
/**
 * Proyecto Intermodular: AUREUS
 * Capa: Enrutador Principal (Front Controller Pattern)
 * Archivo: index.php
 * Descripción:
 * Intercepta todas las peticiones HTTP entrantes dirigidas al backend.
 * Actúa como un semáforo (Router), instanciando el controlador adecuado 
 * y delegando la ejecución del endpoint solicitado según el parámetro 'accion'.
 * Implementa supresión de errores en la salida estándar para evitar la corrupción
 * de las respuestas JSON esperadas por la Single Page Application (SPA).
 */

// 1. Inicialización del entorno de ejecución
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', 0); // Ocultar errores directos para proteger la estructura JSON
date_default_timezone_set('Europe/Madrid');

// 2. Importación de las dependencias de control
require_once 'controladores/ControladorAcceso.php';
require_once 'controladores/ControladorSubasta.php';

// 3. Captura y normalización del parámetro de enrutamiento
$accion = $_GET['accion'] ?? 'inicio';

// 4. Árbol de Enrutamiento (Switch Dispatcher)
switch ($accion) {

    // =======================================================
    // BLOQUE 1: MÓDULO DE AUTENTICACIÓN Y PERFILES (ControladorAcceso)
    // =======================================================
    case 'procesar_login':
        (new ControladorAcceso())->procesarLogin();
        break;
    case 'logout':
        (new ControladorAcceso())->cerrarSesion();
        break;
    case 'procesar_registro':
        (new ControladorAcceso())->procesarRegistro();
        break;
    case 'obtener_usuario':
        (new ControladorAcceso())->obtenerUsuario();
        break;
    case 'obtener_mi_perfil':
        (new ControladorAcceso())->obtenerMiPerfil();
        break;
    case 'guardar_biografia':
        (new ControladorAcceso())->guardarBiografia();
        break;
    case 'ingresar_fondos':
        (new ControladorAcceso())->ingresarFondos();
        break;
    case 'capturar_pago_paypal':
        (new ControladorAcceso())->capturarPagoPayPal();
        break;
    case 'ascender_artista':
        (new ControladorAcceso())->ascenderArtista();
        break;
    case 'obtener_historial_financiero':
        (new ControladorAcceso())->obtenerHistorialFinanciero();
        break;

    // =======================================================
    // BLOQUE 2: MÓDULO TRANSACCIONAL Y DE CATÁLOGO (ControladorSubasta)
    // =======================================================
    case 'obtener_catalogo':
        (new ControladorSubasta())->obtenerCatalogo();
        break;
    case 'obtener_detalle':
        (new ControladorSubasta())->obtenerDetalleObra();
        break;
    case 'obtener_ticker':
        (new ControladorSubasta())->obtenerTickerGlobal();
        break;
    case 'sellar_transaccion':
        (new ControladorSubasta())->sellarTransaccion();
        break;
    case 'obtener_mis_pujas':
        (new ControladorSubasta())->obtenerMisPujas();
        break;
    case 'confirmar_recepcion':
        (new ControladorSubasta())->confirmarRecepcionObra();
        break;
    case 'subir_obra':
        (new ControladorSubasta())->subirObra();
        break;
    case 'obtener_taller':
        (new ControladorSubasta())->obtenerTaller();
        break;
    case 'liquidar_vencidas':
        (new ControladorSubasta())->liquidarVencidas();
        break;

    // =======================================================
    // BLOQUE 3: MÓDULO ADMINISTRATIVO (Control de Entidades)
    // =======================================================
    case 'obtener_usuarios':
        (new ControladorAcceso())->obtenerUsuarios();
        break;
    case 'cambiar_rol_usuario':
        (new ControladorAcceso())->cambiarRolUsuario();
        break;
    case 'eliminar_usuario':
        (new ControladorAcceso())->eliminarUsuario();
        break;
    case 'amnistiar_usuario':
        (new ControladorAcceso())->amnistiarUsuario();
        break;
    case 'obtener_pendientes':
        (new ControladorSubasta())->obtenerPendientes();
        break;
    case 'obtener_detalle_revision':
        (new ControladorSubasta())->obtenerDetalleRevision();
        break;
    case 'aprobar_obra':
        (new ControladorSubasta())->aprobarObra();
        break;
    case 'rechazar_obra':
        (new ControladorSubasta())->rechazarObra();
        break;

    // =======================================================
    // BLOQUE 4: OPERACIONES DE TESTING Y OVERRIDE (Modo Dios)
    // =======================================================
    case 'admin_sumar_fondos':
        (new ControladorAcceso())->adminSumarFondos();
        break;
    case 'admin_modificar_tiempo':
        (new ControladorSubasta())->adminModificarTiempo();
        break;
    case 'admin_borrar_obra':
        (new ControladorSubasta())->adminBorrarObra();
        break;

    // =======================================================
    // BLOQUE 5: REDIRECCIÓN POR DEFECTO (Acceso SPA)
    // =======================================================
    case 'inicio':
    default:
        header("Location: public/index.html");
        exit();
        break;
}
?>