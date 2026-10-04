<?php

namespace App\Services\Omr;

use App\Models\Piece;
use Illuminate\Support\Facades\File;

/**
 * Hand-over point between the queue worker and the Audiveris container (docker/omr/watch.sh).
 * Both see the same directory:
 *
 *   in/<id>.pdf      written by submit(); picked up and removed by the watcher
 *   out/<id>.mxl     the recognised score
 *   out/<id>.warn    optional, one line per problem the owner should know (e.g. pages that were skipped)
 *   out/<id>.failed  the reason, when recognition did not produce a score
 */
class OmrSpool
{
    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? config('practice.omr.spool'), '/');
    }

    public function submit(Piece $piece, string $pdfPath): void
    {
        File::ensureDirectoryExists($this->root.'/in', 0775);
        File::ensureDirectoryExists($this->root.'/out', 0775);
        $this->clear($piece);

        // Copy under a name the watcher ignores, then rename: it must never see half a file.
        $part = $this->root.'/in/'.$piece->id.'.part';
        File::copy($pdfPath, $part);
        File::move($part, $this->root.'/in/'.$piece->id.'.pdf');
    }

    /** @return array{state:'waiting'}|array{state:'done',path:string,warnings:list<string>}|array{state:'failed',reason:string} */
    public function poll(Piece $piece): array
    {
        $done = $this->root.'/out/'.$piece->id.'.mxl';
        if (is_file($done)) {
            $warn = $this->root.'/out/'.$piece->id.'.warn';
            $warnings = is_file($warn) ? array_values(array_filter(array_map('trim', file($warn)))) : [];

            return ['state' => 'done', 'path' => $done, 'warnings' => $warnings];
        }
        $failed = $this->root.'/out/'.$piece->id.'.failed';
        if (is_file($failed)) {
            return ['state' => 'failed', 'reason' => trim((string) file_get_contents($failed))];
        }

        return ['state' => 'waiting'];
    }

    /** Remove everything the spool holds for this piece (before a new submit, and after collecting the result). */
    public function clear(Piece $piece): void
    {
        foreach (['in/'.$piece->id.'.pdf', 'in/'.$piece->id.'.part', 'out/'.$piece->id.'.mxl', 'out/'.$piece->id.'.warn', 'out/'.$piece->id.'.failed'] as $file) {
            File::delete($this->root.'/'.$file);
        }
    }
}
