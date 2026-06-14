<?php

namespace App\Http\Requests;

use App\Models\ReadingPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscribeReadingPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $plan = $this->route('plan');

        return $user !== null
            && $plan instanceof ReadingPlan
            && $plan->isAvailableTo($user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $plan = $this->route('plan');
        $planDays = $plan instanceof ReadingPlan
            ? $plan->getOrderedDayNumbers()
            : [];

        return [
            'start_day' => ['required', 'integer', Rule::in($planDays)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('start_day')) {
            $plan = $this->route('plan');
            $startDay = $plan instanceof ReadingPlan
                ? $plan->getFirstDayNumber()
                : 1;

            $this->merge(['start_day' => $startDay]);
        }
    }
}
