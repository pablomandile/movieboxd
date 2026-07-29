<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reportable_type' => ['required', Rule::in(['review', 'comment', 'user'])],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(['spoiler', 'spam', 'abuse', 'other'])],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $class = Relation::getMorphedModel($data['reportable_type']);
        $reportable = $class::findOrFail($data['reportable_id']);

        Report::firstOrCreate(
            [
                'reporter_id' => $request->user()->id,
                'reportable_type' => $data['reportable_type'],
                'reportable_id' => $reportable->id,
                'status' => 'pending',
            ],
            [
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
            ]
        );

        return back();
    }
}
