<?php
// editar_producto.php
// Este script permite editar un producto existente en la base de datos.
require_once 'includes/db.php';
require_once 'includes/header.php';

$mensaje = '';
$producto = null;
$categoria = '';
$codigo = '';

// Verificar si se reciben los parámetros necesarios para la edición
if (isset($_GET['id']) && isset($_GET['categoria'])) {
    $codigo = trim($_GET['id']);
    $categoria = trim($_GET['categoria']);

    // Validar el nombre de la categoría para prevenir inyección SQL
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $categoria)) {
        die("Nombre de categoría inválido.");
    }

    try {
        // Consultar el producto a editar
        $sql = "SELECT * FROM `$categoria` WHERE CODIGO = :codigo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':codigo' => $codigo]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            $mensaje = "<p class='btn-danger'>Producto no encontrado.</p>";
        }
    } catch (PDOException $e) {
        $mensaje = "<p class='btn-danger'>Error al cargar el producto: " . $e->getMessage() . "</p>";
    }
} else {
    $mensaje = "<p class='btn-danger'>No se especificó un producto o categoría para editar.</p>";
}

// Procesar la actualización del producto si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_producto'])) {
    $codigo_antiguo = trim($_POST['codigo_antiguo']);
    $categoria_actual = trim($_POST['categoria_actual']);
    $nuevo_codigo = trim($_POST['nuevo_codigo']);
    $descripcion = trim($_POST['descripcion']);
    $cantidad = (int)$_POST['cantidad'];
    $unidad = trim($_POST['unidad']);

    $codigo_barras = '*' . $nuevo_codigo . '*';

    if (empty($nuevo_codigo) || empty($descripcion) || empty($cantidad)) {
        $mensaje = "<p class='btn-danger'>Todos los campos son obligatorios.</p>";
    } else {
        try {
            // Actualizar la información del producto
            $sql = "UPDATE `$categoria_actual` SET CODIGO = ?, CODIGO_BARRAS = ?, PRODUCTO = ?, CANT = ?, UNIDAD = ? WHERE CODIGO = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nuevo_codigo, $codigo_barras, $descripcion, $cantidad, $unidad, $codigo_antiguo]);

            // Redirigir a la página de la categoría después de una actualización exitosa
            header("Location: producto_categoria.php?categoria=" . urlencode($categoria_actual));
            exit();

        } catch (PDOException $e) {
            $mensaje = "<p class='btn-danger'>Error al actualizar el producto: " . $e->getMessage() . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
</head>
<body>
    <h2>Editar Producto</h2>
    <p><a class="btn btn-dark" href="ver_productos.php?categoria=<?php echo urlencode($categoria); ?>">Regresar a Productos</a></p>
    
    <?php echo $mensaje; ?>

    <?php if ($producto): ?>
        <form action="editar_producto.php?id=<?php echo urlencode($producto['CODIGO']); ?>&categoria=<?php echo urlencode($categoria); ?>" method="POST">
            <input type="hidden" name="codigo_antiguo" value="<?php echo htmlspecialchars($producto['CODIGO']); ?>">
            <input type="hidden" name="categoria_actual" value="<?php echo htmlspecialchars($categoria); ?>">

            <label for="nuevo_codigo">Código del Producto:</label>
            <input type="text" id="nuevo_codigo" name="nuevo_codigo" value="<?php echo htmlspecialchars($producto['CODIGO']); ?>" required>
            <br><br>

            <label for="descripcion">Descripción del Producto:</label>
            <input type="text" id="descripcion" name="descripcion" value="<?php echo htmlspecialchars($producto['PRODUCTO']); ?>" required>
            <br><br>

            <label for="cantidad">Cantidad:</label>
            <input type="number" id="cantidad" name="cantidad" value="<?php echo htmlspecialchars($producto['CANT']); ?>" required>
            <br><br>

            <label for="unidad">Unidad:</label>
            <select id="unidad" name="unidad" required>
                <?php 
                    $unidades = ['UNIDAD', 'CAJA', 'EMPAQUE', 'PACA', 'PAR', 'FRASCO'];
                    foreach ($unidades as $un): 
                ?>
                    <option value="<?php echo $un; ?>" <?php echo ($producto['UNIDAD'] === $un) ? 'selected' : ''; ?>><?php echo $un; ?></option>
                <?php endforeach; ?>
            </select>
            <br><br>
            <button type="submit" name="editar_producto" class="btn btn-success">Actualizar Producto</button>
        </form>
    <?php endif; ?>

</body>
</html>

<?php require_once 'includes/footer.php'; ?>
