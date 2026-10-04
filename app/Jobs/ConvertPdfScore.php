<?php

namespace App\Jobs;

use App\Models\Piece;
use App\Repositories\PieceRepository;
use App\Services\MusicXml\MusicXmlException;
use App\Services\Omr\OmrSpool;
use App\Services\PieceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Hands a PDF to the Audiveris container and collects the MusicXML it writes back.
 *
 * Recognition takes from seconds to minutes, so the job never sits and waits: it puts the file in
 * the spool, then releases itself and looks again every few seconds until a result (or the
 * deadline) turns up. That keeps the queue worker free for other jobs.
 */
class ConvertPdfScore implements ShouldQueue
{
    use Queueable;

    /** Every release counts as an attempt, so the deadline below is what ends the job, not a count. */
    public int $tries = 0;

    private const POLL_SECONDS = 5;

    public function __construct(public Piece $piece) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(config('practice.omr.wait_minutes'));
    }

    public function handle(OmrSpool $spool, PieceService $pieces, PieceRepository $repository): void
    {
        $piece = $this->piece->fresh();
        if ($piece === null || $piece->source_pdf_path === null) {
            return; // deleted or replaced while waiting
        }

        if ($piece->parse_status === 'pending') {
            // Submit first: if this throws, the retry must still find the piece 'pending' and submit again.
            $spool->submit($piece, Storage::disk('local')->path($piece->source_pdf_path));
            $piece->update(['parse_status' => 'converting']);
        }

        $result = $spool->poll($piece);

        if ($result['state'] === 'waiting') {
            $this->release(self::POLL_SECONDS);

            return;
        }

        try {
            if ($result['state'] === 'failed') {
                throw new MusicXmlException($result['reason'] ?: 'The recogniser produced no score.');
            }
            $pieces->attachRecognisedScore($piece, $result['path'], $result['warnings']);
        } catch (MusicXmlException $e) {
            $repository->markFailed($piece);
            Log::info('PDF recognition failed', ['piece' => $piece->id, 'reason' => $e->getMessage()]);
        } finally {
            $spool->clear($piece);
        }
    }

    /** Past the deadline with no answer: the Audiveris container is probably not running. */
    public function failed(?\Throwable $e): void
    {
        $piece = $this->piece->fresh();
        if ($piece && in_array($piece->parse_status, ['pending', 'converting'], true)) {
            app(PieceRepository::class)->markFailed($piece);
            app(OmrSpool::class)->clear($piece);
        }
    }
}
