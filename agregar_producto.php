<?php
// agregar_producto.php
// Este script agrega un nuevo producto a la categoría (tabla) seleccionada

session_start();
//Control de acceso: solo para daministradores
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location:index.php");
    exit();
}

include 'includes/db.php';
include 'includes/header.php';

$mensaje = '';
$categoria_seleccionada = isset($_GET['categoria']) ? $_GET['categoria'] : null;

// Bloque GET: Validar el nombre de la categoría
if (empty($categoria_seleccionada)) {
    $mensaje = "<p class='btn-danger'>❌ Error: No se ha especificado una categoría para agregar productos.</p>";
} else {
    // Asegurarse de que el nombre de la tabla sea seguro para evitar SQL Injection
    if (!preg_match('/^[a-z0-9_]+$/i', $categoria_seleccionada)) {
        $mensaje = "<p class='btn-danger'>❌ Error: Nombre de categoría no válido.</p>";
        $categoria_seleccionada = null; // Desactiva la funcionalidad
    }
}

// Bloque POST: Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $categoria_seleccionada) {
    // Recolectar datos del formulario
    $codigo = $_POST['CODIGO'];
    $producto = $_POST['PRODUCTO'];
    $cant = $_POST['CANT'];
    $unidad = $_POST['UNIDAD'];

    $codigo_barras = '*' . $codigo . '*';

    if (!empty($codigo) && !empty($codigo_barras) && !empty($producto) && !empty($cant) && !empty($unidad)) {
        // La consulta SQL ahora usa la variable $categoria_seleccionada
        try {
            $sql = "INSERT INTO `$categoria_seleccionada` (CODIGO, CODIGO_BARRAS, PRODUCTO, CANT, UNIDAD) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$codigo, $codigo_barras, $producto, $cant, $unidad])) {
                $mensaje = "<p class='btn-success'>✅ Producto agregado a la categoría '" . htmlspecialchars($categoria_seleccionada) . "' correctamente.</p>";
            } else {
                $mensaje = "<p class='btn-danger'>❌ Error al agregar el producto.</p>";
            }
        } catch (PDOException $e) {
            $mensaje = "<p class='btn-danger'>❌ Error de base de datos: " . $e->getMessage() . "</p>";
        }
    } else {
        $mensaje = "<p class='btn-warning'>⚠️ Por favor, complete todos los campos.</p>";
    }
}
?>

<?php echo $mensaje; ?>

<?php if ($categoria_seleccionada): ?>
    <h2>Agregando Producto a la Categoría: "<?php echo htmlspecialchars(ucfirst($categoria_seleccionada)); ?>"</h2>
    <form action="agregar_producto.php?categoria=<?php echo htmlspecialchars($categoria_seleccionada); ?>" method="POST">
        <div class="form-group">
            <label for="codigo">Código:</label>
            <input type="text" id="codigo" name="CODIGO" required>
        </div>
        <div class="form-group">
            <label for="producto">Nombre del Producto:</label>
            <input type="text" id="producto" name="PRODUCTO" required>
        </div>
        <div class="form-group">
            <label for="cant">Cantidad:</label>
            <input type="number" id="cant" name="CANT" step="1" required>
        </div>
        <div class="form-group">
            <label for="unidad">Unidad:</label>
            <input type="text" id="unidad" name="UNIDAD" required>
        </div>
        <button type="submit" class="btn btn-primary">Agregar Producto</button>
        <a href="categorias.php" class="btn btn-success">Volver a Categorías</a>
        <a href="ver_productos.php?categoria=<?php echo htmlspecialchars($categoria_seleccionada); ?>" method="POST" class="btn btn-dark">Ver Categoría</a>
    </form>
<?php else: ?>
    <p>Por favor, seleccione una categoría desde la página <a href="categorias.php">Gestor de Categorías</a>.</p>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>