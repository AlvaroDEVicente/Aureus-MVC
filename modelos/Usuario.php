<?php
/**
 * Proyecto Intermodular: AUREUS
 * Capa: Modelo (Lógica de Datos)
 * Archivo: modelos/Usuario.php
 * Descripción:
 * Gestiona el ciclo de vida, autenticación y transacciones monetarias 
 * locales vinculadas al perfil del usuario. Integra los registros 
 * de auditoría (Libro Mayor) para garantizar la trazabilidad de operaciones.
 */

class Usuario {
    
    /** @var mysqli Objeto de conexión a la base de datos. */
    private $db; 

    /**
     * Constructor de la clase.
     * @param mysqli $conexion Conexión activa a la base de datos.
     */
    public function __construct($conexion) {
        $this->db = $conexion; 
    }

    /**
     * Verifica la validez de las credenciales de acceso.
     * @param string $email Correo electrónico del usuario.
     * @param string $password Contraseña provista.
     * @return array|false Datos esenciales de la sesión si es correcto, false si falla.
     */
    public function login($email, $password) {
        $sql = "SELECT id_usuario, nombre, password, rol, activo FROM usuario WHERE email = ?";
        
        $stmt = $this->db->prepare($sql); 
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($fila = $resultado->fetch_assoc()) {
            if (password_verify($password, $fila['password'])) {
                return [
                    'id' => $fila['id_usuario'],
                    'nombre' => $fila['nombre'],
                    'rol' => $fila['rol'],
                    'activo' => $fila['activo']
                ];
            }
        }
        return false; 
    }

