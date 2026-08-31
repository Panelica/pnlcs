<?php

namespace Modules\CompanyLookup;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\CompanyLookup\Contracts\CompanyDataProviderInterface;
use Modules\CompanyLookup\Exceptions\ProviderException;
use Modules\CompanyLookup\Support\Nip;

/**
 * Orchestrates the four registries and hands back one normalised answer.
 *
 * Identification (name / NIP / REGON / address / legal form / PKD) prefers
 * GUS → CEIDG → MF → OpenBRIS; VAT status and bank accounts come from MF;
 * business status and activity dates come from CEIDG. Any source can fail
 * without sinking the whole lookup — a partial result ships with a warning,
 * and per-field discrepancies are surfaced instead of silently discarded.
 */
final class CompanyLookupService
{
    public function __construct(
        private readonly CompanyDataProviderInterface $gus,
        private readonly CompanyDataProviderInterface $mf,
        private readonly CompanyDataProviderInterface $ceidg,
        private readonly CompanyDataProviderInterface $openbris,
        private readonly DataNormalizer $normalizer,
        private readonly int $cacheTtl,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $rawNip): array
    {
        $startedAt = microtime(true);

        $nip = Nip::normalize($rawNip);
        if (! Nip::isValidDigits($nip)) {
            return $this->failed('INVALID_NIP', $nip, $startedAt);
        }

        $cacheKey = 'company_lookup:'.$nip;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $sources = ['gus' => false, 'mf' => false, 'ceidg' => false, 'openbris' => false];
        $warnings = [];

        $gus = $this->call('gus', $this->gus, $nip, $sources, $warnings);
        $mf = $this->call('mf', $this->mf, $nip, $sources, $warnings);
        $ceidg = $this->call('ceidg', $this->ceidg, $nip, $sources, $warnings);
        $openbris = $this->call('openbris', $this->openbris, $nip, $sources, $warnings);

        $normalized = $this->normalizer->normalize($gus, $ceidg, $mf, $openbris);
        $company = $normalized['company'];

        // Discrepancy warnings are structured objects; append them to the
        // textual provider warnings.
        $warnings = array_merge($warnings, $normalized['warnings']);

        if (! $company->hasAnyData()) {
            // At least one source answered but knew nothing → genuinely absent.
            // No source answered at all → a failure, not a "not found".
            $code = ($sources['gus'] || $sources['mf'] || $sources['ceidg'] || $sources['openbris'])
                ? 'COMPANY_NOT_FOUND'
                : 'UNKNOWN_ERROR';

            return $this->failed($code, $nip, $startedAt, $sources, $warnings);
        }

        $result = [
            'success' => true,
            'company' => $company->toArray(),
            'sources' => $sources,
            'warnings' => $warnings,
        ];

        if ($this->cacheTtl > 0) {
            Cache::put($cacheKey, $result, $this->cacheTtl);
        }

        $this->logSuccess($nip, $sources, $startedAt);

        return $result;
    }

    /**
     * Call one provider, converting failures into per-source flags/warnings.
     *
     * NOT_CONFIGURED is skipped silently (an optional source with no key yet);
     * any other failure produces a textual warning. Returns the CompanyData or
     * null when the source found nothing.
     *
     * @param  array{gus: bool, mf: bool, ceidg: bool, openbris: bool}  $sources
     * @param  array<int, string|array<string, mixed>>  $warnings
     */
    private function call(
        string $name,
        CompanyDataProviderInterface $provider,
        string $nip,
        array &$sources,
        array &$warnings,
    ): ?CompanyData {
        try {
            $data = $provider->findByNip($nip);
            $sources[$name] = true;

            return $data;
        } catch (ProviderException $e) {
            $sources[$name] = false;

            if ($e->codeName() === ProviderException::NOT_CONFIGURED) {
                return null;
            }

            $warnings[] = __('messages.company_lookup.'.$name.'_unavailable');
            $this->logProviderFailure($name, $e);

            return null;
        }
    }

    /**
     * @param  array{gus: bool, mf: bool, ceidg: bool, openbris: bool}  $sources
     * @param  array<int, string|array<string, mixed>>  $warnings
     * @return array<string, mixed>
     */
    private function failed(
        string $code,
        string $nip,
        float $startedAt,
        array $sources = ['gus' => false, 'mf' => false, 'ceidg' => false, 'openbris' => false],
        array $warnings = [],
    ): array {
        Log::channel('daily')->warning('Company lookup failed', [
            'nip' => $nip,
            'code' => $code,
            'sources' => $sources,
            'duration_ms' => $this->elapsed($startedAt),
        ]);

        return [
            'success' => false,
            'error' => $code,
            'company' => null,
            'sources' => $sources,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array{gus: bool, mf: bool, ceidg: bool, openbris: bool}  $sources
     */
    private function logSuccess(string $nip, array $sources, float $startedAt): void
    {
        Log::channel('daily')->info('Company lookup ok', [
            'nip' => $nip,
            'sources' => $sources,
            'duration_ms' => $this->elapsed($startedAt),
        ]);
    }

    private function logProviderFailure(string $source, ProviderException $e): void
    {
        Log::channel('daily')->warning("Company lookup provider [{$source}] failed", [
            'code' => $e->codeName(),
            'message' => $e->getMessage(),
        ]);
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
