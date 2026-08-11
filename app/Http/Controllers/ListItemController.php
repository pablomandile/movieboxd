<?php

namespace App\Http\Controllers;

use App\Models\ListItem;
use App\Models\ListModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ListItemController extends Controller
{
    public function store(Request $request, ListModel $list): RedirectResponse
    {
        Gate::authorize('updateItems', $list);

        $data = $request->validate([
            'title_id' => ['required', 'integer', 'exists:titles,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $exists = ListItem::where('list_id', $list->id)->where('title_id', $data['title_id'])->exists();

        if (! $exists) {
            ListItem::create([
                'list_id' => $list->id,
                'title_id' => $data['title_id'],
                'position' => (int) ListItem::where('list_id', $list->id)->max('position') + 1,
                'note' => $data['note'] ?? null,
            ]);

            $list->touch();
        }

        return back();
    }

    public function update(Request $request, ListModel $list, ListItem $item): RedirectResponse
    {
        Gate::authorize('updateItems', $list);
        abort_unless($item->list_id === $list->id, 404);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $item->update(['note' => $data['note'] ?? null]);

        return back();
    }

    public function destroy(ListModel $list, ListItem $item): RedirectResponse
    {
        Gate::authorize('updateItems', $list);
        abort_unless($item->list_id === $list->id, 404);

        DB::transaction(function () use ($list, $item) {
            $item->delete();

            // Resecuenciar posiciones
            $list->items()->orderBy('position')->get()->each(function (ListItem $remaining, int $index) {
                $remaining->update(['position' => $index + 1]);
            });
        });

        return back();
    }

    /**
     * Reordena la lista completa: recibe los ids de ítems en el nuevo orden.
     */
    public function reorder(Request $request, ListModel $list): RedirectResponse
    {
        Gate::authorize('updateItems', $list);

        $data = $request->validate([
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['integer'],
        ]);

        $items = $list->items()->pluck('id');

        abort_unless(
            $items->count() === count($data['item_ids'])
            && $items->diff($data['item_ids'])->isEmpty(),
            422
        );

        DB::transaction(function () use ($data) {
            foreach (array_values($data['item_ids']) as $index => $itemId) {
                ListItem::whereKey($itemId)->update(['position' => $index + 1]);
            }
        });

        return back();
    }
}
