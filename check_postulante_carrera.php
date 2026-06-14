<?php
$data = json_decode(file_get_contents('db_dump.json'), true);
if (isset($data['postulante_carrera'])) {
    print_r(array_slice($data['postulante_carrera'], 0, 1));
} else {
    echo "No postulante_carrera found";
}
