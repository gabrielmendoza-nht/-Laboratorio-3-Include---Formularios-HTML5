<?php
// procesar.php - Backend: valida, estandariza y guarda la foto (sin base de datos)

// Solo se acepta el envío del formulario por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$errores = [];

/* ---------- 1. Saneamiento y normalización ---------- */
// trim + strip_tags: quitamos espacios y cualquier etiqueta HTML/PHP
function limpiar($valor)
{
    return trim(strip_tags((string)($valor ?? '')));
}

// Formato tipo título con soporte de tildes (equivale a ucwords(strtolower()))
function tipoTitulo($texto)
{
    return mb_convert_case(mb_strtolower($texto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

$nombre         = limpiar($_POST['nombre'] ?? '');
$apellido       = limpiar($_POST['apellido'] ?? '');
$identificacion = limpiar($_POST['identificacion'] ?? '');
$fechaNac       = limpiar($_POST['fecha_nacimiento'] ?? '');
$sexo           = limpiar($_POST['sexo'] ?? '');

/* ---------- 2. Campos no vacíos y formato ---------- */
if ($nombre === '')         $errores[] = 'El nombre es obligatorio.';
if ($apellido === '')       $errores[] = 'El apellido es obligatorio.';
if ($identificacion === '') $errores[] = 'La identificación es obligatoria.';
if ($fechaNac === '')       $errores[] = 'La fecha de nacimiento es obligatoria.';
if ($sexo === '')           $errores[] = 'El sexo es obligatorio.';

if ($nombre !== '' && !preg_match("/^[\p{L}\s'\-]+$/u", $nombre)) {
    $errores[] = 'El nombre solo puede contener letras.';
}
if ($apellido !== '' && !preg_match("/^[\p{L}\s'\-]+$/u", $apellido)) {
    $errores[] = 'El apellido solo puede contener letras.';
}
if ($identificacion !== '' && !preg_match('/^[A-Za-z0-9\-]+$/', $identificacion)) {
    $errores[] = 'La identificación solo puede contener letras, números y guiones.';
}
if ($sexo !== '' && !in_array($sexo, ['Hombre', 'Mujer'], true)) {
    $errores[] = 'El sexo seleccionado no es válido.';
}

// Estandarizar textos: "sofia" -> "Sofia", identificación en MAYÚSCULAS
$nombre         = tipoTitulo($nombre);
$apellido       = tipoTitulo($apellido);
$identificacion = strtoupper($identificacion);

/* ---------- 3. Edad entre 18 y 70 años ---------- */
$edad = null;
if ($fechaNac !== '') {
    $fecha = DateTime::createFromFormat('Y-m-d', $fechaNac);
    if (!$fecha || $fecha->format('Y-m-d') !== $fechaNac) {
        $errores[] = 'La fecha de nacimiento no es válida.';
    } else {
        $hoy = new DateTime('today');
        if ($fecha > $hoy) {
            $errores[] = 'La fecha de nacimiento no puede ser futura.';
        } else {
            $edad = $fecha->diff($hoy)->y;
            if ($edad < 18 || $edad > 70) {
                $errores[] = "La edad ($edad años) debe estar entre 18 y 70 años.";
            }
        }
    }
}

/* ---------- 4. Validar y guardar la foto de forma segura ---------- */
$nombreFoto = null;
$rutaFoto   = null;
$extPermitidas  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$mimePermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxBytes = 2 * 1024 * 1024; // 2 MB

if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
    $errores[] = 'Debe seleccionar una fotografía.';
} elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    $errores[] = 'Ocurrió un error al subir la fotografía (código ' . (int)$_FILES['foto']['error'] . ').';
} else {
    $tmp = $_FILES['foto']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $extPermitidas, true)) {
        $errores[] = 'Extensión no permitida. Use: ' . implode(', ', $extPermitidas) . '.';
    } elseif ($_FILES['foto']['size'] > $maxBytes) {
        $errores[] = 'La fotografía no debe superar los 2 MB.';
    } else {
        // Verificamos el contenido real del archivo, no solo la extensión
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmp);
        if (!in_array($mime, $mimePermitidos, true) || @getimagesize($tmp) === false) {
            $errores[] = 'El archivo no es una imagen válida.';
        } elseif (empty($errores)) {
            // Nombre aleatorio: evita colisiones y nombres maliciosos del usuario
            $nombreFoto = bin2hex(random_bytes(8)) . '.' . $ext;
            $rutaFoto   = __DIR__ . '/uploaded_files/' . $nombreFoto;
            if (!move_uploaded_file($tmp, $rutaFoto)) {
                $errores[] = 'No se pudo guardar la fotografía en el servidor.';
                $nombreFoto = $rutaFoto = null;
            }
        }
    }
}

// La carpeta uploaded_files está bloqueada desde el navegador (.htaccess),
// por eso mostramos la foto leyéndola desde PHP como data URI.
$fotoSrc = null;
if ($rutaFoto && is_file($rutaFoto)) {
    $fotoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($rutaFoto));
}

// htmlspecialchars se aplica al imprimir (previene XSS)
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<?php include 'includes/header.php'; ?>

<main class="flex-grow-1 py-4">
    <section class="container" style="max-width: 520px;">

        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <h2 class="h5">No se pudo completar el registro</h2>
                <ul class="mb-0">
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <a href="index.php" class="btn btn-secondary w-100">Volver al formulario</a>

        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body p-4 text-center">
                    <h1 class="h4 fw-bold text-success mb-3">¡Aspirante registrado con éxito!</h1>

                    <?php if ($fotoSrc): ?>
                        <img src="<?php echo $fotoSrc; ?>" alt="Fotografía del aspirante"
                             class="img-thumbnail rounded-circle mb-3"
                             style="width: 150px; height: 150px; object-fit: cover;">
                    <?php endif; ?>

                    <ul class="list-group list-group-flush text-start mb-3">
                        <li class="list-group-item"><strong>Nombre:</strong> <?php echo e($nombre); ?></li>
                        <li class="list-group-item"><strong>Apellido:</strong> <?php echo e($apellido); ?></li>
                        <li class="list-group-item"><strong>Identificación:</strong> <?php echo e($identificacion); ?></li>
                        <li class="list-group-item"><strong>Fecha de nacimiento:</strong> <?php echo e($fechaNac); ?></li>
                        <li class="list-group-item"><strong>Edad:</strong> <?php echo e($edad); ?> años</li>
                        <li class="list-group-item"><strong>Sexo:</strong> <?php echo e($sexo); ?></li>
                        <li class="list-group-item"><strong>Foto guardada como:</strong> <?php echo e($nombreFoto); ?></li>
                    </ul>

                    <a href="index.php" class="btn btn-primary w-100">Registrar otro aspirante</a>
                </div>
            </div>
        <?php endif; ?>

    </section>
</main>

<?php include 'includes/footer.php'; ?>
