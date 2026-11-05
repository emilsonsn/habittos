<?php

namespace App\Services;

use App\Enums\EntryType;
use Illuminate\Validation\Rule;

class EntryRules
{
    public static function forWorkspace(int $workspaceId, mixed $type = null): array
    {
        return [
            'description' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::enum(EntryType::class)],
            'amount' => ['bail', 'required', 'numeric', 'decimal:0,2', $type === EntryType::Investment->value ? 'min:-999999999.99' : 'min:0.01', 'max:999999999.99', function ($attribute, $value, $fail) {
                if ((float) $value === 0.0) {
                    $fail('Informe um valor diferente de zero.');
                }
            }],
            'date' => ['required', 'date_format:Y-m-d'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('workspace_id', $workspaceId)],
            'paid' => ['sometimes', 'boolean'],
            'is_fixed' => ['sometimes', 'boolean'],
            'is_installment' => ['sometimes', 'boolean'],
            'counts_toward_weekly_spending' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
