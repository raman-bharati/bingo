<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\BingoRoomModel;

class CleanupOldRooms extends BaseCommand
{
    protected $group       = 'Bingo';
    protected $name        = 'bingo:cleanup';
    protected $description = 'Delete bingo rooms older than 24 hours';

    public function run(array $params)
    {
        $roomModel = new BingoRoomModel();
        
        $cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        try {
            $deleted = $roomModel->where('updated_at <', $cutoff)->delete();
            CLI::write("Deleted {$deleted} old room(s).", 'green');
        } catch (\Throwable $e) {
            CLI::error('Failed to cleanup rooms: ' . $e->getMessage());
            
            // Fallback: cleanup JSON file
            $this->cleanupJsonFile();
        }
    }
    
    private function cleanupJsonFile(): void
    {
        $path = WRITEPATH . 'bingo_rooms.json';
        
        if (!is_file($path)) {
            CLI::write('No JSON file to cleanup.', 'yellow');
            return;
        }
        
        $fp = fopen($path, 'c+');
        if (!$fp || !flock($fp, LOCK_EX)) {
            CLI::error('Could not lock JSON file.');
            return;
        }
        
        try {
            rewind($fp);
            $contents = stream_get_contents($fp);
            $data = json_decode($contents, true) ?? [];
            
            $cutoffTimestamp = strtotime('-24 hours');
            $removed = 0;
            
            foreach ($data as $roomCode => $room) {
                $updatedAt = $room['lastCall']['at'] ?? $room['createdAt'] ?? 0;
                if ($updatedAt < $cutoffTimestamp) {
                    unset($data[$roomCode]);
                    $removed++;
                }
            }
            
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            
            CLI::write("Cleaned up {$removed} old room(s) from JSON file.", 'green');
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
