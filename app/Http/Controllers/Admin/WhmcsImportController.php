<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\WhmcsImportConnection;
use App\Models\WhmcsImportLog;
use App\Models\WhmcsImportProfile;
use App\Services\WhmcsImport\ClientImporter;
use App\Services\WhmcsImport\DomainImporter;
use App\Services\WhmcsImport\ImportValidator;
use App\Services\WhmcsImport\MappingEngine;
use App\Services\WhmcsImport\SchemaReader;
use App\Services\WhmcsImport\ServiceImporter;
use App\Services\WhmcsImport\WhmcsConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WhmcsImportController extends Controller
{
    public function __construct(
        protected SchemaReader $schema,
        protected MappingEngine $engine,
        protected ImportValidator $validator,
        protected ClientImporter $importer,
        protected DomainImporter $domainImporter,
        protected ServiceImporter $serviceImporter,
    ) {}

    public function index()
    {
        return view('admin.whmcs-import.index', [
            'connections' => WhmcsImportConnection::orderBy('id')->get(),
            'profiles' => WhmcsImportProfile::orderBy('name')->get(),
            'logs' => WhmcsImportLog::orderByDesc('id')->limit(20)->get(),
        ]);
    }

    /** Check the credentials without saving anything. */
    public function testConnection(Request $request)
    {
        $config = $this->connectionRules($request);

        try {
            $connector = new WhmcsConnector($config);
            $connector->test();
            $tables = count($connector->tables());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->withInput()->with('success', __('whmcs_import.connection_ok', ['tables' => $tables]));
    }

    /** Save the connection and carry on to the mapper. */
    public function saveConnection(Request $request)
    {
        $validated = $this->connectionRules($request);
        $validated['name'] = $request->filled('name') ? $request->string('name')->toString() : null;

        try {
            $connector = new WhmcsConnector($validated);
            $connector->test();
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['connection' => $e->getMessage()]);
        }

        $validated['prefix'] = $validated['prefix'] ?: 'tbl';
        $connection = WhmcsImportConnection::create($validated);

        return redirect()->route('admin.whmcs-import.mapper', $connection)
            ->with('success', __('whmcs_import.connection_saved'));
    }

    public function mapper(Request $request, WhmcsImportConnection $connection)
    {
        $data = $this->mapperData($request, $connection);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return view('admin.whmcs-import.mapper', $data);
    }

    public function preview(Request $request, WhmcsImportConnection $connection)
    {
        $data = $this->mapperData($request, $connection);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $config = $this->targetConfig($data['target']);
        $mapping = $data['mapping'];
        $matchKey = $this->resolveMatchKey($request->input('match_key'), $config);

        $data['errors'] = $this->validateMapping($data, $mapping, $matchKey, $request->input('import_mode', 'add'), $config['required_fields']);

        $rows = $data['connector']->rows($data['sourceTable'], 10);
        $this->enrichRows($data, $rows);
        $data['preview'] = array_map(function (array $row) use ($mapping) {
            return [
                'source' => $row,
                'target' => $this->engine->apply($row, $mapping),
            ];
        }, $rows);

        return view('admin.whmcs-import.mapper', $data);
    }

    public function import(Request $request, WhmcsImportConnection $connection)
    {
        $data = $this->mapperData($request, $connection);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $config = $this->targetConfig($data['target']);
        $mapping = $data['mapping'];
        $matchKey = $this->resolveMatchKey($request->input('match_key'), $config);
        $importMode = $request->input('import_mode', 'add');

        $errors = $this->validateMapping($data, $mapping, $matchKey, $importMode, $config['required_fields']);
        if ($errors !== []) {
            $data['errors'] = $errors;

            return view('admin.whmcs-import.mapper', $data)->with('error', __('whmcs_import.validation.fix_errors'));
        }

        if ($data['target'] === 'domains') {
            $summary = $this->domainImporter->run(
                fn ($callback) => $data['connector']->eachWithClientEmail($data['sourceTable'], $data['prefix'], 500, $callback),
                $mapping,
                $importMode,
                $matchKey,
            );
        } elseif ($data['target'] === 'services') {
            $summary = $this->serviceImporter->run(
                fn ($callback) => $data['connector']->eachForServices($data['sourceTable'], $data['prefix'], 500, $callback),
                $mapping,
                $importMode,
                $matchKey,
                $this->parseProductMapping($request),
            );
        } else {
            $summary = $this->importer->run(
                fn ($callback) => $data['connector']->eachEnriched($data['sourceTable'], $data['prefix'], $data['customFields'], 500, $callback),
                $mapping,
                $importMode,
                $matchKey,
            );
        }

        $log = WhmcsImportLog::create([
            'source' => 'WHMCS',
            'source_table' => $data['sourceTable'],
            'total' => $summary['total'],
            'added' => $summary['added'],
            'updated' => $summary['updated'],
            'skipped' => $summary['skipped'],
            'errors' => $summary['errors'],
            'error_details' => $summary['error_details'],
            'skipped_details' => $summary['skipped_details'],
            'admin_id' => auth('admin')->id(),
        ]);

        return redirect()->route('admin.whmcs-import.log.show', $log)->with('success', __('whmcs_import.import_done'));
    }

    public function showLog(WhmcsImportLog $log)
    {
        return view('admin.whmcs-import.log', compact('log'));
    }

    public function saveProfile(Request $request, WhmcsImportConnection $connection)
    {
        $request->validate(['profile_name' => 'required|string|max:255']);

        $mapping = $this->parseMapping($request);
        $sourceTable = $request->input('source_table', $connection->prefix.'clients');

        WhmcsImportProfile::create([
            'connection_id' => $connection->id,
            'name' => $request->string('profile_name')->toString(),
            'source_table' => $sourceTable,
            'target' => $this->targetForTable($sourceTable, $connection->prefix),
            'mapping' => $mapping['columns'],
            'constants' => $mapping['constants'],
            'transforms' => $mapping['transforms'],
            'product_mapping' => $this->parseProductMapping($request),
            'match_key' => $request->input('match_key') ?: null,
            'import_mode' => $request->input('import_mode', 'add'),
        ]);

        return redirect()->route('admin.whmcs-import.mapper', $connection)
            ->with('success', __('whmcs_import.profile_saved'));
    }

    public function deleteProfile(WhmcsImportProfile $profile)
    {
        $profile->delete();

        return back()->with('success', __('whmcs_import.profile_deleted'));
    }

    /**
     * Everything the mapper screen needs, or a redirect when the source is unreachable.
     *
     * @return array<string, mixed>|RedirectResponse
     */
    protected function mapperData(Request $request, WhmcsImportConnection $connection)
    {
        try {
            $connector = new WhmcsConnector($connection->config());
            $connector->test();
            $tables = $connector->tables();
        } catch (\Throwable $e) {
            return redirect()->route('admin.whmcs-import.index')->with('error', $e->getMessage());
        }

        $defaultTable = $connection->prefix.'clients';
        $sourceTable = $request->input('source_table', $request->get('table', $defaultTable));
        if (! in_array($sourceTable, $tables, true)) {
            $sourceTable = $defaultTable;
        }
        if (! in_array($sourceTable, $tables, true)) {
            $sourceTable = $tables[0] ?? '';
        }

        $target = $this->targetForTable($sourceTable, $connection->prefix);
        $config = $this->targetConfig($target);

        $sourceColumns = $connector->columns($sourceTable);

        // WHMCS keeps PESEL/NIP & co. in custom fields, not in tblclients. They
        // join the source list under `custom:{name}` so the operator can map
        // them exactly like a real column.
        $customFields = [];
        if ($target === 'clients') {
            $customFields = $this->schema->whmcsCustomFields($connector, $connection->prefix);
            foreach ($customFields as $field) {
                $sourceColumns[] = ['name' => 'custom:'.$field['name'], 'type' => 'custom field'];
            }
        }

        $profile = null;
        if ($request->filled('profile')) {
            $profile = WhmcsImportProfile::where('connection_id', $connection->id)
                ->find($request->integer('profile'));
        }

        $suggestions = [];
        foreach ($sourceColumns as $column) {
            $suggestion = $this->engine->suggest($column['name']);

            // A WHMCS custom field with the same name as a PNLCS custom field
            // (e.g. CSA) maps onto it; nothing else can guess that here.
            if ($suggestion === null && str_starts_with($column['name'], 'custom:')) {
                $name = substr($column['name'], strlen('custom:'));
                if (in_array('custom_field:'.$name, $config['target_fields'], true)) {
                    $suggestion = 'custom_field:'.$name;
                }
            }

            $suggestions[$column['name']] = $suggestion;
        }

        $mapping = $this->mappingFromRequest($request, $profile);

        // The dropdown default for each source, keeping an explicit "Skip" on
        // re-render (the parsed mapping drops skips, the engine must not).
        $selected = [];
        foreach ($sourceColumns as $column) {
            $name = $column['name'];
            if ($request->isMethod('post')) {
                $selected[$name] = $request->input('mapping.'.$name, '__skip__');
            } elseif ($profile && array_key_exists($name, $profile->mapping ?? [])) {
                $selected[$name] = $profile->mapping[$name];
            } else {
                $selected[$name] = $suggestions[$name] ?? '__skip__';
            }
        }

        // The services mapper may map WHMCS products to PNLCS products by name.
        $whmcsProducts = [];
        $pnlcsProducts = collect();
        if ($target === 'services') {
            try {
                $whmcsProducts = $connector->products($connection->prefix);
            } catch (\Throwable) {
                $whmcsProducts = [];
            }
            $pnlcsProducts = Product::orderBy('name')->get(['id', 'name']);
        }

        return [
            'connection' => $connection,
            'connector' => $connector,
            'tables' => $tables,
            'sourceTable' => $sourceTable,
            'target' => $target,
            'sourceColumns' => $sourceColumns,
            'customFields' => $customFields,
            'prefix' => $connection->prefix,
            'targetFields' => $config['target_fields'],
            'suggestions' => $suggestions,
            'profiles' => WhmcsImportProfile::orderBy('name')->get(),
            'profile' => $profile,
            'mapping' => $mapping,
            'selected' => $selected,
            'whmcsProducts' => $whmcsProducts,
            'pnlcsProducts' => $pnlcsProducts,
            'productMap' => $this->productMapFromRequest($request, $profile),
            'matchKey' => $this->resolveMatchKey($request->input('match_key'), $config, $profile?->match_key),
            'importMode' => $request->input('import_mode', $profile?->import_mode ?? 'add'),
            'totalCount' => $connector->count($sourceTable),
            'errors' => [],
            'preview' => null,
        ];
    }

    /** @return 'clients'|'domains'|'services' */
    protected function targetForTable(string $sourceTable, string $prefix): string
    {
        $table = str_starts_with($sourceTable, $prefix) ? substr($sourceTable, strlen($prefix)) : $sourceTable;

        return match ($table) {
            'domains' => 'domains',
            'hosting' => 'services',
            default => 'clients',
        };
    }

    /**
     * The match key for the current target, resetting a stale value from the
     * other target (e.g. `email` carried over from the clients mapper when the
     * source table is switched to domains).
     */
    protected function resolveMatchKey(?string $key, array $config, ?string $profileKey = null): string
    {
        $key = $key ?: ($profileKey ?: $config['default_match_key']);

        return in_array($key, $config['target_fields'], true) ? $key : $config['default_match_key'];
    }

    /**
     * @return array{target_fields: list<string>, required_fields: list<string>, default_match_key: string}
     */
    protected function targetConfig(string $target): array
    {
        if ($target === 'domains') {
            return [
                'target_fields' => $this->schema->domainTargetFields(),
                'required_fields' => ['domain'],
                'default_match_key' => 'domain',
            ];
        }

        if ($target === 'services') {
            return [
                'target_fields' => $this->schema->serviceTargetFields(),
                'required_fields' => [],
                'default_match_key' => 'domain',
            ];
        }

        return [
            'target_fields' => $this->schema->clientTargetFields(),
            'required_fields' => ['first_name', 'last_name', 'email'],
            'default_match_key' => 'email',
        ];
    }

    /**
     * Attach the target-specific extra data to a set of source rows.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $rows
     */
    protected function enrichRows(array $data, array &$rows): void
    {
        if ($data['target'] === 'domains') {
            $data['connector']->enrichWithClientEmail($rows, $data['prefix']);

            return;
        }

        if ($data['target'] === 'services') {
            $data['connector']->enrichWithServiceReferences($rows, $data['prefix']);

            return;
        }

        $data['connector']->enrichRows($rows, $data['prefix'], $data['customFields']);
    }

    /** @return array{columns: array<string, string>, constants: array<string, string>, transforms: array<string, array{pattern: string, replacement: string}>} */
    protected function parseMapping(Request $request): array
    {
        $columns = [];
        foreach ($request->input('mapping', []) as $source => $target) {
            if (is_string($target) && $target !== '' && $target !== '__skip__') {
                $columns[$source] = $target;
            }
        }

        $constants = [];
        foreach ($request->input('constants', []) as $field => $value) {
            if (is_string($value) && trim($value) !== '') {
                $constants[$field] = trim($value);
            }
        }

        $transforms = [];
        foreach ($request->input('transforms', []) as $field => $transform) {
            $pattern = trim((string) ($transform['pattern'] ?? ''));
            if ($pattern === '') {
                continue;
            }
            $transforms[$field] = [
                'pattern' => $pattern,
                'replacement' => (string) ($transform['replacement'] ?? ''),
            ];
        }

        return ['columns' => $columns, 'constants' => $constants, 'transforms' => $transforms];
    }

    /** Mapping from the request, falling back to a loaded profile. */
    protected function mappingFromRequest(Request $request, ?WhmcsImportProfile $profile): array
    {
        if ($request->isMethod('post')) {
            return $this->parseMapping($request);
        }

        if ($profile) {
            return [
                'columns' => $profile->mapping ?? [],
                'constants' => $profile->constants ?? [],
                'transforms' => $profile->transforms ?? [],
            ];
        }

        return ['columns' => [], 'constants' => [], 'transforms' => []];
    }

    /**
     * The WHMCS product name → PNLCS product id map, from the request or a
     * saved profile. An empty value means "leave unmatched" and is dropped.
     *
     * @return array<string, int>
     */
    protected function productMapFromRequest(Request $request, ?WhmcsImportProfile $profile): array
    {
        if ($request->isMethod('post')) {
            return $this->parseProductMapping($request);
        }

        return $profile?->product_mapping ?? [];
    }

    /**
     * @return array<string, int>
     */
    protected function parseProductMapping(Request $request): array
    {
        $map = [];
        foreach ($request->input('product_mapping', []) as $name => $id) {
            if (is_string($id) && $id !== '') {
                $map[(string) $name] = (int) $id;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{columns: array<string, string>, constants: array<string, string>}  $mapping
     * @param  list<string>  $requiredFields
     * @return list<string>
     */
    protected function validateMapping(array $data, array $mapping, ?string $matchKey, string $importMode, array $requiredFields = ['first_name', 'last_name', 'email']): array
    {
        $sourceColumns = array_column($data['sourceColumns'], 'name');

        return $this->validator->mapping($mapping, $data['targetFields'], $sourceColumns, $matchKey, $importMode, $requiredFields);
    }

    /** @return array<string, mixed> */
    protected function connectionRules(Request $request): array
    {
        return $request->validate([
            // Letters, digits, dots and dashes only: a ';' would add parameters
            // to the connection string (unix_socket=, and so on).
            'host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            'port' => 'required|integer|min:1|max:65535',
            'database' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_$-]+$/'],
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'prefix' => 'nullable|string|max:64',
        ]);
    }
}
