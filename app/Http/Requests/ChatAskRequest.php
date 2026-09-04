<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChatAskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question'   => ['required', 'string', 'min:2', 'max:500'],
            'session_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => 'A pergunta é obrigatória.',
            'question.string'   => 'A pergunta deve ser um texto válido.',
            'question.min'      => 'A pergunta deve conter no mínimo 2 caracteres.',
            'question.max'      => 'A pergunta não pode exceder 500 caracteres.',
            'session_id.string' => 'O ID de sessão deve ser um texto válido.',
            'session_id.max'    => 'O ID de sessão não pode exceder 100 caracteres.',
        ];
    }

    /**
     * Resposta JSON padronizada em caso de erro de validação (sem stack trace).
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'Dados da requisição inválidos.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}