<?php
// escanear.php
// Permite buscar un producto por su código de barras a través de todas las tablas de categorías.
include 'includes/db.php'; // Archivo de conexión a la base de datos
include 'includes/header.php'; // Cabecera HTML

$mensaje = '';
$producto_encontrado = null;
$categoria_del_producto = '';

// --- Lógica para obtener todas las categorías dinámicamente ---
$categorias = [];
try {
    $stmt = $pdo->query("SELECT nombre_categoria FROM categorias ORDER BY nombre_categoria");
    $categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $mensaje = "<p class='btn-danger'>Error al cargar las categorías: " . $e->getMessage() . "</p>";
}

// --- Lógica de búsqueda al enviar el formulario ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['codigo_busqueda'])) {
    $codigo_busqueda = trim($_POST['codigo_busqueda']);

    if (empty($codigo_busqueda)) {
        $mensaje = "<p class='btn-warning'>Por favor, ingrese un código de barras para buscar.</p>";
    } else {
        // Asume que el código de barras puede tener o no los asteriscos iniciales/finales.
        // El trigger lo guarda con asteriscos, así que buscamos la versión con asteriscos.
        $codigo_con_asteriscos = "*" . str_replace(['*'], '', $codigo_busqueda) . "*";

        // Iterar sobre cada categoría (tabla) para buscar el producto
        foreach ($categorias as $cat) {
            // Asegurarse de que el nombre de la tabla sea seguro para evitar inyección SQL
            if (!preg_match('/^[a-z0-9_]+$/i', $cat)) {
                continue; // Saltar categorías con nombres no válidos
            }

            try {
                // Consulta preparada para buscar el CODIGO_BARRAS en la tabla actual
                $sql = "SELECT CODIGO, PRODUCTO, CANT, UNIDAD FROM `$cat` WHERE CODIGO_BARRAS = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo_con_asteriscos]);
                $producto_encontrado = $stmt->fetch(PDO::FETCH_ASSOC);

                // Si se encuentra el producto, guardar la categoría y detener la búsqueda
                if ($producto_encontrado) {
                    $categoria_del_producto = $cat;
                    break;
                }
            } catch (PDOException $e) {
                // Puedes registrar un error aquí si una tabla no existe, pero la búsqueda debe continuar.
                // $mensaje .= "<p class='text-muted'>Error al buscar en $cat: " . $e->getMessage() . "</p>";
                continue;
            }
        }

        if (!$producto_encontrado) {
            $mensaje = "<p class='btn-danger'>Producto con código de barras '$codigo_busqueda' no encontrado en ninguna categoría.</p>";
        }
    }
}
?>

<body>
    <div class="container main-content">
        <h2>Busqueda Código de Barras</h2><br>

        <!-- Formulario de búsqueda -->
        <form action="escanear.php" method="POST">
            <label for="codigo_busqueda" style="font-size: x-large;">Código de Barras:</label>
            <!-- El autofocus es útil para simular el escaneo directo con un lector de códigos -->
            <input type="text" id="codigo_busqueda" name="codigo_busqueda" style="margin-left: 10px;"
                value="<?php echo isset($codigo_busqueda) ? htmlspecialchars($codigo_busqueda) : ''; ?>" required
                autofocus>
            <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Buscar Producto</button>
        </form>

        <hr>
        <?php echo $mensaje; ?>

        <!-- Muestra los resultados si se encuentra un producto -->
        <?php if ($producto_encontrado): ?>
            <div class="card-resultado">
                <h3>Producto Encontrado</h3>
                <table border="1" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>CATEGORIA</th>
                            <th>CÓDIGO INTERNO</th>
                            <th>PRODUCTO</th>
                            <th>CANTIDAD</th>
                            <th>UNIDAD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center;">
                                <?php echo htmlspecialchars(ucfirst($categoria_del_producto)); ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($producto_encontrado['CODIGO']); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php echo htmlspecialchars($producto_encontrado['PRODUCTO']); ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($producto_encontrado['CANT']); ?>
                            </td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($producto_encontrado['UNIDAD']); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

</body>

</html>
<?php include 'includes/footer.php'; ?>