<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de request para registrar uma entrada de estoque.
 *
 * Payload esperado:
 * {
 *   "product_id": 1,
 *   "quantity": 10,
 *   "unit_cost": 5.25 // opcional
 * }
 */
final class RegisterInventoryRequest extends FormRequest
{
    /**
     * Autoriza a requisição. Ajustar se for necessário validar permissões.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras de validação para o registro de entrada de estoque.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            // o teste pede custo na entrada — usamos para custo médio móvel
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
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
            'product_id.required' => 'Informe o produto.',
            'product_id.exists' => 'Produto não encontrado.',
            'quantity.required' => 'Informe a quantidade.',
            'quantity.min' => 'Quantidade deve ser pelo menos 1.',
            'unit_cost.min' => 'O custo unitário não pode ser negativo.',
        ];
    }
}
