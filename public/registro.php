<?php 
/**
 * Proyecto Intermodular: AUREUS
 * Capa: Presentación (Frontend)
 * Archivo: public/registro.php
 * Descripción: 
 * Interfaz de captación de usuarios. Recolecta la información personal 
 * y la transmite al controlador para la persistencia del perfil.
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
    <meta name="description" content="Formulario de registro para la plataforma AUREUS.">
    <title>AUREUS | Nueva Afiliación</title>
    
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

    <main class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card shadow-lg" style="background-color: #181818; border: 1px solid #333;">
                    <div class="card-header bg-dark text-center py-3" style="border-bottom: 2px solid #D4AF37;">
                        <h1 class="h4 mb-0 text-gold" style="font-family: 'Cinzel', serif;">Solicitud de Afiliación</h1>
                    </div>
                    
                    <div class="card-body p-4 p-md-5">

                        <?php if(isset($_GET['error']) && $_GET['error'] === 'duplicado'): ?>
                            <div class="alert alert-danger bg-dark text-danger border-danger small" role="alert">
                                Incoherencia de datos: El identificador (correo) ya se encuentra registrado en el sistema.
                            </div>
                        <?php endif; ?>

                        <form action="../index.php?accion=procesar_registro" method="POST">

                            <div class="mb-3">
                                <label for="nombre" class="form-label text-muted small mb-1">Nombre Legal o Pseudónimo Comercial</label>
                                <input type="text" id="nombre" name="nombre" class="form-control bg-dark text-light border-secondary" required autocomplete="name">
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label text-muted small mb-1">Correo Electrónico</label>
                                <input type="email" id="email" name="email" class="form-control bg-dark text-light border-secondary" required autocomplete="email">
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label text-muted small mb-1">Creación de Clave de Acceso</label>
                                <input type="password" id="password" name="password" class="form-control bg-dark text-light border-secondary" required autocomplete="new-password" minlength="8">
                                <div class="form-text text-secondary" style="font-size: 0.75rem;">Mínimo 8 caracteres requeridos por directiva de seguridad.</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="dni" class="form-label text-muted small mb-1">Documento Identificativo (DNI/NIF)</label>
                                    <input type="text" id="dni" name="dni" class="form-control bg-dark text-light border-secondary" required>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="telefono" class="form-label text-muted small mb-1">Teléfono de Contacto</label>
                                    <input type="tel" id="telefono" name="telefono" class="form-control bg-dark text-light border-secondary" required autocomplete="tel">
                                </div>
                            </div>                

                            <button type="submit" class="btn btn-gold w-100 py-2 mt-2">Transmitir Solicitud</button>
                        </form>
                    </div>
                    
                    <div class="card-footer text-center py-4 border-top" style="border-color: #333 !important;">
                        <p class="mb-0 text-muted small">
                            ¿Ya es miembro registrado? <a href="login.php" class="text-gold text-decoration-none fw-bold">Acceder al sistema</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>