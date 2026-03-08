<?php 
/**
 * Proyecto Intermodular: AUREUS
 * Capa: Presentación (Frontend)
 * Archivo: public/login.php
 * Descripción: 
 * Interfaz de autenticación para el ingreso a la Single Page Application (SPA).
 * Implementa redirección automática si existe una sesión activa y gestiona 
 * la visualización de notificaciones del controlador de acceso.
 */

session_start();

// Control de flujo: Redirección si el usuario ya está autenticado.
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
    <meta name="description" content="Acceso seguro a la plataforma de subastas exclusivas AUREUS.">
    <title>AUREUS | Autenticación de Usuarios</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    
    <style>
        body { 
            background-color: #0a0a0a; 
            color: #e0e0e0; 
            font-family: "Inter", sans-serif; 
        }
        .text-gold { 
            color: #D4AF37; 
            font-family: "Cinzel", serif; 
        }
        .bg-gold { 
            background-color: #D4AF37; 
            color: #000; 
        }
        .btn-gold { 
            background-color: #D4AF37; 
            color: #000; 
            font-weight: 600; 
            transition: all 0.3s ease; 
        }
        .btn-gold:hover { 
            background-color: #b5952f; 
            transform: translateY(-2px);
        }
        .form-control:focus {
            border-color: #D4AF37;
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark mb-4" style="border-bottom: 1px solid #333;">
        <div class="container justify-content-center justify-content-md-start">
            <a class="navbar-brand m-0" href="index.html" aria-label="Volver al inicio">
                <img src="./img/Aureus_logo.png" alt="Logotipo AUREUS" style="height: 80px; width: auto; object-fit: contain" />
            </a>
        </div>
    </nav>
    
    <main class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">
                <div class="card shadow-lg" style="background-color: #181818; border: 1px solid #333;">
                    <div class="card-header bg-gold text-center py-3">
                        <h1 class="h4 mb-0 text-dark" style="font-family: 'Cinzel', serif; font-weight: bold;">Acceso Restringido</h1>
                    </div>
                    
                    <div class="card-body p-4 p-md-5">
                        
                        <?php if(isset($_GET['registro']) && $_GET['registro'] === 'exito'): ?>
                            <div class="alert alert-success bg-dark text-success border-success small" role="alert">
                                Registro completado exitosamente. Por favor, introduzca sus credenciales.
                            </div>
                        <?php endif; ?>

                        <?php if(isset($_GET['error']) && $_GET['error'] === '1'): ?>
                            <div class="alert alert-danger bg-dark text-danger border-danger small" role="alert">
                                Error de autenticación: Credenciales no válidas.
                            </div>
                        <?php endif; ?>

                        <?php if(isset($_GET['msg']) && $_GET['msg'] === 'sesion_cerrada'): ?>
                            <div class="alert alert-info bg-dark text-info border-info small" role="alert">
                                La sesión ha finalizado de manera segura.
                            </div>
                        <?php endif; ?>

                        <?php if(isset($_GET['error']) && $_GET['error'] === 'baneado'): ?>
                            <div class="alert alert-warning bg-dark text-warning border-warning small" role="alert">
                                Cuenta inhabilitada. Contacte con la administración del sistema.
                            </div>
                        <?php endif; ?>

                        <form action="../index.php?accion=procesar_login" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label text-muted small mb-1">Identificador (Correo Electrónico)</label>
                                <input type="email" id="email" name="email" class="form-control bg-dark text-light border-secondary" required placeholder="usuario@dominio.com" autocomplete="email">
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label text-muted small mb-1">Clave de Acceso</label>
                                <input type="password" id="password" name="password" class="form-control bg-dark text-light border-secondary" required autocomplete="current-password">
                            </div>

                            <button type="submit" class="btn btn-gold w-100 py-2 mt-2">Iniciar Sesión</button>
                        </form>
                    </div>
                    
                    <div class="card-footer text-center py-4 border-top" style="border-color: #333 !important;">
                        <p class="mb-0 text-muted small">
                            ¿No dispone de credenciales? <a href="registro.php" class="text-gold text-decoration-none fw-bold">Solicitar afiliación</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>