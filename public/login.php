<?php 
/**
 * AUREUS - Vista de Inicio de Sesión
 * Ubicación: public/login.php
 * Descripción: Puerta de acceso para Mecenas y Artistas.
 */
session_start();

// Si el usuario ya tiene sesión, lo redirigimos a la SPA
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
    <title>AUREUS | Acceso de Mecenas</title>
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
        <a class="navbar-brand" href="index.html" style="cursor: pointer;">
        <img src="./img/Aureus_logo.png" alt="Logo AUREUS" style="height: 100px; width: auto; object-fit: contain" />
        </a>
          <img
            src="./img/Aureus_logo.png"
            alt="Logo AUREUS"
            style="height: 100px; width: auto; object-fit: contain"
          />
        </a>
        </div>
    </nav>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-lg" style="background-color: #181818; border: 1px solid #333;">
                    <div class="card-header bg-gold text-center py-3">
                        <h3 class="mb-0 text-dark" style="font-family: 'Cinzel', serif;">Acceso de Mecenas</h3>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if(isset($_GET['registro']) && $_GET['registro'] == 'exito'): ?>
                            <div class="alert alert-success bg-dark text-success border-success">
                                ¡Cuenta forjada con éxito! Por favor, identifícate.
                            </div>
                        <?php endif; ?>

                        <?php if(isset($_GET['error']) && $_GET['error'] == '1'): ?>
                            <div class="alert alert-danger bg-dark text-danger border-danger">
                                Credenciales incorrectas o mecenas inexistente.
                            </div>
                        <?php endif; ?>

                        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'sesion_cerrada'): ?>
                            <div class="alert alert-info bg-dark text-info border-info">
                                Has abandonado la cámara de forma segura.
                            </div>
                        <?php endif; ?>

                        <?php if(isset($_GET['error']) && $_GET['error'] == 'baneado'): ?>
                            <div class="alert alert-danger bg-dark text-danger border-danger">
                                Su cuenta ha sido desactivada. Por favor, contacte con la Mesa del Senado para más información.
                            </div>
                        <?php endif; ?>

                        <form action="../index.php?accion=procesar_login" method="POST">
                            <div class="mb-3">
                                <label class="form-label text-muted">Correo Electrónico</label>
                                <input type="email" name="email" class="form-control bg-dark text-light border-secondary" required placeholder="senador@roma.com">
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label text-muted">Contraseña</label>
                                <input type="password" name="password" class="form-control bg-dark text-light border-secondary" required>
                            </div>

                            <button type="submit" class="btn btn-gold w-100 py-2">Ingresar a la Bóveda</button>
                        </form>
                    </div>
                    <div class="card-footer text-center py-3 border-0">
                        <p class="mb-0 text-muted">¿Aún no eres miembro? <a href="registro.php" class="text-gold text-decoration-none">Regístrate aquí</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>