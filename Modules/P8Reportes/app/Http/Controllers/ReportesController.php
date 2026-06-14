<?php

namespace Modules\P8Reportes\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ReportesController extends Controller
{
    public function generar(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
        ]);

        $prompt = $request->input('prompt');
        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            return response()->json([
                'message' => 'Falta configurar la GEMINI_API_KEY en el archivo .env',
            ], 500);
        }

        // Obtener el esquema real dinámicamente de la base de datos y cachearlo por 1 hora
        $esquemaDinamico = Cache::remember('db_schema_string', 3600, function () {
            $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
            $schemaString = "";
            foreach ($tables as $table) {
                // Omitir tablas internas de Laravel
                if (in_array($table->table_name, ['migrations', 'personal_access_tokens', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'])) {
                    continue;
                }
                $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ?", [$table->table_name]);
                $colNames = array_map(function($c) { return $c->column_name; }, $columns);
                $schemaString .= "- " . $table->table_name . " (" . implode(', ', $colNames) . ")\n";
            }
            return $schemaString;
        });

        // Definimos el esquema y damos inteligencia de nivel High-End al modelo usando Heredoc (sin errores de comillas)
        $schemaContext = <<<EOT
<SystemPrompt>
Eres un Arquitecto de Datos Senior (High-Level AI) especialista en PostgreSQL.
Tu tarea es convertir requerimientos de negocio en lenguaje natural a consultas SQL avanzadas, eficientes y absolutamente libres de errores.
</SystemPrompt>

<DatabaseSchema>
$esquemaDinamico
</DatabaseSchema>

<BusinessLogic>
- La tabla 'persona' es el núcleo. Todos los nombres reales (de postulantes, usuarios, etc.) provienen de 'persona.nombre'. Únela usando 'id_persona' o equivalente.
- Estados de pago: "Pagado" o "Ya pagaron" significa que en la tabla 'pago', la columna 'estado' es igual a 'Procesado'.
- Estados generales: "Activos" significa que la columna 'estado' es igual a 'Activo'.
- Género o Sexo: "Femenino" o "Mujeres" significa `sexo = 'F'`. "Masculino" o "Hombres" significa `sexo = 'M'`.
</BusinessLogic>

<StrictDirectives>
1. PROHIBICIÓN DE ALIAS EN TABLAS: NUNCA uses alias cortos para las tablas (como "p", "u", "post"). Usa SIEMPRE el nombre completo de la tabla (ej. persona.nombre, pago.monto). Esto es obligatorio para evitar errores de ambigüedad "Undefined table" en subconsultas complejas.
2. PRECISIÓN EN COLUMNAS: Selecciona columnas exactas. Usa la palabra clave AS con comillas dobles para dar nombres estéticos a la salida (ej. persona.nombre AS "Nombre del Postulante", postulante.colegio AS "Colegio").
3. CONSULTAS NEGATIVAS Y COMPLEJAS: Si el usuario pide exclusiones (ej. "los que NO han pagado"), usa NOT IN o NOT EXISTS con subconsultas lógicas. Recuerda usar los nombres completos de las tablas también dentro de la subconsulta. Agrega DISTINCT si consideras que los JOINs traerán duplicados.
</StrictDirectives>

<OutputFormat>
Devuelve ÚNICAMENTE la instrucción SQL cruda. No incluyas bloques de código markdown. No incluyas texto, saludos ni explicaciones. Tu respuesta será ejecutada directamente por el motor de la base de datos.
</OutputFormat>

<UserInput>
$prompt
</UserInput>
EOT;

        try {
            // Llamada a la API de Gemini
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $schemaContext]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('Error de Gemini API: ' . $response->body());
                return response()->json([
                    'message' => 'Error al comunicarse con la IA (Gemini).'
                ], 500);
            }

            $geminiData = $response->json();
            $sqlQuery = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Limpiar la consulta SQL (por si la IA añade markdown)
            $sqlQuery = trim($sqlQuery);
            $sqlQuery = preg_replace('/^```sql\s*/i', '', $sqlQuery);
            $sqlQuery = preg_replace('/\s*```$/i', '', $sqlQuery);
            $sqlQuery = trim($sqlQuery);

            Log::info("SQL generado por IA: " . $sqlQuery);

            // Validar que solo sea SELECT
            if (empty($sqlQuery) || stripos(trim($sqlQuery), 'SELECT') !== 0) {
                return response()->json([
                    'message' => 'La IA no pudo generar una consulta segura para esta solicitud.'
                ], 422);
            }

            // Ejecutar la consulta en la BD
            $results = DB::select($sqlQuery);

            // Extraer las columnas
            $columns = [];
            if (count($results) > 0) {
                $columns = array_keys((array) $results[0]);
            }

            return response()->json([
                'columns' => $columns,
                'rows' => $results,
                'sql' => $sqlQuery // Opcional: útil para depuración en frontend
            ]);

        } catch (\Exception $e) {
            Log::error('Excepción al generar reporte: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error ejecutando la consulta generada: ' . $e->getMessage()
            ], 500);
        }
    }
}
