<?php 
/**
 * AUREUS - Vista de Registro de Usuarios
 * Ubicación: public/registro.php
 * Descripción: Formulario de captación de nuevos clientes.
 */
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AUREUS | Nueva Afiliación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #0a0a0a; color: #e0e0e0; font-family: "Inter", sans-serif; }
        .text-gold { color: #D4AF37; font-family: "Cinzel", serif; }
        .bg-gold { background-color: #D4AF37; color: #000; }
        .btn-gold { background-color: #D4AF37; color: #000; font-weight: bold; transition: 0.3s; }
        .btn-gold:hover { background-color: #b5952f; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark mb-4" style="border-bottom: 1px solid #333;">
        <div class="container">
                    <a class="navbar-brand" href="#">
          <img
            src="./img/Aureus_logo.png"
            alt="Logo AUREUS"
            style="height: 100px; width: auto; object-fit: contain"
          />
        </a>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg" style="background-color: #181818; border: 1px solid #333;">
                    <div class="card-header bg-dark text-center py-3" style="border-bottom: 1px solid #D4AF37;">
                        <h3 class="mb-0 text-gold" style="font-family: 'Cinzel', serif;">Nueva Afiliación</h3>
                    </div>
                    <div class="card-body p-4">

                        <?php if(isset($_GET['error']) && $_GET['error'] == 'duplicado'): ?>
                            <div class="alert alert-danger bg-dark text-danger border-danger">
                                El correo introducido ya pertenece a un miembro de la plataforma.
                            </div>
                        <?php endif; ?>

                        <form action="../index.php?accion=procesar_registro" method="POST">

                            <div class="mb-3">
                                <label class="form-label text-muted">Nombre Completo (o Pseudónimo)</label>
                                <input type="text" name="nombre" class="form-control bg-dark text-light border-secondary" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted">Correo Electrónico</label>
                                <input type="email" name="email" class="form-control bg-dark text-light border-secondary" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted">Contraseña Segura</label>
                                <input type="password" name="password" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">DNI / Pasaporte</label>
                                    <input type="text" name="dni" class="form-control bg-dark text-light border-secondary" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">Teléfono</label>
                                    <input type="tel" name="telefono" class="form-control bg-dark text-light border-secondary" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label text-muted">¿Cuál es su propósito en Aureus?</label>
                                <select name="rol" class="form-select bg-dark text-light border-secondary">
                                    <option value="comprador">Exclusivamente Coleccionista (Comprar)</option>
                                    <option value="artista">Creador de Arte (Vender y Comprar)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-gold w-100 py-2">Solicitar Ingreso</button>
                        </form>
                    </div>
                    <div class="card-footer text-center py-3 border-0">
                        <p class="mb-0 text-muted">¿Ya eres miembro? <a href="login.php" class="text-gold text-decoration-none">Inicia Sesión</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>