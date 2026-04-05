<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index()
    {
        $events = CalendarEvent::orderBy("start")->get();
        return view("admin.calendar.index", compact("events"));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "title" => "required|string|max:255",
            "description" => "nullable|string",
            "start" => "required|date",
            "end" => "nullable|date|after_or_equal:start",
            "recurring" => "boolean",
        ]);

        $validated["recurring"] = $request->boolean("recurring");
        $validated["admin"] = auth("admin")->user()->full_name ?? "Admin";

        CalendarEvent::create($validated);
        return back()->with("success", "Event created.");
    }

    public function update(Request $request, CalendarEvent $event)
    {
        $validated = $request->validate([
            "title" => "required|string|max:255",
            "description" => "nullable|string",
            "start" => "required|date",
            "end" => "nullable|date|after_or_equal:start",
            "recurring" => "boolean",
        ]);

        $validated["recurring"] = $request->boolean("recurring");
        $event->update($validated);
        return back()->with("success", "Event updated.");
    }

    public function destroy(CalendarEvent $event)
    {
        $event->delete();
        return back()->with("success", "Event deleted.");
    }

    public function apiEvents(Request $request)
    {
        $events = CalendarEvent::when($request->start, fn($q) => $q->where("start", ">=", $request->start))
            ->when($request->end, fn($q) => $q->where("start", "<=", $request->end))
            ->get()
            ->map(fn($e) => [
                "id" => $e->id,
                "title" => $e->title,
                "start" => $e->start?->toIso8601String(),
                "end" => $e->end?->toIso8601String(),
                "allDay" => true,
                "description" => $e->description,
                "admin" => $e->admin,
            ]);

        return response()->json($events);
    }
}
