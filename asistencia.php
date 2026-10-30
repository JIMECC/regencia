<?php
require "sesion.php";
solo_para("Regente", "Director");   
$pagina = "asistencia";

$hoy   = date("Y-m-d");
$error = "";

function inscripciones_del_curso($conexion, $curso_id, $gestion_id) {
    $st = $conexion->prepare("SELECT id FROM inscripcion WHERE curso_id = ? AND gestion_id = ? AND estado = 'Efectivo'");
    $st->bind_param("ii", $curso_id, $gestion_id);
    $st->execute();
    return array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), "id");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $curso_id = (int) ($_POST["curso_id"] ?? 0);
    $fecha    = $_POST["fecha"] ?? "";

    if (!fecha_valida($fecha) || $fecha > $hoy) {
        $error = "La fecha no es válida (no se puede registrar asistencia de días futuros).";
    } else {
        $validas     = inscripciones_del_curso($conexion, $curso_id, $gestion_id);
        $sin_llegada = [$id_estado["F"], $id_estado["L"]];

        $st = $conexion->prepare(
            "INSERT INTO asistencia (inscripcion_id, fecha, estado_id, hora_llegada, observacion, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE estado_id = VALUES(estado_id), hora_llegada = VALUES(hora_llegada),
                                     observacion = VALUES(observacion), usuario_id = VALUES(usuario_id)"
        );
        foreach ($_POST["estado"] ?? [] as $insc => $estado) {
            $insc   = (int) $insc;
            $estado = (int) $estado;
            if (!in_array($insc, $validas) || !in_array($estado, $id_estado)) continue;

            $llegada = nulo($_POST["hora_llegada"][$insc] ?? "");
            if (in_array($estado, $sin_llegada)) $llegada = null;   
            $obs = nulo($_POST["observacion"][$insc] ?? "");

            $st->bind_param("isissi", $insc, $fecha, $estado, $llegada, $obs, $usuario_id);
            $st->execute();
        }
        header("Location: index.php?ok=$curso_id&fecha=$fecha");
        exit;
    }
}

$curso_id = (int) ($_GET["curso_id"] ?? $_POST["curso_id"] ?? 0);
$fecha    = $_GET["fecha"] ?? $_POST["fecha"] ?? $hoy;
if (!fecha_valida($fecha) || $fecha > $hoy) $fecha = $hoy;

$curso_nombre = "";
foreach ($cursos as $c) if ($c["id"] == $curso_id) $curso_nombre = $c["nombre"] . " - " . $c["turno"];

$lista = [];
$ya_registrado = false;
if ($curso_nombre) {
    $st = $conexion->prepare(
        "SELECT i.id AS insc, e.rude,
                CONCAT(e.apellido_paterno, ' ', IFNULL(e.apellido_materno, ''), ', ', e.nombres) AS nombre,
                a.estado_id, a.hora_llegada, a.observacion,
                (SELECT l.motivo FROM licencia l
                  WHERE l.inscripcion_id = i.id AND l.estado = 'Aprobada'
                    AND ? BETWEEN l.fecha_inicio AND l.fecha_fin LIMIT 1) AS licencia
         FROM inscripcion i
         JOIN estudiante e ON e.id = i.estudiante_id
         LEFT JOIN asistencia a ON a.inscripcion_id = i.id AND a.fecha = ?
         WHERE i.curso_id = ? AND i.gestion_id = ? AND i.estado = 'Efectivo'
         ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres"
    );
    $st->bind_param("ssii", $fecha, $fecha, $curso_id, $gestion_id);
    $st->execute();
    $lista = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($lista as $fila) if ($fila["estado_id"]) $ya_registrado = true;
}

$colores = ["P" => "success", "F" => "danger", "A" => "warning", "L" => "info"];

include "encabezado.php";
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 m-0">Registrar asistencia</h1>
    <a href="index.php?fecha=<?= h($fecha) ?>" class="btn btn-outline-secondary">&larr; Volver al inicio</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

