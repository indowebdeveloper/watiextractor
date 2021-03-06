<?php

namespace App\Commands;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;
use Storage;

class Execute extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'execute {date=today : "gunakan tanggal dengan format YYYY-MM-DD ( eg: 2020-07-20 )"}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Mulai extraction wati';
    public $contacts = [];
    public $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiJiYTZmMzU3Ni1lYjc4LTQ2MGUtOWJhNy00MjhhOGE5ZDkwNzUiLCJ1bmlxdWVfbmFtZSI6Imtpb3NiYW4uc2hAZ21haWwuY29tIiwibmFtZWlkIjoia2lvc2Jhbi5zaEBnbWFpbC5jb20iLCJlbWFpbCI6Imtpb3NiYW4uc2hAZ21haWwuY29tIiwiaHR0cDovL3NjaGVtYXMubWljcm9zb2Z0LmNvbS93cy8yMDA4LzA2L2lkZW50aXR5L2NsYWltcy9yb2xlIjoiQURNSU5JU1RSQVRPUiIsImV4cCI6MjUzNDAyMzAwODAwLCJpc3MiOiJDbGFyZV9BSSIsImF1ZCI6IkNsYXJlX0FJIn0.nY1x6fmvVKhEvzJ0JV-oDjQwwTMVvuiE4_l9f-8lplA';
    public $folderName;
    public $index = 1;
    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function itterateChat($chats, $contactName, $phone)
    {
        $text = '';
        foreach ($chats as $chat) {
            if (isset($chat['text']) && $chat['eventType'] == 'message') {
                $timestamp = Carbon::createFromTimestamp($chat['timestamp'])->setTimezone('Asia/Jakarta');

                if (!is_null($chat['operatorName'])) {
                    // for operator
                    $text .= '[' . $timestamp . '] ' . $chat['operatorName'] . ' : ' . $chat['text'];
                } else {
                    // for customer
                    $text .= '[' . $timestamp . '] ' . $contactName . ' : ' . $chat['text'];
                }
                $text .= " \r\n";
            }
        }
        // put as file
        Storage::put('/' . $this->folderName . '/ChatWith-' . $phone . '.txt', $text);
    }
    public function handle()
    {
        $date = ($this->argument('date') == 'today' ? Carbon::now()->format('Y-m-d') : ($this->argument('date') == 'all' ? 'all' : Carbon::createFromFormat('Y-m-d', $this->argument('date'))->toDateString()));
        $this->task("Mengunduh kontak...", function () {
            $this->contacts = Http::withToken($this->token)->get('https://live-server-143.wati.io/api/v1/getContacts?pageSize=10000000')->json()['contact_list'];
            return !is_null($this->contacts);
        });
        $this->task("Membuat folder backup..", function () use ($date) {
            $dateFolder = ($date == 'all' ? 'all-' . Carbon::now()->format('Y-m-d') : $date);
            $this->folderName = 'WatiBackup-' . $dateFolder;
            Storage::makeDirectory($this->folderName);
            return true;
        });
        $this->info('====== Memulai ekstraksi =======');
        $this->question('Total contact adalah : ' . count($this->contacts));
        foreach ($this->contacts as $contact) {
            $this->task("Melakukan backup ( " . $contact['wAid'] . ") " . $this->index . " dari " . count($this->contacts) . " Kontak", function () use ($contact, $date) {
                $contactName = $contact['fullName'];
                $phone = $contact['wAid'];
                $messages = Http::withToken($this->token)->get('https://live-server-143.wati.io/api/v1/getMessages/' . $contact['wAid'] . '?pageSize=1000000')->json();
                if ($date == "all") {
                    $this->itterateChat($messages['messages']['items'], $contactName, $phone);
                } else {
                    if ($date == Carbon::parse($messages['messages']['items'][0]['created'])->setTimezone('Asia/Jakarta')->toDateString()) {
                        $this->itterateChat($messages['messages']['items'], $contactName, $phone);
                    } else {
                        // skip
                        $this->info('-- Skipping karena tanggal tidak sesuai');
                    }
                }
            });
            $this->index++;
        }
        $this->info('====== SELESAI =======');
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
