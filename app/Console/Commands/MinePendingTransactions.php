<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use App\Models\Miner;

class MinePendingTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mine:pending-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mine pending transactions using the specified miner address';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $client = new Client();
        $miners = Miner::all();

        foreach ($miners as $miner) {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            $body = json_encode([
                'minerAddress' => $miner->identifier,
            ]);

            try {
                $response = $client->post('http://localhost:3000/minePendingTransactions', [
                    'headers' => $headers,
                    'body'    => $body,
                ]);

                $this->info('Block mined successfully for miner: ' . $miner->name);
                $this->info('Response: ' . $response->getBody());

            } catch (\Exception $e) {
                $this->error('Error mining block for miner: ' . $miner->name);
                $this->error('Message: ' . $e->getMessage());
            }
        }

        return 0;
    }
}
