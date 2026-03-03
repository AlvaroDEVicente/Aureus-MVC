<?php
/**
 * AUREUS - Proyecto Intermodular
 * Capa: MODELO (Lógica de Datos)
 * Archivo: models/Usuario.php
 * Descripción: Gestiona todas las consultas a la base de datos relacionadas 
 * con la tabla 'usuario' (Login, Registro, Consulta de saldos).
 */

class Usuario {
    
    // 1. PROPIEDAD: Aquí guardaremos la conexión a la base de datos
    private $db; 

    /**
     * 2. CONSTRUCTOR
     * Es un método mágico que se ejecuta automáticamente cuando hacemos "new Usuario($conexion)".
     * Lo usamos para inyectarle la conexión a la base de datos al modelo.
     */
    public function __construct($conexion) {
        // Le decimos al objeto: "Guarda esta conexión en TU propiedad interna"
        $this->db = $conexion; 
    }

    // ====================================================================
    // 3. MÉTODOS (Las antiguas funciones sueltas)
    // ====================================================================

    /**
     * Verifica las credenciales de un usuario.
     * Reemplaza a vuestro antiguo 'procesar_login.php'
     */
    public function login($email, $password) {
        $sql = "SELECT id_usuario, nombre, password, rol, activo FROM usuario WHERE email = ?";
        
        // Fijaos: En vez de mysqli_prepare($conexion, $sql), usamos el enfoque de objetos:
        $stmt = $this->db->prepare($sql); 
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($fila = $resultado->fetch_assoc()) {
            // Verificamos la contraseña encriptada (¡Vuestra misma lógica segura!)
            if (password_verify($password, $fila['password'])) {
                // Si es correcta, devolvemos un array con los datos básicos
                return [
                    'id' => $fila['id_usuario'],
                    'nombre' => $fila['nombre'],
                    'rol' => $fila['rol'],
                    'activo' => $fila['activo']
                ];
            }
        }
        return false; // Login fallido
    }

    /**
     * Obtiene los saldos y el rol de un usuario por su ID.
     * Reemplaza a vuestro antiguo 'get_user.php'
     */
 public function obtenerPorId($id_usuario) {
        // Usamos id_usuario que es el nombre real en tu tabla
        $sql = "SELECT id_usuario, nombre, saldo_disponible, saldo_bloqueado, es_artista, rol FROM usuario WHERE id_usuario = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($fila = $resultado->fetch_assoc()) {
            // Sincronizamos el nombre del campo para el Frontend
            $fila['id'] = $fila['id_usuario']; 
            return $fila;
        }
        return null;
    }

        /**
     * Añade fondos a la bóveda del mecenas.
     */
    public function actualizarFondos($id_usuario, $monto) {
        $sql = "UPDATE usuario SET saldo_disponible = saldo_disponible + ? WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("di", $monto, $id_usuario);
        return $stmt->execute();
    }

    /**
     * Registra un nuevo usuario en la plataforma.
     * Retorna false si el email ya existe.
     */
    public function registrarUsuario($nombre, $email, $password, $rol, $dni, $telefono) {
        // 1. Evitar correos duplicados
        $sql_check = "SELECT id_usuario FROM usuario WHERE email = ?";
        $stmt_check = $this->db->prepare($sql_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            return false; // El correo ya está registrado
        }

        // 2. Hash de contraseña y lógica de rol
        $pass_segura = password_hash($password, PASSWORD_DEFAULT);
        $es_artista = ($rol === 'artista') ? 1 : 0;

        // 3. Inserción
        $sql = "INSERT INTO usuario (nombre, email, password, rol, es_artista, dni, telefono) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssssiss", $nombre, $email, $pass_segura, $rol, $es_artista, $dni, $telefono);
        
        return $stmt->execute();
    }

/**
     * PANEL ADMIN: Obtiene la lista completa de usuarios.
     */
/**
     * PANEL ADMIN: Obtiene la lista completa de usuarios.
     */
    public function obtenerTodos() {
        // AÑADIDA LA COLUMNA 'activo' A LA CONSULTA SQL
        $sql = "SELECT id_usuario, nombre, email, rol, saldo_disponible, activo, fecha_registro FROM usuario ORDER BY fecha_registro DESC";
        $resultado = $this->db->query($sql);
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * PANEL ADMIN: Cambia el rol de un usuario.
     */
    public function actualizarRol($id_usuario, $nuevo_rol) {
        $sql = "UPDATE usuario SET rol = ? WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $nuevo_rol, $id_usuario);
        return $stmt->execute();
    }

    /**
     * Realiza un borrado lógico del usuario (Baneo/Desactivación).
     */
    public function borrarUsuario($id_usuario) {
        $sql = "UPDATE usuario SET activo = 0 WHERE id_usuario = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        
        return $stmt->execute();
    }

 /**
     * Cobra la licencia, cambia el rol y registra el movimiento.
     */
/**
     * Cobra la licencia, cambia el rol y registra el movimiento.
     * (Versión MySQLi)
     */
    public function pagarLicenciaArtista($id_usuario) {
        $coste = 19.99;

        try {
            // 1. Desactivamos el autocommit para iniciar la transacción en MySQLi
            $this->db->autocommit(FALSE);

            // 2. Verificamos si tiene saldo suficiente
            $sql_check = "SELECT saldo_disponible FROM usuario WHERE id_usuario = ?";
            $stmt_check = $this->db->prepare($sql_check);
            $stmt_check->bind_param("i", $id_usuario);
            $stmt_check->execute();
            $resultado = $stmt_check->get_result();
            $user = $resultado->fetch_assoc();

            if (!$user || $user['saldo_disponible'] < $coste) {
                $this->db->rollback(); // Cancelamos si no hay dinero
                $this->db->autocommit(TRUE); // Restauramos el comportamiento normal
                return "Capital insuficiente. Requiere 19,99 € en Saldo Disponible.";
            }

            // 3. Le cobramos y le damos los galones de artista
            $sql_update = "UPDATE usuario SET saldo_disponible = saldo_disponible - ?, rol = 'artista', es_artista = 1 WHERE id_usuario = ?";
            $stmt_update = $this->db->prepare($sql_update);
            $stmt_update->bind_param("di", $coste, $id_usuario);
            $stmt_update->execute();

            // 4. Registramos el pago en el log del sistema (ya que no existe tabla transaccion en tu SQL)
            $accion = 'PAGO_LICENCIA';
            $detalle = "Mecenas ID {$id_usuario} adquiere Licencia de Creador por {$coste}€";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $sql_log = "INSERT INTO log_sistema (id_usuario, accion, detalle, ip) VALUES (?, ?, ?, ?)";
            $stmt_log = $this->db->prepare($sql_log);
            $stmt_log->bind_param("isss", $id_usuario, $accion, $detalle, $ip);
            $stmt_log->execute();

            // 5. Confirmamos los cambios de la transacción
            $this->db->commit();
            $this->db->autocommit(TRUE); // Restauramos el comportamiento normal
            return true;

        } catch (Exception $e) {
            // Si cualquier consulta falla, deshacemos todo
            $this->db->rollback();
            $this->db->autocommit(TRUE);
            return "Error en la bóveda: " . $e->getMessage();
        }
    }

    /**
     * Levanta el castigo y vuelve a activar al usuario (Desbaneo).
     */
    public function amnistiarUsuario($id_usuario) {
        $sql = "UPDATE usuario SET activo = 1 WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        return $stmt->execute();
    }
}
?>