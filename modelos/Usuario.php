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
        $sql = "SELECT nombre, saldo_disponible, saldo_bloqueado, es_artista, rol FROM usuario WHERE id_usuario = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($fila = $resultado->fetch_assoc()) {
            return $fila; // Devuelve el registro de la BD
        }
        return null; // Si no lo encuentra
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
     * Realiza un borrado lógico del usuario (Baneo/Desactivación).
     */
    public function borrarUsuario($id_usuario) {
        $sql = "UPDATE usuario SET activo = 0 WHERE id_usuario = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        
        return $stmt->execute();
    }
}
?>