<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhmcsImportConnection;
use App\Models\WhmcsImportLog;
use App\Models\WhmcsImportProfile;
use App\Services\WhmcsImport\ClientImporter;
use App\Services\WhmcsImport\ImportValidator;
use App\Services\WhmcsImport\MappingEngine;
use App\Services\WhmcsImport\SchemaReader;
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

        $connector = $data['connector'];
        $sourceTable = $data['sourceTable'];
        $mapping = $data['mapping'];

        $data['errors'] = $this->validateMapping($data, $mapping, $request->input('match_key') ?: 'email', $request->input('import_mode', 'add'));

        $rows = $connector->rows($sourceTable, 10);
        $connector->enrichRows($rows, $data['prefix'], $data['customFields']);
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

        $mapping = $data['mapping'];
        $matchKey = $request->input('match_key') ?: 'email';
        $importMode = $request->input('import_mode', 'add');

        $errors = $this->validateMapping($data, $mapping, $matchKey, $importMode);
        if ($errors !== []) {
            $data['errors'] = $errors;

            return view('admin.whmcs-import.mapper', $data)->with('error', __('whmcs_import.validation.fix_errors'));
        }

        $summary = $this->importer->run(
            fn ($callback) => $data['connector']->eachEnriched($data['sourceTable'], $data['prefix'], $data['customFields'], 500, $callback),
            $mapping,
            $importMode,
            $matchKey,
        );

        $log = WhmcsImportLog::create([
            'source' => 'WHMCS',
            'source_table' => $data['sourceTable'],
            'total' => $summary['total'],
            'added' => $summary['added'],
            'updated' => $summary['updated'],
            'skipped' => $summary['skipped'],
            'errors' => $summary['errors'],
            'error_details' => $summary['error_details'],
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

        WhmcsImportProfile::create([
            'connection_id' => $connection->id,
            'name' => $request->string('profile_name')->toString(),
            'source_table' => $request->input('source_table', $connection->prefix.'clients'),
            'target' => 'clients',
            'mapping' => $mapping['columns'],
            'constants' => $mapping['constants'],
            'match_key' => $request->input('match_key') ?: 'email',
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

        $sourceColumns = $connector->columns($sourceTable);
        $targetFields = $this->schema->clientTargetFields();

        // WHMCS keeps PESEL/NIP & co. in custom fields, not in tblclients. They
        // join the source list under `custom:{name}` so the operator can map
        // them exactly like a real column.
        $customFields = [];
        if ($sourceTable === $connection->prefix.'clients') {
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
                if (in_array('custom_field:'.$name, $targetFields, true)) {
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

        return [
            'connection' => $connection,
            'connector' => $connector,
            'tables' => $tables,
            'sourceTable' => $sourceTable,
            'sourceColumns' => $sourceColumns,
            'customFields' => $customFields,
            'prefix' => $connection->prefix,
            'targetFields' => $targetFields,
            'suggestions' => $suggestions,
            'profiles' => WhmcsImportProfile::orderBy('name')->get(),
            'profile' => $profile,
            'mapping' => $mapping,
            'selected' => $selected,
            'matchKey' => $request->input('match_key') ?: ($profile?->match_key ?: 'email'),
            'importMode' => $request->input('import_mode', $profile?->import_mode ?? 'add'),
            'totalCount' => $connector->count($sourceTable),
            'errors' => [],
            'preview' => null,
        ];
    }

    /** @return array{columns: array<string, string>, constants: array<string, string>} */
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

        return ['columns' => $columns, 'constants' => $constants];
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
            ];
        }

        return ['columns' => [], 'constants' => []];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{columns: array<string, string>, constants: array<string, string>}  $mapping
     * @return list<string>
     */
    protected function validateMapping(array $data, array $mapping, ?string $matchKey, string $importMode): array
    {
        $sourceColumns = array_column($data['sourceColumns'], 'name');

        return $this->validator->mapping($mapping, $data['targetFields'], $sourceColumns, $matchKey, $importMode);
    }

    /** @return array<string, mixed> */
    protected function connectionRules(Request $request): array
    {
        return $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'prefix' => 'nullable|string|max:64',
        ]);
    }
}
