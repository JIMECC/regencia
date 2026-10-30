<?php
session_start();
require_once "conexion.php";

if (!empty($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

$error = "";
$usuario_escrito = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario_escrito = trim($_POST["usuario"] ?? "");
    $clave_escrita   = $_POST["clave"] ?? "";

    $st = $conexion->prepare("SELECT id, nombre, clave, rol, activo FROM usuario WHERE usuario = ?");
    $st->bind_param("s", $usuario_escrito);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();

    if ($u && password_verify($clave_escrita, $u["clave"]) && !$u["activo"]) {
        $error = "Tu cuenta está desactivada. Consulta con el director.";
    } elseif ($u && password_verify($clave_escrita, $u["clave"])) {
        session_regenerate_id(true);
        $_SESSION["usuario_id"] = $u["id"];
        $_SESSION["nombre"]     = $u["nombre"];
        $_SESSION["rol"]        = $u["rol"];
        header("Location: index.php");
        exit;
    }
    if (!$error) $error = "Usuario o contraseña incorrectos.";
} elseif (isset($_GET["inactivo"])) {
    $error = "Tu cuenta fue desactivada. Consulta con el director.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar - Regencia <?= NOMBRE_UE ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">
<div class="container" style="max-width: 400px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 text-center mb-1"><?= NOMBRE_UE ?></h1>
            <p class="text-center text-muted mb-4">Regencia · Control de asistencia</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <input type="text" name="usuario" class="form-control" value="<?= h($usuario_escrito) ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="clave" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100">Ingresar</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
