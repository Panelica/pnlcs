<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketPredefinedCategory;
use App\Models\TicketPredefinedReply;
use Illuminate\Http\Request;

/**
 * Predefined replies: saved answers staff insert into a ticket reply.
 *
 * The tables, the models and the API's read calls (GetTicketPredefinedCats,
 * GetTicketPredefinedReplies) existed; nothing could add one.
 */
class PredefinedReplyController extends Controller
{
    public function index()
    {
        return view('admin.config.predefined-replies', [
            'categories' => TicketPredefinedCategory::with(['replies' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request)
    {
        TicketPredefinedCategory::create($request->validate(['name' => 'required|string|max:255']));

        return back()->with('success', __('admin.predefined.category_saved'));
    }

    public function destroyCategory(TicketPredefinedCategory $category)
    {
        // Its replies go with it (cascade on the foreign key).
        $category->delete();

        return back()->with('success', __('admin.predefined.category_deleted'));
    }

    public function store(Request $request)
    {
        TicketPredefinedReply::create($request->validate([
            'category_id' => 'required|exists:ticket_predefined_categories,id',
            'name' => 'required|string|max:255',
            'reply' => 'required|string|max:20000',
        ]));

        return back()->with('success', __('admin.predefined.reply_saved'));
    }

    public function update(Request $request, TicketPredefinedReply $reply)
    {
        $reply->update($request->validate([
            'category_id' => 'required|exists:ticket_predefined_categories,id',
            'name' => 'required|string|max:255',
            'reply' => 'required|string|max:20000',
        ]));

        return back()->with('success', __('admin.predefined.reply_saved'));
    }

    public function destroy(TicketPredefinedReply $reply)
    {
        $reply->delete();

        return back()->with('success', __('admin.predefined.reply_deleted'));
    }
}
