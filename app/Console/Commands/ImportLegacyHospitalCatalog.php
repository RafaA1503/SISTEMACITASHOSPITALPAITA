<?php

namespace App\Console\Commands;

use App\Models\AppointmentType;
use App\Models\OrganizationalUnit;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\ServiceSubarea;
use App\Models\ServiceType;
use App\Models\Specialty;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportLegacyHospitalCatalog extends Command
{
    protected $signature = 'hospital:import-legacy-catalog {dump? : Ruta del respaldo SQL}';
    protected $description = 'Importa servicios, especialidades, áreas y subáreas del respaldo SIGESA sin importar historias clínicas';

    public function handle(): int
    {
        $path = $this->argument('dump') ?: storage_path('app/database_exports/dump-hospitallasmerce_sigh-202608051517.sql');
        if (! is_file($path)) {
            $this->error("No se encontró el respaldo: {$path}");
            return self::FAILURE;
        }

        DB::transaction(function () use ($path) {
            foreach ($this->rows($path, 'tiposservicio') as $row) {
                ServiceType::updateOrCreate(['legacy_id' => (int) $row[0]], ['name' => $row[1], 'active' => true]);
            }

            foreach ($this->rows($path, 'especialidades') as $row) {
                Specialty::updateOrCreate(['legacy_id' => (int) $row[0]], [
                    'name' => $row[1],
                    'department_code' => $row[2] ?? null,
                    'average_attention_minutes' => $this->nullableInt($row[3] ?? null),
                    'minsa_code' => $row[4] ?? null,
                    'allows_referral' => (bool) ($row[5] ?? false),
                    'active' => true,
                ]);
            }

            foreach ($this->rows($path, 'unidadorganica') as $row) {
                OrganizationalUnit::updateOrCreate(['legacy_id' => (int) $row[0]], ['name' => $row[1], 'active' => (bool) ($row[2] ?? true)]);
            }

            foreach ($this->rows($path, 'servicios') as $row) {
                $specialty = Specialty::where('legacy_id', $this->nullableInt($row[2] ?? null))->first();
                $service = Service::updateOrCreate(['legacy_id' => (int) $row[0]], [
                    'name' => trim($row[1]),
                    'code' => 'SIG-'.$row[0],
                    'specialty_id' => $specialty?->id,
                    'service_type_id' => ServiceType::where('legacy_id', $this->nullableInt($row[3] ?? null))->value('id'),
                    'category' => $this->category($specialty?->department_code, $this->nullableInt($row[3] ?? null), $row[1]),
                    'active' => (int) ($row[5] ?? 1) === 1,
                    'report_enabled' => (bool) ($row[4] ?? false),
                    'notes' => $row[6] ?? null,
                ]);
                AppointmentType::updateOrCreate(['code' => 'SIG-A-'.$row[0]], [
                    'service_id' => $service->id,
                    'name' => 'Atención en '.$service->name,
                    'duration_minutes' => $service->specialty?->average_attention_minutes ?: 20,
                    'requires_order' => false,
                    'active' => $service->active,
                ]);
            }

            foreach ($this->rows($path, 'areas') as $row) {
                $serviceId = Service::where('legacy_id', (int) $row[1])->value('id');
                if (! $serviceId) continue;
                ServiceArea::updateOrCreate(['legacy_id' => (int) $row[0]], [
                    'service_id' => $serviceId, 'name' => $row[2], 'critical' => (bool) $row[3],
                    'food_access' => (bool) $row[4], 'active' => (bool) $row[5],
                ]);
            }

            foreach ($this->rows($path, 'subareas') as $row) {
                $areaId = ServiceArea::where('legacy_id', (int) $row[1])->value('id');
                if (! $areaId) continue;
                ServiceSubarea::updateOrCreate(['legacy_id' => (int) $row[0]], [
                    'service_area_id' => $areaId, 'name' => $row[2],
                    'food_access' => (bool) $row[3], 'active' => (bool) $row[4],
                ]);
            }
        });

        $this->info('Catálogo SIGESA importado sin datos clínicos de pacientes.');
        $this->table(['Elemento', 'Cantidad'], [
            ['Tipos de servicio', ServiceType::count()], ['Especialidades', Specialty::count()],
            ['Unidades orgánicas', OrganizationalUnit::count()], ['Servicios', Service::whereNotNull('legacy_id')->count()],
            ['Áreas', ServiceArea::count()], ['Subáreas', ServiceSubarea::count()],
        ]);
        return self::SUCCESS;
    }

    private function rows(string $path, string $table): array
    {
        $contents = file_get_contents($path);
        if (! preg_match('/INSERT INTO `'.preg_quote($table, '/').'` VALUES (.*?);\R/s', $contents, $match)) return [];
        $rows = [];
        foreach ($this->splitTuples($match[1]) as $tuple) {
            $values = str_getcsv(substr($tuple, 1, -1), ',', "'", '\\');
            $rows[] = array_map(fn ($value) => strtoupper(trim($value)) === 'NULL' ? null : $value, $values);
        }
        if (! $rows && trim($match[1]) !== '') throw new RuntimeException("No se pudo interpretar la tabla {$table}.");
        return $rows;
    }

    private function splitTuples(string $sql): array
    {
        $tuples = []; $start = null; $quoted = false; $escaped = false; $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quoted) {
                if ($escaped) { $escaped = false; continue; }
                if ($char === '\\') { $escaped = true; continue; }
                if ($char === "'") $quoted = false;
                continue;
            }
            if ($char === "'") { $quoted = true; continue; }
            if ($char === '(' && $start === null) $start = $i;
            if ($char === ')' && $start !== null) { $tuples[] = substr($sql, $start, $i - $start + 1); $start = null; }
        }
        return $tuples;
    }

    private function nullableInt(mixed $value): ?int { return $value === null || $value === '' ? null : (int) $value; }

    private function category(?string $departmentCode, ?int $type, string $name): string
    {
        // IdDepartamento de `especialidades`: 7 agrupa Laboratorio/Banco de Sangre/Patología Clínica,
        // 9 agrupa Radiología/Ecografía/Tomografía/Resonancia. Es la clasificación real del respaldo.
        if ($departmentCode === '7') return 'laboratorio';
        if ($departmentCode === '9') return 'imagenes';
        $text = mb_strtolower($name);
        if (str_contains($text, 'laboratorio') || str_contains($text, 'sangre')) return 'laboratorio';
        if (str_contains($text, 'imagen') || str_contains($text, 'radiolog')) return 'imagenes';
        return $type === 8 ? 'administrativo' : 'consulta';
    }
}
