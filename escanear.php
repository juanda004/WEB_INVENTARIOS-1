<?php
// escanear.php
// Permite buscar un producto por su código de barras en todas las tablas de categorías y subcategorías.

session_start();
include 'includes/db.php';

$mensaje = '';
$producto_encontrado = null;
$categoria_del_producto = '';

// --- 1. OBTENER TODAS LAS TABLAS DINÁMICAMENTE ---
$tablas_a_buscar = [];
try {
    // Obtener nombres de tablas de categorías principales
    $stmt_cat = $pdo->query("SELECT nombre_categoria FROM categorias ORDER BY nombre_categoria");
    while ($row = $stmt_cat->fetch(PDO::FETCH_COLUMN)) {
        $tablas_a_buscar[] = $row;
    }

    // Obtener nombres de tablas de subcategorías
    $stmt_sub = $pdo->query("SELECT nombre_tabla FROM subcategorias_logicas ORDER BY nombre_tabla");
    while ($row = $stmt_sub->fetch(PDO::FETCH_COLUMN)) {
        $tablas_a_buscar[] = $row;
    }

    // Eliminar duplicados y valores vacíos para mayor seguridad
    $tablas_a_buscar = array_unique(array_filter($tablas_a_buscar));

} catch (PDOException $e) {
    $mensaje = "<p class='alert alert-danger'>Error al cargar el mapa de categorías: " . $e->getMessage() . "</p>";
}

// --- 2. LÓGICA DE BÚSQUEDA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['codigo_busqueda'])) {
    $codigo_busqueda = trim($_POST['codigo_busqueda']);

    if (empty($codigo_busqueda)) {
        $mensaje = "<p class='alert alert-warning'>Por favor, ingrese un código de barras.</p>";
    } else {
        // Preparar el código con asteriscos (formato del sistema)
        $codigo_con_asteriscos = "*" . str_replace(['*'], '', $codigo_busqueda) . "*";

        // Realizar la búsqueda en la lista unificada de tablas
        foreach ($tablas_a_buscar as $tabla) {
            if (!preg_match('/^[a-z0-9_]+$/i', $tabla)) continue;

            try {
                $sql = "SELECT CODIGO, PRODUCTO, CANT, UNIDAD FROM `$tabla` WHERE CODIGO_BARRAS = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo_con_asteriscos]);
                $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($resultado) {
                    $producto_encontrado = $resultado;
                    $categoria_del_producto = $tabla;
                    break; // DETENER la búsqueda en cuanto se encuentre el producto
                }
            } catch (PDOException $e) {
                // Si una tabla no existe, simplemente saltamos a la siguiente
                continue;
            }
        }

        if (!$producto_encontrado) {
            $mensaje = "<p class='alert alert-danger'>Producto con código '$codigo_busqueda' no encontrado en ninguna categoría.</p>";
        }
    }
}

// --- 3. INICIO DE VISTA (HTML) ---
include 'includes/header.php'; 
?>

<div class="container main-content">
    <h2><i class="fas fa-barcode"></i> Búsqueda por Código de Barras</h2>
    <p class="text-muted">Escanee el código para localizar el producto en todo el inventario.</p>
    <hr>

    <form action="escanear.php" method="POST" class="form-inline mb-4">
        <div class="input-group" style="width: 100%; max-width: 500px;">
            <input type="text" id="codigo_busqueda" name="codigo_busqueda" 
                   class="form-control form-control-lg" 
                   placeholder="Escanear ahora..." autofocus 
                   value="<?php echo isset($codigo_busqueda) ? htmlspecialchars($codigo_busqueda) : ''; ?>" required>
            <div class="input-group-append">
                <button type="submit" class="btn btn-primary btn-lg">Buscar</button>
            </div>
        </div>
    </form>

    <?php echo $mensaje; ?>

    <?php if ($producto_encontrado): ?>
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">Producto Localizado</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr class="text-center">
                            <th>CATEGORÍA</th>
                            <th>CÓDIGO INTERNO</th>
                            <th>DESCRIPCIÓN</th>
                            <th>EXISTENCIA</th>
                            <th>UNIDAD</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-center">
                                <span class="badge badge-info p-2"><?php echo htmlspecialchars(ucfirst($categoria_del_producto)); ?></span>
                            </td>
                            <td class="text-center"><?php echo htmlspecialchars($producto_encontrado['CODIGO']); ?></td>
                            <td><?php echo htmlspecialchars($producto_encontrado['PRODUCTO']); ?></td>
                            <td class="text-center"><strong><?php echo htmlspecialchars($producto_encontrado['CANT']); ?></strong></td>
                            <td class="text-center"><?php echo htmlspecialchars($producto_encontrado['UNIDAD']); ?></td>
                            <td class="text-center">
                                <a href="editar_producto.php?categoria=<?php echo urlencode($categoria_del_producto); ?>&id=<?php echo urlencode($producto_encontrado['CODIGO']); ?>" 
                                   class="btn btn-sm btn-warning">Editar</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-4">
        <a class="btn btn-dark" href="categorias.php">Volver a Categorías</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>