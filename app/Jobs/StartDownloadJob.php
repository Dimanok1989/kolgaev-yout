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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StartDownloadJob implements ShouldQueue
{
    use Queueable;

    /**
     * Время работы очереди
     * 
     * @var int
     */
    public $timeout = 3600;

    /**
     * Количество попыток выполнения задания
     * 
     * @var int
     */
    public $tries = 1;

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
        set_time_limit(3600);

        try {

            $pytube = new Pytube(
                $this->process->url,
                $this->process->process_id
            );

            $this->process->update([
                'process_id' => $pytube->uuid,
                'video_id' => $pytube->getVideoId(),
                'title' => $pytube->getTitle(),
                'meta' => $pytube->meta(),
            ]);

            if (!$this->process->videoExists()) {

                $video = $pytube->downloadVideo();
                $basename = pathinfo(trim(collect($video)->last()), PATHINFO_BASENAME);

                $this->process->update([
                    'video_path' => $pytube->path($basename),
                ]);

                $fullPath = Storage::path($pytube->path($basename));
                chown($fullPath, env('TUBE_OWNER_USER', 'www-data'));
                chgrp($fullPath, env('TUBE_OWNER_GROUP', 'www-data'));
            }

            if (!$this->process->audioExists()) {

                $audio = $pytube->downloadAudio();
                $basename = pathinfo(trim(collect($audio)->last()), PATHINFO_BASENAME);

                $this->process->update([
                    'audio_path' => $pytube->path($basename),
                ]);

                $fullPath = Storage::path($pytube->path($basename));
                chown($fullPath, env('TUBE_OWNER_USER', 'www-data'));
                chgrp($fullPath, env('TUBE_OWNER_GROUP', 'www-data'));
            }

            $this->process->update([
                'downloaded_at' => now(),
            ]);

            Http::post($this->process->callback_url . "/downloaded");
        } catch (Exception $e) {
            Log::error('job error {error}', ['error' => $e->getMessage()]);
            FailedProcessJob::dispatch($this->process->callback_url, $e->getMessage());
        }
    }
}
