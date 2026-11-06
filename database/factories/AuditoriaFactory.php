<?php

namespace Database\Factories;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auditoria>
 */
class AuditoriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'colegio_id' => null,
            'usuario_id' => null,
            'evento' => fake()->word(),
            'entidad' => 'usuarios',
            'entidad_id' => null,
            'valores_anteriores' => null,
            'valores_nuevos' => null,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'fecha' => now(),
        ];
    }
}
