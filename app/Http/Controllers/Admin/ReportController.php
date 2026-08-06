<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportManager $manager) {}

    public function index(Request $request)
    {
        $reports = $this->manager->all();
        $categories = $this->manager->categories();
        $selectedCategory = $request->input('category');

        if ($selectedCategory) {
            $reports = $reports->filter(fn ($items, $cat) => $cat === $selectedCategory);
        }

        return view('admin.reports.index', compact('reports', 'categories', 'selectedCategory'));
    }

    public function show(string $slug, Request $request)
    {
        $report = $this->manager->find($slug);

        if (! $report) {
            return back()->with('error', __('admin.messages.report_not_found'));
        }

        try {
            $data = $report->generate($request);
        } catch (\Throwable $e) {
            $data = ['columns' => ['Error'], 'rows' => [(object) ['error' => $e->getMessage()]]];
        }

        return view('admin.reports.show', [
            'report' => $report,
            'title' => $report->getTitle(),
            'description' => $report->getDescription(),
            'columns' => $data['columns'] ?? [],
            'rows' => $data['rows'] ?? [],
            'totals' => $data['totals'] ?? null,
            'hasDateFilter' => $report->hasDateFilter(),
            'canExport' => $report->canExport(),
            'from' => $request->input('from', now()->startOfMonth()->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
            'year' => $request->input('year', now()->year),
            'month' => $request->input('month', now()->month),
        ]);
    }

    public function export(string $slug, Request $request): StreamedResponse
    {
        $report = $this->manager->find($slug);

        if (! $report || ! $report->canExport()) {
            abort(404);
        }

        $data = $report->generate($request);
        $columns = $data['columns'] ?? [];
        $rows = $data['rows'] ?? [];

        return response()->streamDownload(function () use ($columns, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_map('csv_cell', $columns));
            foreach ($rows as $row) {
                $rowArray = is_object($row) ? (array) $row : $row;
                fputcsv($handle, array_map('csv_cell', array_values($rowArray)));
            }
            fclose($handle);
        }, $slug.'-'.date('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
