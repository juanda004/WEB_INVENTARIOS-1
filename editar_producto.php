<?php
// editar_producto.php
// Este script permite editar un producto existente en cualquier tabla de categoría o subcategoría.

require_once 'includes/db.php';
require_once 'includes/header.php';

$mensaje = '';
$producto = null;
$tabla_seleccionada = ''; // Nombre de la tabla (Categoría o Subcategoría)
$codigo_original = '';    // El CODIGO del producto que se está editando


// Control de acceso: solo para administradores
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location:index.php");
    exit();
}

// --- Bloque GET: Cargar datos iniciales del producto ---
if (isset($_GET['id']) && isset($_GET['categoria'])) {
    $codigo_original = trim($_GET['id']);
    $tabla_seleccionada = trim($_GET['categoria']);

    // 1. Validar el nombre de la tabla para prevenir inyección SQL
    if (!preg_match('/^[a-zA-Z0-9_]+$/i', $tabla_seleccionada)) {
        die("<p class='btn-danger'>Nombre de Categoría/Subcategoría inválido.</p>");
    }

    try {
        // 2. Consultar el producto a editar de la tabla dinámica
        $sql = "SELECT * FROM `$tabla_seleccionada` WHERE CODIGO = :codigo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':codigo' => $codigo_original]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            $mensaje = "<p class='btn-danger'>Producto no encontrado en la tabla '" . htmlspecialchars(ucfirst($tabla_seleccionada)) . "'.</p>";
        }
    } catch (PDOException $e) {
        $mensaje = "<p class='btn-danger'>Error al cargar el producto: " . $e->getMessage() . "</p>";
    }
} else {
    $mensaje = "<p class='btn-danger'>No se especificó un producto o categoría para editar.</p>";
}


// --- Bloque POST: Procesar la actualización del producto ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_producto']) && $producto) {
    // Valores originales y contexto
    $tabla_actual = trim($_POST['categoria_actual']);
    $codigo_original_post = trim($_POST['codigo_original']);
    
    // Nuevos valores
    $nuevo_codigo = trim($_POST['nuevo_codigo']);
    $descripcion = trim($_POST['descripcion']);
    $cantidad = (int)$_POST['cantidad'];
    $unidad = trim($_POST['unidad']);

    $codigo_barras = '*' . $nuevo_codigo . '*';

    if (empty($nuevo_codigo) || empty($descripcion) || $cantidad <= 0 || empty($unidad)) {
        $mensaje = "<p class='btn-danger'>❌ Todos los campos son obligatorios o tienen valores inválidos.</p>";
    } else {
        try {
            // Actualizar la información del producto en la tabla dinámica
            // Se usa el código original para identificar el registro a cambiar
            $sql = "UPDATE `$tabla_actual` SET CODIGO = ?, CODIGO_BARRAS = ?, PRODUCTO = ?, CANT = ?, UNIDAD = ? WHERE CODIGO = ?";
            $stmt = $pdo->prepare($sql);
            
            // Los parámetros son: Nuevo Código, Nuevo Código Barras, Nuevo Producto, Nueva Cantidad, Nueva Unidad, Código Original (WHERE)
            $stmt->execute([$nuevo_codigo, $codigo_barras, $descripcion, $cantidad, $unidad, $codigo_original_post]);

            // Redirigir a la página de productos de la categoría después de una actualización exitosa
            // Nota: Se usa el nombre de la tabla actual para volver
            header("Location: ver_productos.php?categoria=" . urlencode($tabla_actual));
            exit();

        } catch (PDOException $e) {
             if ($e->getCode() == 23000) {
                 $mensaje = "<p class='btn-danger'>❌ Error: El código de producto '<b>" . htmlspecialchars($nuevo_codigo) . "</b>' ya existe en esta tabla.</p>";
            } else {
                $mensaje = "<p class='btn-danger'>❌ Error al actualizar el producto: " . $e->getMessage() . "</p>";
            }
        }
    }
}
?>

<div class="container mt-4">
    <h2>Editando Producto en: <?php echo htmlspecialchars(ucfirst($tabla_seleccionada)); ?></h2>
    <p><a class="btn btn-dark" href="ver_productos.php?categoria=<?php echo urlencode($tabla_seleccionada); ?>">Regresar a Productos</a></p>
    
    <?php echo $mensaje; ?>

    <?php if ($producto): ?>
        <form action="editar_producto.php?categoria=<?php echo urlencode($tabla_seleccionada); ?>&id=<?php echo urlencode($codigo_original); ?>" method="POST">
            <input type="hidden" name="categoria_actual" value="<?php echo htmlspecialchars($tabla_seleccionada); ?>">
            <input type="hidden" name="codigo_original" value="<?php echo htmlspecialchars($codigo_original); ?>">
            
            <div class="form-group">
                <label for="nuevo_codigo">Código del Producto:</label>
                <input type="text" id="nuevo_codigo" name="nuevo_codigo" value="<?php echo htmlspecialchars($producto['CODIGO']); ?>" required>
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción del Producto:</label>
                <input type="text" id="descripcion" name="descripcion" value="<?php echo htmlspecialchars($producto['PRODUCTO']); ?>" required>
            </div>

            <div class="form-group">
                <label for="cantidad">Cantidad:</label>
                <input type="number" id="cantidad" name="cantidad" value="<?php echo htmlspecialchars($producto['CANT']); ?>" required>
            </div>

            <div class="form-group">
                <label for="unidad">Unidad:</label>
                <select id="unidad" name="unidad" required>
                    <?php 
                        $unidades = ['UNIDAD', 'CAJA', 'EMPAQUE', 'PACA', 'PAR', 'FRASCO', 'LITRO', 'METRO', 'ROLLO'];
                        $unidad_actual = $producto['UNIDAD'];
                        foreach ($unidades as $un): 
                    ?>
                        <option value="<?php echo $un; ?>" <?php echo ($unidad_actual === $un) ? 'selected' : ''; ?>>
                            <?php echo $un; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" name="editar_producto" class="btn btn-success">Actualizar Producto</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>