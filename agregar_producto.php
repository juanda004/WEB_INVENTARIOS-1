<?php
// agregar_producto.php
// Este script agrega un nuevo producto a la tabla (Categoría o Subcategoría) seleccionada por URL.

session_start();
//Control de acceso: solo para administradores
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location:index.php");
    exit();
}

include 'includes/db.php';
include 'includes/header.php';

$mensaje = '';
// El nombre de la tabla (Categoría o Subcategoría) se recibe por URL
$tabla_seleccionada = isset($_GET['categoria']) ? $_GET['categoria'] : null;

// Bloque GET: Validar el nombre de la tabla
if (empty($tabla_seleccionada)) {
    $mensaje = "<p class='btn-danger'>❌ Error: No se ha especificado una categoría o subcategoría para agregar productos.</p>";
} else {
    // Asegurarse de que el nombre de la tabla sea seguro para evitar SQL Injection
    // Solo permitir letras, números y guion bajo
    if (!preg_match('/^[a-zA-Z0-9_]+$/i', $tabla_seleccionada)) {
        $mensaje = "<p class='btn-danger'>❌ Error: Nombre de Categoría/Subcategoría no válido.</p>";
        $tabla_seleccionada = null; // Desactiva la funcionalidad
    }
}

// Bloque POST: Procesar el formulario e insertar en la tabla dinámica
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $tabla_seleccionada) {
    // Recolectar y sanitizar datos del formulario
    $codigo = trim($_POST['CODIGO']);
    $producto = trim($_POST['PRODUCTO']);
    $cant = (int)$_POST['CANT'];
    $unidad = trim($_POST['UNIDAD']);

    $codigo_barras = '*' . $codigo . '*';

    // Validación adicional de campos
    if (empty($codigo) || empty($producto) || $cant <= 0 || empty($unidad)) {
        $mensaje = "<p class='btn-warning'>⚠️ Por favor, complete todos los campos y asegúrese que la Cantidad es un número positivo.</p>";
    } else {
        try {
            // La consulta de inserción usa la tabla dinámica (Categoría o Subcategoría)
            $sql = "INSERT INTO `$tabla_seleccionada` (CODIGO, CODIGO_BARRAS, PRODUCTO, CANT, UNIDAD) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$codigo, $codigo_barras, $producto, $cant, $unidad])) {
                $mensaje = "<p class='btn-success'>✅ Producto agregado a la tabla '" . htmlspecialchars(ucfirst($tabla_seleccionada)) . "' correctamente.</p>";
                
                // Opcional: Limpiar los campos después de la inserción exitosa
                $_POST = []; 
            } else {
                $mensaje = "<p class='btn-danger'>❌ Error al agregar el producto.</p>";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                 $mensaje = "<p class='btn-danger'>❌ Error: El código de producto '<b>" . htmlspecialchars($codigo) . "</b>' ya existe en esta tabla. Los códigos deben ser únicos.</p>";
            } else {
                 $mensaje = "<p class='btn-danger'>❌ Error de base de datos: " . $e->getMessage() . "</p>";
            }
        }
    }
}
?>

<?php echo $mensaje; ?>

<?php if ($tabla_seleccionada): ?>
    <h2>Agregando Producto a: "<?php echo htmlspecialchars(ucfirst($tabla_seleccionada)); ?>"</h2>
    <form action="agregar_producto.php?categoria=<?php echo htmlspecialchars($tabla_seleccionada); ?>" method="POST">
        
        <div class="form-group">
            <label for="codigo">Código:</label>
            <input type="text" id="codigo" name="CODIGO" value="<?php echo htmlspecialchars($_POST['CODIGO'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="producto">Nombre del Producto:</label>
            <input type="text" id="producto" name="PRODUCTO" value="<?php echo htmlspecialchars($_POST['PRODUCTO'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="cant">Cantidad:</label>
            <input type="number" id="cant" name="CANT" step="1" value="<?php echo htmlspecialchars($_POST['CANT'] ?? '1'); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="unidad">Unidad:</label>
            <select id="unidad" name="UNIDAD" required>
                <?php 
                    $unidades = ['UNIDAD', 'CAJA', 'EMPAQUE', 'PACA', 'PAR', 'FRASCO', 'LITRO', 'METRO', 'ROLLO'];
                    $selected_unidad = $_POST['UNIDAD'] ?? 'UNIDAD';
                    foreach ($unidades as $un): 
                ?>
                    <option value="<?php echo $un; ?>" <?php echo ($selected_unidad === $un) ? 'selected' : ''; ?>>
                        <?php echo $un; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">Agregar Producto</button>
        <a href="categorias.php" class="btn btn-success">Volver a Categorías</a>
        <a href="ver_productos.php?categoria=<?php echo htmlspecialchars($tabla_seleccionada); ?>" class="btn btn-dark">Ver Inventario de <?php echo ucfirst($tabla_seleccionada); ?></a>
    </form>
<?php else: ?>
    <p>Por favor, seleccione una Categoría o Subcategoría desde la página <a href="categorias.php">Gestor de Categorías</a>.</p>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>