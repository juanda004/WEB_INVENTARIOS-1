<?php
// buscar_producto.php
// Este archivo maneja la búsqueda de productos en todas las categorías, usando solo el código.
require_once 'includes/db.php';
require_once 'includes/header.php';

$resultados = [];
$mensaje = '';
$termino_busqueda = '';

// Procesar el formulario de búsqueda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar_producto'])) {
    $termino_busqueda = trim($_POST['termino_busqueda']);

    $termino_lik = '%' . $termino_busqueda . '%';

    if (empty($termino_busqueda)) {
        $mensaje = "<p class='btn-danger'>Por favor, ingrese un código de producto.</p>";
    } else {
        try {
            // Obtener todas las categorías para buscar en ellas
            $stmt = $pdo->query("SELECT nombre_categoria FROM categorias ORDER BY nombre_categoria");
            $categorias_para_buscar = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            $mensaje = "<p class='btn-danger'>Error al cargar las categorías para la búsqueda: " . $e->getMessage() . "</p>";
            $categorias_para_buscar = [];
        }

        // Recorrer cada categoría y buscar el producto
        foreach ($categorias_para_buscar as $cat) {
            //Aegurarse de que el nombre de la tabla sea seguro
            if (!preg_match('/^[a-z0-9_]+$/i', $cat)) {
                continue; // Saltar tablas con nombres no seguros
            }

            try {
                // Prepara la consulta para evitar inyección SQL
                // La búsqueda ahora solo se realiza por el campo CODIGO,
                // usando REPLACE para eliminar guiones y asteriscos, si existen.
                $termino_sin_caracteres_especiales = str_replace(['-', '*'], '', $termino_busqueda);

                $sql = "SELECT CODIGO, CODIGO_BARRAS, PRODUCTO, CANT, UNIDAD, :categoria AS categoria_origen FROM `$cat` WHERE REPLACE(CODIGO, '-', '') = :termino";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':termino', $termino_sin_caracteres_especiales);
                $stmt->bindValue(':categoria', $cat);
                $stmt->execute();
                // Combina los resultados de cada tabla en un solo array
                $resultados = array_merge($resultados, $stmt->fetchAll(PDO::FETCH_ASSOC));
            } catch (PDOException $e) {
                // Si la tabla no existe o hay un error, lo ignoramos y continuamos
            }
        }

        if (empty($resultados)) {
            $mensaje = "<p class='btn-info'>No se encontraron productos con el código '$termino_busqueda' en ninguna categoría.</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Buscar Producto</title>
</head>
<body>
    <h2>Buscar Producto</h2>
    <p><a href="index.php">Regresar al Panel Principal</a></p>
    <?php echo $mensaje; ?>

    <!-- Formulario de búsqueda universal de producto -->
    <h3>Buscador Universal</h3>
    <form action="buscar_producto.php" method="POST">
        <label for="termino_busqueda">Código de Producto:</label>
        <input type="text" id="termino_busqueda" name="termino_busqueda" required placeholder="Ingrese el código del producto">
        <button type="submit" name="buscar_producto">Buscar</button>
    </form>
    <hr>

    <?php if (!empty($resultados)): ?>
        <h3>Resultados de la Búsqueda</h3>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>CATEGORÍA</th>
                    <th>CÓDIGO</th>
                    <th>CÓDIGO DE BARRAS</th>
                    <th>PRODUCTO</th>
                    <th>CANTIDAD</th>
                    <th>UNIDAD</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados as $producto): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($producto['categoria_origen']); ?></td>
                        <td><?php echo htmlspecialchars($producto['CODIGO']); ?></td>
                        <td><?php echo htmlspecialchars($producto['CODIGO_BARRAS']); ?></td>
                        <td><?php echo htmlspecialchars($producto['PRODUCTO']); ?></td>
                        <td><?php echo htmlspecialchars($producto['CANT']); ?></td>
                        <td><?php echo htmlspecialchars($producto['UNIDAD']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar_producto'])): ?>
        <p>No se encontraron resultados para su búsqueda.</p>
    <?php endif; ?>

</body>
</html>

<?php require_once 'includes/footer.php'; ?>
