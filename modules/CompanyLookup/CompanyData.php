<?php

namespace Modules\CompanyLookup;

/**
 * Normalised view of a Polish company, assembled from GUS REGON, the MF
 * "Biała Lista" register and CEIDG. Raw provider responses are never passed
 * through to the frontend; only this shape leaves the module.
 */
final class CompanyData
{
    public function __construct(
        public ?string $name = null,
        public ?string $nip = null,
        public ?string $regon = null,
        public ?string $street = null,
        public ?string $buildingNumber = null,
        public ?string $apartmentNumber = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $voivodeship = null,
        public ?string $country = 'PL',
        public ?string $legalForm = null,
        public ?string $vatStatus = null,
        public ?string $vatRegistrationDate = null,
        public array $bankAccounts = [],
        public array $pkd = [],
        public ?string $businessStatus = null,
        public ?string $activityStartDate = null,
        public ?string $activityEndDate = null,
        public ?string $suspensionStartDate = null,
        public ?string $suspensionEndDate = null,
    ) {
    }

    /**
     * The JSON shape the endpoint returns under the `company` key.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'nip' => $this->nip,
            'regon' => $this->regon,
            'address' => [
                'street' => $this->street,
                'building_number' => $this->buildingNumber,
                'apartment_number' => $this->apartmentNumber,
                'postal_code' => $this->postalCode,
                'city' => $this->city,
                'voivodeship' => $this->voivodeship,
            ],
            'legal_form' => $this->legalForm,
            'vat' => [
                'status' => $this->vatStatus,
                'registration_date' => $this->vatRegistrationDate,
            ],
            'bank_accounts' => array_values($this->bankAccounts),
            'pkd' => array_values($this->pkd),
            'business_status' => $this->businessStatus,
            'activity_start_date' => $this->activityStartDate,
            'activity_end_date' => $this->activityEndDate,
            'suspension_start_date' => $this->suspensionStartDate,
            'suspension_end_date' => $this->suspensionEndDate,
        ];
    }

    /**
     * True when the record carries at least one identifying field.
     */
    public function hasAnyData(): bool
    {
        return $this->name !== null
            || $this->nip !== null
            || $this->regon !== null
            || $this->businessStatus !== null;
    }
}
