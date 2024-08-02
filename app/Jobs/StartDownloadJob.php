<?php

namespace App\Jobs;

use App\Models\DownloadProcess;
use App\Services\Pytube;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class StartDownloadJob implements ShouldQueue
{
    use Queueable;

    /**
     * Время работы очереди
     * 
     * @var int
     */
    protected $timeout = 0;

    /**
     * Create a new job instance.
     * 
     * @param int $processId
     * @return void
     */
    public function __construct(
        protected DownloadProcess $process
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {

            $pytube = new Pytube($this->process->url);

            $this->process->update([
                'title' => $pytube->getTitle(),
                'thumbnail_url' => $pytube->getThumbnailUrl(),
            ]);

            if (!$this->process->videoExists()) {

                $video = $pytube->downloadVideo();
                $basename = pathinfo(trim(collect($video)->last()), PATHINFO_BASENAME);

                $this->process->update([
                    'video_path' => $pytube->path($basename),
                ]);
            }

            if (!$this->process->audioExists()) {

                $audio = $pytube->downloadAudio();
                $basename = pathinfo(trim(collect($audio)->last()), PATHINFO_BASENAME);

                $this->process->update([
                    'audio_path' => $pytube->path($basename),
                ]);
            }

            $this->process->update([
                'downloaded_at' => now(),
            ]);

            Http::post($this->process->callback_url . "/downloaded");
        } catch (Exception $e) {
            FailedProcessJob::dispatch($this->process->callback_url, $e->getMessage());
        }
    }
}
