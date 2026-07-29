<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ReviewController;
use App\Models\Comment;
use App\Models\ListModel;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', ReportStatus::Pending->value);

        $reports = Report::query()
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->with(['reporter', 'reportable', 'resolver'])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('admin/Reports', [
            'status' => $status,
            'reports' => $reports->through(fn (Report $report) => [
                'id' => $report->id,
                'reason' => $report->reason->value,
                'details' => $report->details,
                'status' => $report->status->value,
                'createdAt' => $report->created_at->toDateString(),
                'reporter' => $report->reporter?->only('name', 'username'),
                'resolvedBy' => $report->resolver?->name,
                'target' => $this->targetProps($report),
            ]),
        ]);
    }

    /**
     * Resuelve un reporte: descartar, o borrar el contenido reportado.
     */
    public function update(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['dismiss', 'delete_content'])],
        ]);

        if ($data['action'] === 'delete_content') {
            // El borrado dispara los observers que ajustan los contadores
            $report->reportable?->delete();
        }

        $report->update([
            'status' => $data['action'] === 'dismiss' ? ReportStatus::Dismissed : ReportStatus::Resolved,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return back();
    }

    protected function targetProps(Report $report): ?array
    {
        $target = $report->reportable;

        return match (true) {
            $target instanceof Review => [
                'type' => 'review',
                'excerpt' => str($target->body)->limit(300)->toString(),
                'containsSpoilers' => $target->contains_spoilers,
                'author' => $target->user->only('name', 'username'),
                'url' => route('reviews.show', $target),
                'subject' => ReviewController::subjectProps($target->reviewable)['name'] ?? null,
            ],
            $target instanceof Comment => [
                'type' => 'comment',
                'excerpt' => str($target->body)->limit(300)->toString(),
                'containsSpoilers' => false,
                'author' => $target->user->only('name', 'username'),
                'url' => $target->commentable instanceof Review ? route('reviews.show', $target->commentable) : null,
                'subject' => null,
            ],
            $target instanceof ListModel => [
                'type' => 'list',
                'excerpt' => $target->name,
                'containsSpoilers' => false,
                'author' => $target->user->only('name', 'username'),
                'url' => route('lists.show', ['username' => $target->user->username, 'list' => $target->id]),
                'subject' => null,
            ],
            $target instanceof User => [
                'type' => 'user',
                'excerpt' => $target->bio,
                'containsSpoilers' => false,
                'author' => $target->only('name', 'username'),
                'url' => route('profiles.show', $target->username),
                'subject' => null,
            ],
            // El contenido ya fue borrado
            default => null,
        };
    }
}
