<?php

namespace App\Imports;

use Modules\P1SeguridadYAuditoria\Models\Persona;
use Modules\P1SeguridadYAuditoria\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Exception;

class PostulantesImport implements ToModel, WithHeadingRow
{
    private $errores = [];
    private $exitos = 0;

    public function model(array $row)
    {
        try {
            // Validar campos básicos
            if (!isset($row['carnet']) || !isset($row['nombre'])) {
                $this->errores[] = "Fila sin Carnet o Nombre.";
                return null;
            }

            $ci = $row['carnet'];
            
            // Verificar si ya existe la persona
            $persona = Persona::where('ci', $ci)->first();

            if (!$persona) {
                // Crear nueva persona
                $persona = Persona::create([
                    'ci' => $ci,
                    'nombre' => $row['nombre'],
                    'sexo' => $row['sexo'] ?? 'M',
                    'telefono' => $row['telefono'] ?? null,
                    'correo' => $row['correo'] ?? ($ci . '@postulante.edu.bo')
                ]);

                $rolId = \Modules\P1SeguridadYAuditoria\Models\Rol::where('nombre', 'Postulante')->value('id') ?? 5;

                // Asignar cuenta de usuario
                Usuario::create([
                    'id_persona' => $persona->id,
                    'id_rol' => $rolId,
                    'email' => $row['correo'] ?? ($ci . '@postulante.edu.bo'),
                    'password' => Hash::make($ci), // Contraseña es el CI por defecto
                    'estado' => 'Activo'
                ]);

                $this->exitos++;
            } else {
                $this->errores[] = "Carnet {$ci} ya está registrado.";
            }

        } catch (Exception $e) {
            $this->errores[] = "Error en el Carnet {$row['carnet']}: " . $e->getMessage();
        }

        return null; // Retornamos null porque ya lo gestionamos manualmente
    }

    public function getResultados()
    {
        return [
            'exitos' => $this->exitos,
            'errores' => $this->errores
        ];
    }
}
