<?php

namespace App\Support;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UnitNumberingService
{
    public const SCHEME_FLOOR_BASED = 'floor_based';

    public const SCHEME_SEQUENTIAL = 'sequential';

    /**
     * @return list<string>
     */
    public static function schemes(): array
    {
        return [
            self::SCHEME_FLOOR_BASED,
            self::SCHEME_SEQUENTIAL,
        ];
    }

    public function label(string $scheme): string
    {
        return match ($scheme) {
            self::SCHEME_FLOOR_BASED => 'Por piso (101, 102…)',
            self::SCHEME_SEQUENTIAL => 'Consecutivos (1, 2, 3…)',
            default => $scheme,
        };
    }

    public function resolveScheme(Property $property): ?string
    {
        if (! $property->units()->exists()) {
            return null;
        }

        if ($property->unit_numbering_scheme !== null) {
            return $property->unit_numbering_scheme;
        }

        return $this->inferSchemeFromUnits($property);
    }

    public function clearSchemeIfNoUnits(Property $property): void
    {
        if ($property->units()->exists()) {
            return;
        }

        if ($property->unit_numbering_scheme === null) {
            return;
        }

        $property->update(['unit_numbering_scheme' => null]);
    }

    public function lockScheme(Property $property, string $scheme): void
    {
        if (! in_array($scheme, self::schemes(), true)) {
            throw new InvalidArgumentException('Nomenclatura no válida.');
        }

        if ($property->units()->exists() && $property->unit_numbering_scheme !== null) {
            return;
        }

        $property->update(['unit_numbering_scheme' => $scheme]);
    }

    public function convertPropertyUnits(Property $property, string $newScheme): int
    {
        if (! in_array($newScheme, self::schemes(), true)) {
            throw new InvalidArgumentException('Nomenclatura no válida.');
        }

        $propertyCode = trim((string) $property->code);
        if ($propertyCode === '') {
            throw new InvalidArgumentException('La propiedad debe tener un código para renumerar unidades.');
        }

        return DB::transaction(function () use ($property, $newScheme): int {
            $units = Unit::query()
                ->where('property_id', $property->id)
                ->get()
                ->sortBy([
                    fn (Unit $unit): int => (int) ($unit->floor ?? 0),
                    fn (Unit $unit): string => (string) $unit->code,
                ])
                ->values();

            $definitions = $this->buildRenumberDefinitions($property, $units, $newScheme);
            $codes = array_column($definitions, 'code');

            if (count($codes) !== count(array_unique($codes))) {
                throw new InvalidArgumentException('La conversión generaría códigos duplicados.');
            }

            foreach ($definitions as $definition) {
                Unit::query()
                    ->whereKey($definition['id'])
                    ->update([
                        'code' => $definition['code'],
                        'name' => $definition['name'],
                    ]);
            }

            $property->update(['unit_numbering_scheme' => $newScheme]);

            return count($definitions);
        });
    }

    public function extractUnitNumber(string $propertyCode, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $propertyCode = trim($propertyCode);
        if ($propertyCode !== '' && str_starts_with($code, $propertyCode.'-')) {
            return substr($code, strlen($propertyCode) + 1);
        }

        return $code;
    }

    public function buildUnitNumber(int $floor, int $index, string $scheme): string
    {
        if ($scheme === self::SCHEME_SEQUENTIAL) {
            return (string) $index;
        }

        return $floor.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    }

    public function buildUnitCode(Property $property, string $unitNumber): string
    {
        $cleanNumber = preg_replace('/\s+/', '', $unitNumber) ?? '';

        return TextCase::upperRequired(trim((string) $property->code).'-'.$cleanNumber);
    }

    private function inferSchemeFromUnits(Property $property): ?string
    {
        $units = Unit::query()
            ->where('property_id', $property->id)
            ->get(['floor', 'code']);

        if ($units->isEmpty()) {
            return null;
        }

        $floorBasedMatches = 0;
        $sequentialMatches = 0;

        foreach ($units as $unit) {
            $floor = (int) ($unit->floor ?? 0);
            $number = $this->extractUnitNumber((string) $property->code, $unit->code);
            if ($number === null || $number === '' || ! ctype_digit($number)) {
                continue;
            }

            if ($floor > 0 && str_starts_with($number, (string) $floor) && strlen($number) > strlen((string) $floor)) {
                $floorBasedMatches++;
            }

            if ((int) $number <= 999) {
                $sequentialMatches++;
            }
        }

        return $floorBasedMatches >= $sequentialMatches
            ? self::SCHEME_FLOOR_BASED
            : self::SCHEME_SEQUENTIAL;
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @return list<array{id: int, code: string, name: string}>
     */
    private function buildRenumberDefinitions(Property $property, Collection $units, string $scheme): array
    {
        if ($units->isEmpty()) {
            return [];
        }

        $definitions = [];

        if ($scheme === self::SCHEME_SEQUENTIAL) {
            $counter = 1;
            foreach ($units as $unit) {
                $number = (string) $counter;
                $definitions[] = $this->definitionForUnit($property, $unit, $number);
                $counter++;
            }

            return $definitions;
        }

        $byFloor = $units->groupBy(fn (Unit $unit): int => max(1, (int) ($unit->floor ?? 1)));

        foreach ($byFloor->sortKeys() as $floor => $floorUnits) {
            $index = 1;
            foreach ($floorUnits as $unit) {
                $number = $this->buildUnitNumber((int) $floor, $index, $scheme);
                $definitions[] = $this->definitionForUnit($property, $unit, $number);
                $index++;
            }
        }

        return $definitions;
    }

    /**
     * @return array{id: int, code: string, name: string}
     */
    private function definitionForUnit(Property $property, Unit $unit, string $number): array
    {
        return [
            'id' => $unit->id,
            'code' => $this->buildUnitCode($property, $number),
            'name' => 'Departamento '.$number,
        ];
    }
}
