<?php

namespace App\Actions\Patients;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FindPossiblePatientDuplicates
{
    /**
     * @param  array{first_name: string|null, last_name: string|null, date_of_birth: string|null, phone: string|null, email: string|null}  $attributes
     * @return Collection<int, Patient>
     */
    public function handle(array $attributes): Collection
    {
        $hasNameAndDateOfBirth = filled($attributes['first_name'])
            && filled($attributes['last_name'])
            && filled($attributes['date_of_birth']);

        if (! filled($attributes['phone'])
            && ! filled($attributes['email'])
            && ! $hasNameAndDateOfBirth) {
            return new Collection;
        }

        return Patient::query()
            ->select([
                'id',
                'patient_number',
                'first_name',
                'middle_name',
                'last_name',
                'date_of_birth',
                'phone',
                'email',
            ])
            ->where(function (Builder $query) use ($attributes, $hasNameAndDateOfBirth): void {
                if (filled($attributes['phone'])) {
                    $query->orWhere('phone', $attributes['phone']);
                }

                if (filled($attributes['email'])) {
                    $query->orWhere('email', $attributes['email']);
                }

                if ($hasNameAndDateOfBirth) {
                    $query->orWhere(function (Builder $nameQuery) use ($attributes): void {
                        $nameQuery
                            ->where('first_name', $attributes['first_name'])
                            ->where('last_name', $attributes['last_name'])
                            ->whereDate('date_of_birth', $attributes['date_of_birth']);
                    });
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->limit(10)
            ->get();
    }
}
