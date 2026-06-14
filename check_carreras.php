<?php
$data = json_decode(file_get_contents('db_dump.json'), true);

$carreras = $data['carrera'];
$modalidades = $data['modalidad'] ?? []; // wait, is modalidad in db_dump.json?
if (empty($modalidades)) {
    echo "No modalidades found in db_dump.json\n";
} else {
    print_r($modalidades);
}
print_r($carreras);
