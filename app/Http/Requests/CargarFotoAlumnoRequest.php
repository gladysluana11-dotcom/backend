<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Alumno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class CargarFotoAlumnoRequest extends FormRequest
{
    use RechazaColegioId;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $alumno = $this->route('alumno');

        return $alumno instanceof Alumno
            && ($this->user()?->can('update', $alumno) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'foto' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png',
                'extensions:jpg,jpeg,png',
                'max:1024',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (
                        ! $value instanceof UploadedFile
                        || ! in_array($value->getMimeType(), ['image/jpeg', 'image/png'], true)
                    ) {
                        $fail('La foto debe ser un archivo JPG o PNG válido.');
                    }
                },
            ],
        ];
    }
}
