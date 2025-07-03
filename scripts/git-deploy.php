<?php

// ########################################################################################################################
// # Definierter Anfangspunkt
// ########################################################################################################################
chdir(__DIR__);

// ########################################################################################################################
// # Kurz warten, damit die aufrufende Laravel-App auf alle Fälle beendet wurde und das Update damit
// # keine Probleme bereitet.
// ########################################################################################################################
sleep(1);

// ########################################################################################################################
// # Funktion zum Ausführen von Befehlen und Protokollieren der Ausgabe
// ########################################################################################################################
function executeCommand($command, $logFile)
{
    echo "Executing: $command\n";
    $output = null;
    $returnCode = null;
    exec($command . " 2>&1", $output, $returnCode);
    $outputStr = implode("\n", $output);
    $timestamp = date('Y-m-d H:i:s');
    fwrite($logFile, "[{$timestamp}] Command: {$command}\n");
    fwrite($logFile, "[{$timestamp}] Output: {$outputStr}\n");
    fwrite($logFile, "[{$timestamp}] Return code: {$returnCode}\n\n");
    return $returnCode;
}



// ########################################################################################################################
// # Den Branch aus der .env Datei auslesen.
// # Dabei eventuell gefundenen " löschen ("staging" => staging)
// # UND auch noch die Zeilenumbrüche entfernen.
// ########################################################################################################################
$branchKey = "DEPLOYMENT_BRANCH";
$envContent = file_get_contents(".env");
$branch = "";

if (preg_match('/^' . preg_quote($branchKey) . '=(.*)$/m', $envContent, $matches)) {
    $branch = trim(str_replace('"', '', $matches[1]));
}

// ########################################################################################################################
// # Los gehts...
// ########################################################################################################################

// Alle folgenden Befehle in Log-Datei schreiben.
$logFile = fopen('deployment.log', 'w');

// Da jetzt gleich einiges passiert, wird zuerst die Anwendung heruntergefahren.
executeCommand("php artisan down", $logFile);

// Nun werden eventuell geänderte Dateien rückgängig gemacht (um Merging-Fehler zu verhindern)
// und danach der aktive Branch neu eingespielt (alle Dateien aktualisiert).
executeCommand("git reset --hard", $logFile);
executeCommand("git pull origin {$branch}", $logFile);

// Abhängigkeiten installieren, Skripte und CSS compilieren
executeCommand("composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader", $logFile);
executeCommand("npm install", $logFile);
executeCommand("npm run build", $logFile);

// Datenbank aktualisieren, Caches löschen und neu aufbauen.
executeCommand("php artisan migrate --force", $logFile);
executeCommand("php artisan config:clear", $logFile);
executeCommand("php artisan config:cache", $logFile);
executeCommand("php artisan route:clear", $logFile);
executeCommand("php artisan route:cache", $logFile);
executeCommand("php artisan view:clear", $logFile);

// ACHTUNG!
// artisan view:cache darf AUF KEINEN FALL ausgeführt werden, wenn LIVEWIRE verwendet wird!
// Der Grund: Wenn LIVEWIRE den Template-Cache erstellt, dann compiliert es Kommentare mit rein, die es unbedingt
// benötigt, um Inhalte zu morphen. Wenn wir aber LARAVEL den Template-Cache compilieren lassen, dann weiß Laravel
// nichts von diesen Markern und macht sie nicht mit rein. Dann findet LIVEWIRE diese Kommentare (Marker) nicht und
// kommt durcheinander. D.h. er "zerhaut" dann die View!
// Deshalb KEIN $PHP artisan view:cache

// Dies wird nur für Filament Projekte benötigt
// executeCommand("$php artisan filament:clear-cached-components", $logFile);
// executeCommand("$php artisan filament:cache-components", $logFile);

executeCommand("php artisan up", $logFile);

fclose($logFile);
