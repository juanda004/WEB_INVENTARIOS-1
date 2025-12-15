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
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding-top: 20px;
        }

        .container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px 0;
            flex-wrap: wrap;
        }

        .btn-logout {
            background-color: #dc3545;
            color: #fff;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <div class="header container">
        <div style="display: flexbox;">
            <a href="login.php"><h1 style="font-weight: bold; color: black;">Inventario NCS</h1></a>
        </div>

        <?php
        // Solo muestra el botón de cerrar sesión si el usuario está logueado
        // Y no está en la página de login
        if (isset($_SESSION['user_id']) && $current_page !== 'login.php' && $current_page !== 'register.php' && $current_page !== 'password_reset.php') {
            echo '
            <nav>
                <ul class= "nav nav-pills" style="margin-bottom: 1rem;";>
                    <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>';
                
                if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                    
                    echo '<li class="nav-item"><a class="nav-link" href="producto_categoria.php">Productos</a></li>';
                    echo '<li class="nav-item"><a class="nav-link" href="reportes.php">Reportes</a></li>';
                    echo '<li class="nav-item"><a class="nav-link" href="ver_solicitudes.php">Ver Solicitudes</a></li>';
                    echo '<li class="nav-item"><a class="nav-link" href="escanear.php">Buscar</a></li>';
                }

                //Opciones para todos los usuarios
                echo '<li class="nav-item"><a class="nav-link" href="categorias.php">Categorías</a></li>';
                
                if (isset($_SESSION['role']) && $_SESSION['role']=== 'user'){
                    echo '<li class="nav-item"><a class="nav-link" href="solicitudes.php">Solicitudes</a></li>';
                }

                echo '</ul></nav>';
                echo '<a href="logout.php" class="btn-logout" style="text-align: center;">Cerrar Sesión</a>';
        }
        ?>
    </div>
    <br>
    <div class="container main-content">
        <!-- El contenido de la página se insertará aquí -->