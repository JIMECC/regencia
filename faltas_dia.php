<?php
require "sesion.php";
$pagina = "faltas";

$hoy   = date("Y-m-d");
$fecha = fecha_valida($_GET["fecha"] ?? "") ? $_GET["fecha"] : $hoy;
$tipo  = in_array($_GET["tipo"] ?? "", ["F", "A", "L"]) ? $_GET["tipo"] : "FA";

$codigos = str_split($tipo);   // ["F","A"] o uno solo
$marcas  = implode(",", array_fill(0, count($codigos), "?"));

$st = $conexion->prepare(
    "SELECT " . SQL_CURSO . " AS curso, ea.codigo, ea.descripcion, a.hora_llegada, a.observacion,
            e.nombres,
            CONCAT(e.apellido_paterno, ' ', IFNULL(e.apellido_materno, ''), ', ', e.nombres) AS nombre,
            CONCAT(t.nombres, ' ', t.apellidos) AS tutor, t.telefono
     FROM asistencia a
     JOIN estado_asistencia ea ON ea.id = a.estado_id
     JOIN inscripcion i ON i.id = a.inscripcion_id
     JOIN estudiante e  ON e.id = i.estudiante_id
     JOIN curso c ON c.id = i.curso_id JOIN nivel n ON n.id = c.nivel_id
     LEFT JOIN (SELECT estudiante_id, MIN(tutor_id) AS tutor_id FROM estudiante_tutor GROUP BY estudiante_id) et
            ON et.estudiante_id = e.id
     LEFT JOIN tutor t ON t.id = et.tutor_id
     WHERE a.fecha = ? AND ea.codigo IN ($marcas)
     ORDER BY n.id, c.grado, c.paralelo, ea.codigo DESC, e.apellido_paterno, e.nombres"
);
$st->bind_param("s" . str_repeat("s", count($codigos)), $fecha, ...$codigos);
$st->execute();
$lista = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$colores = ["F" => "danger", "A" => "warning", "L" => "info"];

include "encabezado.php";
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h3 m-0">Faltas y atrasos · <?= dia_semana($fecha) ?> <?= fecha_es($fecha) ?></h1>
    <a href="index.php" class="btn btn-outline-secondary d-print-none">&larr; Volver al inicio</a>
</div>

<form method="get" class="row g-2 mb-3 d-print-none">
    <div class="col-auto">
        <input type="date" name="fecha" class="form-control" value="<?= h($fecha) ?>" max="<?= $hoy ?>" onchange="this.form.submit()">
    </div>
    <div class="col-auto">
        <select name="tipo" class="form-select" onchange="this.form.submit()">
            <?php foreach (["FA" => "Faltas y atrasos", "F" => "Solo faltas", "A" => "Solo atrasos", "L" => "Licencias"] as $v => $t): ?>
                <option value="<?= $v ?>" <?= $tipo === $v ? "selected" : "" ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
    </div>
</form>

<?php if (!$lista): ?>
    <div class="alert alert-info">No hay registros para esta fecha. Si todavía no se pasó asistencia, hazlo desde <a href="index.php?fecha=<?= h($fecha) ?>">Inicio</a>.</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr><th>Curso</th><th>Estudiante</th><th>Tipo</th><th>Llegada</th><th>Observación</th>
                <th>Tutor</th><th>Teléfono del tutor</th></tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $f): ?>
            <tr>
                <td><?= h($f["curso"]) ?></td>
                <td><?= h($f["nombre"]) ?></td>
                <td><span class="badge text-bg-<?= $colores[$f["codigo"]] ?>"><?= h($f["descripcion"]) ?></span></td>
                <td><?= h(substr($f["hora_llegada"] ?? "", 0, 5)) ?></td>
                <td><?= h($f["observacion"]) ?></td>
                <td><?= h($f["tutor"]) ?></td>
                <td><?= $f["telefono"] ? h($f["telefono"]) : '<span class="text-muted">Sin teléfono</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<p class="small text-muted mt-2"><?= count($lista) ?> registro(s)</p>
<?php endif; ?>

<?php include "pie.php"; ?>