    /**
     * Consulta el estado financiero y operativo de una cuenta específica.
     * @param int $id_usuario Identificador de la cuenta.
     * @return array|null Resultados mapeados.
     */
    public function obtenerPorId($id_usuario) {
        $sql = "SELECT id_usuario, nombre, saldo_disponible, saldo_bloqueado, es_artista, rol FROM usuario WHERE id_usuario = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($fila = $resultado->fetch_assoc()) {
            $fila['id'] = $fila['id_usuario']; 
            return $fila;
        }
        return null;
    }

    /**
     * Modifica el saldo contable de un usuario y registra la operación en el libro de auditoría.
     * @param int $id_usuario Identificador del usuario.
     * @param float $monto Variación de capital a aplicar.
     * @return bool
     */
    public function actualizarFondos($id_usuario, $monto) {
        $sql = "UPDATE usuario SET saldo_disponible = saldo_disponible + ? WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("di", $monto, $id_usuario);
        $exito = $stmt->execute();

        if ($exito) {
            $detalle = "Ingreso de capital registrado: +" . number_format($monto, 2) . " €";
            
            // Implementación de la captura dinámica de IP (Bloque 2)
            $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            
            $sql_log = "INSERT INTO log_sistema (id_usuario, accion, detalle, ip, fecha) VALUES (?, 'INGRESO', ?, ?, NOW())";
            $stmt_log = $this->db->prepare($sql_log);
            $stmt_log->bind_param("iss", $id_usuario, $detalle, $ip_usuario);
            $stmt_log->execute();
        }
        return $exito;
    }

    /**
     * Formaliza la creación de un nuevo registro de identidad en el sistema.
     * @param string $nombre Identidad del titular.
     * @param string $email Correo de contacto.
     * @param string $password Credencial no cifrada.
     * @param string $rol Rol asignado por defecto.
     * @param string $dni Documento de identificación.
     * @param string $telefono Número de contacto.
     * @return bool False si existe colisión de correos, True en caso de registro exitoso.
     */
    public function registrarUsuario($nombre, $email, $password, $rol, $dni, $telefono) {
        $sql_check = "SELECT id_usuario FROM usuario WHERE email = ?";
        $stmt_check = $this->db->prepare($sql_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            return false; 
        }

        $pass_segura = password_hash($password, PASSWORD_DEFAULT);
        $es_artista = ($rol === 'artista') ? 1 : 0;

        $sql = "INSERT INTO usuario (nombre, email, password, rol, es_artista, dni, telefono) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssssiss", $nombre, $email, $pass_segura, $rol, $es_artista, $dni, $telefono);
        
        return $stmt->execute();
    }

    /**
     * Extrae el compendio de usuarios del sistema. (Uso administrativo exclusivo).
     * @return array
     */
    public function obtenerTodos() {
        $sql = "SELECT id_usuario, nombre, email, rol, saldo_disponible, activo, fecha_registro FROM usuario ORDER BY fecha_registro DESC";
        $resultado = $this->db->query($sql);
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Actualiza la política de acceso de un usuario específico.
     * @param int $id_usuario Identificador objetivo.
     * @param string $nuevo_rol Nivel de acceso concedido.
     * @return bool
     */
    public function actualizarRol($id_usuario, $nuevo_rol) {
        $sql = "UPDATE usuario SET rol = ? WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $nuevo_rol, $id_usuario);
        return $stmt->execute();
    }

    /**
     * Aplica una desactivación lógica sobre una cuenta.
     * @param int $id_usuario Identificador de la cuenta.
     * @return bool
     */
    public function borrarUsuario($id_usuario) {
        $sql = "UPDATE usuario SET activo = 0 WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        return $stmt->execute();
    }

    /**
     * Modifica el estado lógico de una cuenta inactiva restaurando su acceso.
     * @param int $id_usuario Identificador de la cuenta.
     * @return bool
     */
    public function amnistiarUsuario($id_usuario) {
        $sql = "UPDATE usuario SET activo = 1 WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        return $stmt->execute();
    }

    /**
     * Efectúa el cobro por adquisición de permisos de autoría, gestionando el pago de forma transaccional.
     * @param int $id_usuario Identificador del cliente.
     * @return bool|string True si la operación se completó, mensaje de error en caso adverso.
     */
    public function pagarLicenciaArtista($id_usuario) {
        $coste = 19.99;

        try {
            $this->db->autocommit(FALSE);

            $sql_check = "SELECT saldo_disponible FROM usuario WHERE id_usuario = ?";
            $stmt_check = $this->db->prepare($sql_check);
            $stmt_check->bind_param("i", $id_usuario);
            $stmt_check->execute();
            $resultado = $stmt_check->get_result();
            $user = $resultado->fetch_assoc();

            if (!$user || $user['saldo_disponible'] < $coste) {
                $this->db->rollback(); 
                $this->db->autocommit(TRUE); 
                return "Disponibilidad de fondos insuficiente para cubrir la cuota de licencia.";
            }

            $sql_update = "UPDATE usuario SET saldo_disponible = saldo_disponible - ?, rol = 'artista', es_artista = 1 WHERE id_usuario = ?";
            $stmt_update = $this->db->prepare($sql_update);
            $stmt_update->bind_param("di", $coste, $id_usuario);
            $stmt_update->execute();

            $accion = 'PAGO_LICENCIA';
            $detalle = "Adquisición de Licencia Comercial. Cargo aplicado: {$coste}€";
            $ip = substr($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 0, 45);

            $sql_log = "INSERT INTO log_sistema (id_usuario, accion, detalle, ip) VALUES (?, ?, ?, ?)";
            $stmt_log = $this->db->prepare($sql_log);
            $stmt_log->bind_param("isss", $id_usuario, $accion, $detalle, $ip);
            $stmt_log->execute();

            $this->db->commit();
            $this->db->autocommit(TRUE); 
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            $this->db->autocommit(TRUE);
            return "Fallo en el proceso transaccional: " . $e->getMessage();
        }
    }

    /**
     * Retorna los datos requeridos para estructurar la vista de perfil de usuario.
     * @param int $id_usuario Identificador del cliente.
     * @return array|null
     */
    public function obtenerPerfil($id_usuario) {
        $sql = "SELECT nombre, email, dni, rol, avatar_url, biografia FROM usuario WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Actualiza el registro textual correspondiente a la biografía del usuario.
     * @param int $id_usuario Identificador del cliente.
     * @param string $biografia Contenido provisto.
     * @return bool
     */
    public function actualizarBiografiaBD($id_usuario, $biografia) {
        $sql = "UPDATE usuario SET biografia = ? WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $biografia, $id_usuario);
        return $stmt->execute();
    }

    // ==========================================================
    // OPERACIONES ADMINISTRATIVAS (AUDITORÍA EXCEPCIONAL)
    // ==========================================================

    /**
     * Ajuste artificial de fondos aplicado por un administrador de sistemas.
     * @param int $id_usuario Identidad del receptor.
     * @param float $cantidad Variación estática de saldo.
     * @return bool
     */
    public function adminInyectarFondos($id_usuario, $cantidad) {
        $sql = "UPDATE usuario SET saldo_disponible = saldo_disponible + ? WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("di", $cantidad, $id_usuario);
        $exito = $stmt->execute();

        if ($exito) {
            $detalle = "Rectificación administrativa de saldo. Incremento: +" . number_format($cantidad, 2) . " €";
            
            // Implementación de la captura dinámica de IP (Bloque 2)
            $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            
            $sql_log = "INSERT INTO log_sistema (id_usuario, accion, detalle, ip, fecha) VALUES (?, 'INGRESO', ?, ?, NOW())";
            $stmt_log = $this->db->prepare($sql_log);
            $stmt_log->bind_param("iss", $id_usuario, $detalle, $ip_usuario);
            $stmt_log->execute();
        }
        return $exito;
    }

    /**
     * Compila y provee la secuencia cronológica de variaciones financieras del usuario.
     * @param int $id_usuario Identificador de cuenta.
     * @return array Retorna la tabla de resultados.
     */
    public function obtenerHistorialTransacciones($id_usuario) {
        $sql = "SELECT accion, detalle, fecha FROM log_sistema WHERE id_usuario = ? ORDER BY fecha DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>