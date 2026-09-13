<?php

namespace App\Http\Requests;

use App\Actions\Reporting\ReportingPeriod;
use App\StaffPermission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OperationalReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(StaffPermission::ReportsOperationalView) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function reportingPeriod(): ReportingPeriod
    {
        $timezone = config('app.timezone');
        $timezone = is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
        $today = CarbonImmutable::now($timezone);
        $dateFrom = $this->validated('date_from');
        $dateTo = $this->validated('date_to');

        return new ReportingPeriod(
            is_string($dateFrom) ? CarbonImmutable::parse($dateFrom, $timezone) : $today->startOfMonth(),
            is_string($dateTo) ? CarbonImmutable::parse($dateTo, $timezone) : $today->endOfMonth(),
        );
    }
}
