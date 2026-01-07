<?php
// header.php
// Incluye el inicio de la sesión si no ha sido iniciado ya
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener el nombre del archivo de la página actual
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRgcW54AkpoPfFPQacImJCIwpJEctdfJh4t0g&s"
        type="image/png">
    <title>Inventario NCS</title>
    <!-- Incluir Bootstrap CSS para un diseño limpio y responsive -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-image: url("https://lh3.googleusercontent.com/p/AF1QipPdNg6Gxx_ME31mCbA8JyoMMLrlZ3qSO6PTVRFA=s1360-w1360-h1020-rw");
            font-family: Arial, sans-serif;
            padding-top: 20px;
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .custom-navar {
            background-color: white;
            border-radius: 8px;
            padding: 1rem;
        }

        .nav-link {
            color: #495057 !important;
            font-weight: 500;
            transition: 0.3s;
        }

        .nav-link:hover {
            color: #007bff !important;
            text-decoration: underline;
        }

        .brand-title {
            font-weight: bold;
            color: black;
            text-decoration: none !important;
            font-size: x-large;
        }

        .btn-logout {
            background-color: #dc3545;
            color: white;
            border-radius: 5px;
            padding: 8px 15px;
            text-decoration: none;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background-color: #c82333;
            color: #fff;
        }

        .navbar-nav {
            padding-left: 20px;
            font-size: large;
        }
    </style>
</head>

<body>
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-light custom-navar">
            <a class="navbar-brand brand-title" href="index.php">Inventario NCS</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if (isset($_SESSION['user_id']) && !in_array($current_page, ['login.php', 'register.php', 'password_reset.php'])): ?>

                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="producto_categoria.php">Productos</a></li>
                            <li class="nav-item"><a class="nav-link" href="reportes.php">Reportes</a></li>
                            <li class="nav-item"><a class="nav-link" href="ver_solicitudes.php">Ver Solicitudes</a></li>
                            <li class="nav-item"><a class="nav-link" href="escanear.php">Buscar</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="categorias.php">Categorías</a></li>
                        <li class="nav-item"><a class="nav-link" href="mantenimiento.php">Mantenimiento</a></li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'user'): ?>
                            <li class="nav-item"><a class="nav-link" href="solicitudes.php">Solicitudes</a></li>
                        <?php endif; ?>
                    </ul>
                    <div class="form-inline">
                        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
    </div>
    <br>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>