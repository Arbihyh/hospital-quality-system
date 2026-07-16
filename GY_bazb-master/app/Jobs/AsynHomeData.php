<?php

namespace App\Jobs;


use App\Services\HomeData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AsynHomeData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        //
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $homeData = new HomeData();
        if (!empty($this->data['yzb'])) {
            $homeData->addYzb($this->data['yzb']);
        }

        if (!empty($this->data['bl01'])) {
            $homeData->addBLSY2($this->data['bl01']);
        }


    }
}
