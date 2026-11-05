<?php

namespace App\Http\Requests;

use App\Enums\EntryType;
use App\Services\EntryRules;
use App\Services\Money;
use Illuminate\Foundation\Http\FormRequest;

class EntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('POST')) {
            $this->mergeIfMissing([
                'type' => EntryType::Expense->value,
                'date' => now()->toDateString(),
                'paid' => true,
                'is_fixed' => false,
                'is_installment' => false,
                'counts_toward_weekly_spending' => true,
                'notes' => null,
            ]);
        }
    }

    public function rules(): array
    {
        return EntryRules::forWorkspace($this->route('workspace')->id, $this->input('type'));
    }

    public function entryData(): array
    {
        $data = $this->safe()->except('amount');
        $data['amount_cents'] = Money::cents($this->validated('amount'));
        $data['paid'] = $this->boolean('paid');
        $data['is_fixed'] = $this->boolean('is_fixed');

        return $data;
    }
}