<!-- Elegir curso y fecha -->
<form method="get" class="card card-body mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-6">
            <label class="form-label">Curso</label>
            <select name="curso_id" class="form-select" required onchange="this.form.submit()">
                <option value="">-- Elige el curso --</option>
                <?php foreach ($cursos as $c): ?>
                    <option value="<?= $c["id"] ?>" <?= $c["id"] == $curso_id ? "selected" : "" ?>>
                        <?= h($c["nombre"] . " - " . $c["turno"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Fecha</label>
            <input type="date" name="fecha" class="form-control" value="<?= h($fecha) ?>" max="<?= $hoy ?>" required onchange="this.form.submit()">
        </div>
        <div class="col-md-2">
            <button class="btn btn-secondary w-100">Ver lista</button>
        </div>
    </div>
</form>

<?php if ($curso_nombre): ?>
    <?php if ((int) date("N", strtotime($fecha)) >= 6): ?>
        <div class="alert alert-warning">Atención: el <?= fecha_es($fecha) ?> es <?= dia_semana($fecha) ?>.</div>
    <?php endif; ?>

    <?php if (!$lista): ?>
        <div class="alert alert-warning">Este curso no tiene estudiantes inscritos. Inscríbelos desde <a href="estudiantes.php">Estudiantes</a>.</div>
    <?php else: ?>
    <form method="post" class="card card-body">
        <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
        <input type="hidden" name="fecha" value="<?= h($fecha) ?>">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <strong><?= h($curso_nombre) ?></strong> · <?= dia_semana($fecha) ?> <?= fecha_es($fecha) ?>
                <?php if ($ya_registrado): ?>
                    <span class="badge text-bg-success ms-2">Ya registrado: estás corrigiendo</span>
                <?php else: ?>
                    <span class="badge text-bg-secondary ms-2">Sin registrar</span>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-outline-success btn-sm" onclick="todosPresentes()">Marcar todos presentes</button>
        </div>

        <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr><th>#</th><th>Estudiante</th><th>Asistencia</th><th>Hora llegada</th><th>Observación</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lista as $n => $est):
                $id = $est["insc"];
                // Si aún no se registró: Licencia si tiene una aprobada, si no Presente
                $actual = $est["estado_id"] ?? ($est["licencia"] ? $id_estado["L"] : $id_estado["P"]); ?>
                <tr>
                    <td><?= $n + 1 ?></td>
                    <td>
                        <?= h($est["nombre"]) ?><br>
                        <small class="text-muted">RUDE <?= h($est["rude"]) ?></small>
                        <?php if ($est["licencia"]): ?>
                            <br><span class="badge text-bg-info">Licencia aprobada: <?= h($est["licencia"]) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                        <?php foreach ($estados as $e): $radio = "e{$id}_{$e['id']}"; ?>
                            <input type="radio" class="btn-check" name="estado[<?= $id ?>]" id="<?= $radio ?>"
                                   value="<?= $e["id"] ?>" data-fila="<?= $id ?>" data-codigo="<?= $e["codigo"] ?>"
                                   data-licencia="<?= $est["licencia"] ? 1 : 0 ?>"
                                   <?= $e["id"] == $actual ? "checked" : "" ?>>
                            <label class="btn btn-outline-<?= $colores[$e["codigo"]] ?? "secondary" ?>" for="<?= $radio ?>"
                                   title="<?= h($e["descripcion"]) ?>"><?= $e["codigo"] ?></label>
                        <?php endforeach; ?>
                        </div>
                    </td>
                    <td><input type="time" name="hora_llegada[<?= $id ?>]" id="llegada<?= $id ?>" class="form-control form-control-sm"
                               value="<?= h(substr($est["hora_llegada"] ?? "", 0, 5)) ?>"></td>
                    <td><input type="text" name="observacion[<?= $id ?>]" class="form-control form-control-sm" maxlength="200"
                               value="<?= h($est["observacion"] ?? "") ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="small text-muted">P = Presente · F = Falta · A = Atraso · L = Licencia</p>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-lg flex-grow-1">Guardar asistencia</button>
            <a href="index.php?fecha=<?= h($fecha) ?>" class="btn btn-outline-secondary btn-lg"
               onclick="return confirm('¿Salir sin guardar? Los cambios se perderán.');">Cancelar</a>
        </div>
    </form>

    <script>
    
    function ajustarLlegada(radio) {
        const campo = document.getElementById("llegada" + radio.dataset.fila);
        const sinLlegada = radio.dataset.codigo === "F" || radio.dataset.codigo === "L";
        if (sinLlegada) campo.value = "";
        campo.disabled = sinLlegada;
    }
    document.querySelectorAll("input[data-fila]").forEach(function (radio) {
        radio.addEventListener("change", function () { ajustarLlegada(radio); });
        if (radio.checked) ajustarLlegada(radio);
    });
    
    function todosPresentes() {
        document.querySelectorAll("input[data-codigo='P']").forEach(function (radio) {
            if (radio.dataset.licencia === "1") return;
            radio.checked = true;
            ajustarLlegada(radio);
        });
    }
    </script>
    <?php endif; ?>
<?php endif; ?>

<?php include "pie.php"; ?>
