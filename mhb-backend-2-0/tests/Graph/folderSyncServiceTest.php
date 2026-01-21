<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Pfad korrigiert: Zwei Ebenen hoch zum Root
require __DIR__ . '/../../vendor/autoload.php';

// 2. Pfad korrigiert: .env liegt im Root
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

use Kai\MhbBackend20\Graph\GraphClient;
use Kai\MhbBackend20\Graph\Services\MSFolderSyncService;

echo "--- MHB Sync Test Start ---\n";

try {
    echo "[1/3] Initialisiere Komponenten...\n";
    $graphClient = new GraphClient();
    $syncService = new MSFolderSyncService($graphClient);

    echo "[2/3] Starte Sync für Profil 'verwaltung'...\n";
    // Dieser Aufruf geht nun rekursiv durch deine MS Graph Ordner
    $syncService->syncByProfile('verwaltung');

    echo "[3/3] Sync erfolgreich abgeschlossen!\n";
    
} catch (\Throwable $e) {
    echo "\n!!! FEHLER GEFUNDEN !!!\n";
    echo "Nachricht: " . $e->getMessage() . "\n";
    echo "In Datei: " . $e->getFile() . " (Zeile " . $e->getLine() . ")\n";
    echo "------------------------\n";
}