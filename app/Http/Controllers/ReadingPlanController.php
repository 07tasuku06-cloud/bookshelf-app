<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReadingPlanController extends Controller
{
    /**
     * ログインユーザーの読書計画を状態で絞り込み、期日順に表示する。
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ReadingPlan::class);

        $currentStatus = (string) $request->query('status', '');
        $status = ReadingPlanStatus::tryFrom($currentStatus);

        $query = $request->user()
            ->readingPlans()
            ->with('book')
            ->orderBy('target_date');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $readingPlans = $query
            ->paginate(10)
            ->withQueryString();

        return view('reading-plans.index', compact(
            'readingPlans',
            'currentStatus'
        ));
    }

    /**
     * 読書計画の新規登録画面を表示する。
     */
    public function create(): View
    {
        $this->authorize('create', ReadingPlan::class);

        $books = Book::query()
            ->select(['id', 'title', 'author'])
            ->orderBy('title')
            ->get();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * ログインユーザーの読書計画を登録する。
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['status'] = ReadingPlanStatus::Planned;

        $request->user()
            ->readingPlans()
            ->create($validated);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を登録しました。');
    }

    /**
     * 所有者の読書計画編集画面を表示する。
     */
    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 所有者の読書計画の期日を更新する。
     */
    public function update(
        UpdateReadingPlanRequest $request,
        ReadingPlan $readingPlan
    ): RedirectResponse {
        $this->authorize('update', $readingPlan);

        $readingPlan->update($request->validated());

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * 所有者の読書計画を削除する。
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        $readingPlan->delete();

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    /**
     * 所有者の読書計画を読了状態へ更新する。
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('complete', $readingPlan);

        $readingPlan->update([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を読了にしました。');
    }
}
