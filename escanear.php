<?php
// escanear.php
//Permite buscar un producto por Coódigo de Barras y muestre su información

session_start();
require_once 'includes/db.php';
require_once 'includes/header.php';

$mensaje = '';
$producto_encontrado = null;
$filtro_codigo = '';
$user_role = $_SESSION['user_role'] ?? 'admin';

//Redirigir si el usuarion no es administrador
if ($user_role !== 'admin') {
    header("Location: login.php");
    exit();
}

//--- LÓGICA DE BÚSQUEDA ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['codigo'])) {
    $filtro_codigo = trim($_POST['codigo']);

    if (!empty($filtro_codigo)) {
        try {
            // Buscamos el producto utilizando su código. 
            // Usaremos la tabla 'inventario_movimientos' para encontrar la última o cualquier 
            // ocurrencia del producto que contenga su nombre y categoría.
            // NOTA: Si tiene una tabla de 'productos' dedicada, es mejor usarla. 
            // Aquí se usa movimientos por la estructura de su archivo anterior.

            // La consulta busca el movimiento MÁS RECIENTE para asegurar el nombre y categoría actuales
            $sql= "
                SELECT
                    codigo_producto,
                    nombre_producto,
                    categoria
                FROM inventario_movimientos
                WHERE codigo_producto = :codigo
                ORDER BY fecha_movimiento DESC
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':codigo' => $filtro_codigo]);
            $producto_encontrado = $stmt ->fetch(PDO::FETCH_ASSOC);

            if ($producto_encontrado) {
                $mensaje = "<p class='alert alert-success'>Producto Encontrado</p>"
            }
        }
    }
}

?>