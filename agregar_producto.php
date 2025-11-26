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
$user_id = $_SESSION['user_id'] ?? 0; // Se asume que el ID del usuario está aquí

// --- Función Auxiliar necesaria para el Kárdex (Registro de Movimientos) ---
// Esta función NO ha sido modificada, tal como lo solicitaste.
function registrarMovimiento(PDO $pdo, $codigo, $nombre, $categoria, $cantidad, $tipo, $ref_id, $user_id, $comentarios = '') {
    $stmt = $pdo->prepare("
        INSERT INTO inventario_movimientos
        (fecha_movimiento, codigo_producto, nombre_producto, categoria, cantidad_afectada, tipo_movimiento, referencia_id, usuario_id, comentarios)
        VALUES (NOW(), :codigo, :nombre, :categoria, :cantidad, :tipo, :ref_id, :user_id, :comentarios)
    ");
    $stmt->execute([
        ':codigo' => $codigo,
        ':nombre' => $nombre,
        ':categoria' => $categoria,
        ':cantidad' => $cantidad,
        ':tipo' => $tipo,
        ':ref_id' => $ref_id,
        ':user_id' => $user_id,
        ':comentarios' => $comentarios
    ]);
}

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
    // Recolectar datos del formulario
    $codigo = trim($_POST['CODIGO']);
    $producto = trim($_POST['PRODUCTO']);
    $cant = (int)$_POST['CANT'];
    $unidad = trim($_POST['UNIDAD']);

    // Validar datos mínimos
    if (empty($codigo) || empty($producto) || $cant <= 0) {
        $mensaje = "<p class='btn-danger'>❌ Error: Todos los campos son obligatorios y la cantidad debe ser positiva.</p>";
    } else {
        // ✅ CORRECCIÓN 1: Generar el CODIGO_BARRAS a partir del CODIGO
        $codigo_barras = '*' . $codigo . '*'; 
        
        try {
            // 1. Verificar si el código ya existe para evitar duplicados
            $check_sql = "SELECT COUNT(*) FROM `$tabla_seleccionada` WHERE CODIGO = :codigo";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':codigo' => $codigo]);
            
            if ($check_stmt->fetchColumn() > 0) {
                $mensaje = "<p class='btn-warning'>⚠️ Advertencia: Ya existe un producto con el código '{$codigo}'.</p>";
            } else {
                // 2. Insertar el nuevo producto con CODIGO_BARRAS
                // ✅ CORRECCIÓN 2: Incluir CODIGO_BARRAS en la consulta SQL
                $sql = "INSERT INTO `$tabla_seleccionada` (CODIGO, CODIGO_BARRAS, PRODUCTO, CANT, UNIDAD) 
                        VALUES (:codigo, :codigo_barras, :producto, :cant, :unidad)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':codigo' => $codigo,
                    ':codigo_barras' => $codigo_barras, // <-- CORRECCIÓN 3: Bind del parámetro
                    ':producto' => $producto,
                    ':cant' => $cant,
                    ':unidad' => $unidad
                ]);

                // 3. Registrar el movimiento de inventario (Kárdex)
                registrarMovimiento($pdo, $codigo, $producto, $tabla_seleccionada, $cant, 'INGRESO NUEVO', 0, $user_id, "Registro inicial del producto.");

                $mensaje = "<p class='btn-success'>✅ Producto '{$producto}' agregado exitosamente a " . ucfirst($tabla_seleccionada) . ".</p>";
                
                // Limpiar POST para evitar reenvío al recargar
                $_POST = [];
            }

        } catch (PDOException $e) {
            // Error de base de datos
            $mensaje = "<p class='btn-danger'>❌ Error al agregar el producto: " . $e->getMessage() . "</p>";
        }
    }
}
?>

<div class="container">
    <h2>Agregar Producto</h2>
    <?php echo $mensaje; ?>

    <?php if ($tabla_seleccionada): ?>
        <h2>Agregando Producto a la Categoría: "<?php echo htmlspecialchars(ucfirst($tabla_seleccionada)); ?>"</h2>
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
                <input type="number" id="cant" name="CANT" step="1" value="<?php echo htmlspecialchars($_POST['CANT'] ?? '1'); ?>" min="1" required>
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
        <p>Por favor, regrese a la lista de categorías para seleccionar dónde agregar el producto.</p>
        <a href="categorias.php" class="btn btn-dark">Volver a Categorías</a>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>