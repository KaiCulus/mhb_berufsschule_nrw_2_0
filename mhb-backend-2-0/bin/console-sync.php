<?php
//TODO: Einbindung in einen cronjob oder anderer Aufruf für automatisierte Datensynchronisation.
//TODO: Wichtig: Eventuell ist es sinnvoll das nicht über einen cronjob zu machen. Dann muss überprüft werden, ob der Pfad dieser Datei sinnvoll ist.
require __DIR__ . '/../vendor/autoload.php';

// .env laden
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use Kai\MhbBackend20\Graph\GraphClient;
use Kai\MhbBackend20\Graph\Services\MSFolderSyncService;

echo "[" . date('Y-m-d H:i:s') . "] Starte automatisierten Full-Sync...\n";

try {
    $client = new GraphClient();
    $service = new MSFolderSyncService($client);

    // Hier alle Profile durchgehen, die automatisch synchronisiert werden sollen
    $profiles = ['verwaltung', 'paedagogik']; 
    
    foreach ($profiles as $profile) {
        echo "Synchronisiere Profil: $profile...\n";
        $service->syncByProfile($profile);
    }

    echo "Erfolg: Alle Profile synchronisiert.\n";
} catch (\Exception $e) {
    echo "Fehler beim Auto-Sync: " . $e->getMessage() . "\n";
    exit(1);
}