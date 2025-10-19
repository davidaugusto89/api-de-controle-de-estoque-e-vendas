<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de request para criação de venda.
 *
 * Exemplo de payload aceito:
 * {
 *   "items": [
 *     {"product_id": 1, "quantity": 2, "unit_price": 10.5}
 *   ]
 * }
 */
final class CreateSaleRequest extends FormRequest
{
    /**
     * Autoriza a requisição. Ajustar caso a aplicação exija permissões.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras de validação para o payload de criação de venda.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Mensagens de validação personalizadas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Informe ao menos um item.',
        ];
    }
}
