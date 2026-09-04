<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class GenerateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days'       => ['nullable', 'integer', 'min:1', 'max:365'],
            'goal'       => ['nullable', 'string', 'max:300'],
            'session_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'days.integer' => 'A quantidade de dias deve ser um número inteiro.',
            'days.min'     => 'A quantidade de dias deve ser de no mínimo 1 dia.',
            'days.max'     => 'A quantidade de dias não pode exceder 365 dias.',
            'goal.string'  => 'O objetivo da campanha deve ser um texto válido.',
            'goal.max'     => 'O objetivo da campanha não pode exceder 300 caracteres.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'Parâmetros da campanha inválidos.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}