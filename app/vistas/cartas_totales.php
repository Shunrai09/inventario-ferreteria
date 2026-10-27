<?php
// Indicadores (tarjetas de totales). Recibe $cartas: lista de [titulo, icono, total, color].
// Todas siguen el estilo del sistema de diseño (clase .indicador); 'color' ya no se usa.
?>
<div class="row g-3 mb-4">
    <?php foreach($cartas as $carta): ?>
    <div class="col-6 col-xl">
        <div class="indicador">
            <div class="indicador-icono"><i class="<?= $carta['icono'] ?>"></i></div>
            <div class="indicador-cuerpo">
                <div class="indicador-etiqueta"><?= $carta['titulo'] ?></div>
                <div class="indicador-valor"><?= $carta['total'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
