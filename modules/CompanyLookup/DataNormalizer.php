<?php

namespace Modules\CompanyLookup;

/**
 * Merges the three provider results into one CompanyData using per-field
 * priorities, and flags discrepancies (same field, different non-null values
 * from different sources) instead of silently discarding them.
 */
final class DataNormalizer
{
    /**
     * @return array{company: CompanyData, warnings: array<int, array<string, mixed>>}
     */
    public function normalize(?CompanyData $gus, ?CompanyData $ceidg, ?CompanyData $mf, ?CompanyData $openbris = null): array
    {
        $warnings = [];
        $c = new CompanyData();

        // Identification / address: GUS → CEIDG → MF → OpenBRIS (last resort).
        $id = ['gus' => $gus, 'ceidg' => $ceidg, 'mf' => $mf, 'openbris' => $openbris];

        $c->name = $this->pick($id, 'name', $warnings);
        $c->nip = $this->pick($id, 'nip', $warnings);
        $c->regon = $this->pick($id, 'regon', $warnings);
        $c->street = $this->pick($id, 'street', $warnings);
        $c->buildingNumber = $this->pick($id, 'buildingNumber', $warnings);
        $c->apartmentNumber = $this->pick($id, 'apartmentNumber', $warnings);
        $c->postalCode = $this->pick($id, 'postalCode', $warnings);
        $c->city = $this->pick($id, 'city', $warnings);
        $c->voivodeship = $this->pick($id, 'voivodeship', $warnings);
        $c->country = $this->pick($id, 'country', $warnings);

        // Legal form: GUS → CEIDG.
        $c->legalForm = $this->pick(['gus' => $gus, 'ceidg' => $ceidg], 'legalForm', $warnings);

        // PKD: GUS → CEIDG.
        $c->pkd = $this->pickArray(['gus' => $gus, 'ceidg' => $ceidg], 'pkd', $warnings);

        // VAT status + registration date + bank accounts: MF only.
        if ($mf !== null) {
            $c->vatStatus = $mf->vatStatus;
            $c->vatRegistrationDate = $mf->vatRegistrationDate;
            $c->bankAccounts = $mf->bankAccounts;
        }

        // Business status and activity/suspension dates: CEIDG, when present.
        if ($ceidg !== null) {
            $c->businessStatus = $ceidg->businessStatus;
            $c->activityStartDate = $ceidg->activityStartDate;
            $c->activityEndDate = $ceidg->activityEndDate;
            $c->suspensionStartDate = $ceidg->suspensionStartDate;
            $c->suspensionEndDate = $ceidg->suspensionEndDate;
        }

        return ['company' => $c, 'warnings' => $warnings];
    }

    /**
     * First non-null scalar value by priority; warn when sources disagree.
     *
     * @param  array<string, ?CompanyData>  $providers
     * @param  array<int, array<string, mixed>>  $warnings
     */
    private function pick(array $providers, string $field, array &$warnings): mixed
    {
        $first = null;
        $seen = [];

        foreach ($providers as $source => $provider) {
            if ($provider === null) {
                continue;
            }

            $value = $provider->{$field};
            if ($value === null || $value === '') {
                continue;
            }

            $seen[$source] = $value;
            if ($first === null) {
                $first = $value;
            }
        }

        if (count(array_unique($seen)) > 1) {
            $warnings[] = ['field' => $field, 'sources' => $seen];
        }

        return $first;
    }

    /**
     * First non-empty array by priority; warn when the arrays differ.
     *
     * @param  array<string, ?CompanyData>  $providers
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array<int, string>
     */
    private function pickArray(array $providers, string $field, array &$warnings): array
    {
        $first = [];
        $seen = [];

        foreach ($providers as $source => $provider) {
            if ($provider === null) {
                continue;
            }

            $value = array_values(array_filter((array) $provider->{$field}));
            if ($value === []) {
                continue;
            }

            $seen[$source] = $value;
            if ($first === []) {
                $first = $value;
            }
        }

        $unique = array_map('json_encode', $seen);
        if (count(array_unique($unique)) > 1) {
            $warnings[] = ['field' => $field, 'sources' => $seen];
        }

        return $first;
    }
}
