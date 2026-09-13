<?php

namespace App\Http\Requests;

use App\Actions\Reporting\ReportingPeriod;
use App\StaffPermission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ManagementSummaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(StaffPermission::ReportsManagementView) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function reportingPeriod(): ReportingPeriod
    {
        $timezone = config('app.timezone');
        $timezone = is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
        $today = CarbonImmutable::now($timezone);
        $from = $this->validated('from');
        $to = $this->validated('to');

        return new ReportingPeriod(
            is_string($from) ? CarbonImmutable::parse($from, $timezone) : $today->startOfMonth(),
            is_string($to) ? CarbonImmutable::parse($to, $timezone) : $today->endOfMonth(),
        );
    }
}
