<?php
$pagina = $pagina ?? "";
function activo($nombre) {
    global $pagina;
    return $pagina === $nombre ? " active" : "";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Regencia - <?= NOMBRE_UE ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 d-print-none">
    <div class="container">
        <a class="navbar-brand" href="index.php">Regencia · U.E. Guadalupe</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <div class="navbar-nav me-auto">
                <a class="nav-link<?= activo("inicio") ?>" href="index.php">Inicio</a>
                <?php if ($rol !== "Secretaria"): ?>
                <a class="nav-link<?= activo("asistencia") ?>" href="asistencia.php">Asistencia</a>
                <?php endif; ?>
                <a class="nav-link<?= activo("estudiantes") ?>" href="estudiantes.php">Estudiantes</a>
                <a class="nav-link<?= activo("licencias") ?>" href="licencias.php">Licencias</a>
                <a class="nav-link<?= activo("faltas") ?>" href="faltas_dia.php">Faltas del día</a>
                <a class="nav-link<?= activo("reporte") ?>" href="reporte.php">Reporte trimestral</a>
                <?php if ($rol === "Director"): ?>
                <a class="nav-link<?= activo("usuarios") ?>" href="usuarios.php">Usuarios</a>
                <?php endif; ?>
            </div>
            <span class="navbar-text text-white me-3"><?= h($_SESSION["nombre"]) ?> · <?= h($rol) ?></span>
            <a class="btn btn-sm btn-outline-light" href="salir.php">Salir</a>
        </div>
    </div>
</nav>
<div class="container pb-5">
