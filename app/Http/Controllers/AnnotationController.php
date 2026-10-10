<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAnnotationsRequest;
use App\Models\Piece;
use App\Models\PieceAnnotation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AnnotationController extends Controller
{
    public function show(Request $request, Piece $piece): View
    {
        Gate::authorize('annotate', $piece);
        $user = $request->user();
        $shared = $piece->annotations()->shared()->with('editor')->first();
        $mine = $piece->annotations()->where('user_id', $user->id)->first();

        return view('pieces.annotate', [
            'piece' => $piece,
            'shared' => $shared,
            'canEditShared' => $user->can('annotateShared', $piece),
            'config' => [
                'title' => $piece->title,
                'source' => $piece->source_pdf_path !== null
                    ? ['type' => 'pdf', 'url' => route('pieces.pdf', $piece)]
                    : ['type' => 'musicxml', 'url' => route('pieces.file', $piece)],
                'layers' => [
                    'shared' => $this->layer($piece, $shared, route('annotations.update', [$piece, 'shared']), $user->can('annotateShared', $piece)),
                    'mine' => $this->layer($piece, $mine, route('annotations.update', [$piece, 'mine']), true),
                ],
            ],
        ]);
    }

    public function update(SaveAnnotationsRequest $request, Piece $piece, string $layer): JsonResponse
    {
        $user = $request->user();
        Gate::authorize($layer === 'shared' ? 'annotateShared' : 'annotate', $piece);

        $annotation = PieceAnnotation::updateOrCreate(
            ['piece_id' => $piece->id, 'user_id' => $this->layerOwner($layer, $user)],
            ['pages' => $request->input('pages'), 'source_path' => $piece->annotationSourcePath(), 'updated_by' => $user->id],
        );

        return response()->json(['savedAt' => $annotation->updated_at->toIso8601String()]);
    }

    private function layerOwner(string $layer, User $user): ?int
    {
        return $layer === 'shared' ? null : $user->id;
    }

    private function layer(Piece $piece, ?PieceAnnotation $annotation, string $saveUrl, bool $editable): array
    {
        return [
            'pages' => $annotation?->pages ?? [],
            'editable' => $editable,
            'saveUrl' => $saveUrl,
            // Marks placed on an earlier upload may no longer sit on the right notes.
            'outdated' => $annotation !== null && $annotation->source_path !== $piece->annotationSourcePath(),
        ];
    }
}
