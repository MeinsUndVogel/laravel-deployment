#!/usr/bin/bash

########################################################################################################################
# Definierter Anfangspunkt
########################################################################################################################
SCRIPT_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd $SCRIPT_PATH

########################################################################################################################
# Den Branch aus der .env Datei auslesen.
# Dabei eventuell gefundenen " löschen ("staging" => staging)
# UND auch noch die Zeilenumbrüche entfernen.
########################################################################################################################
BRANCH_KEY="GITHUB_BRANCH"
BRANCH=$(awk -F '=' "/^$BRANCH_KEY/ {gsub(/[\"]/, \"\", \$2); print \$2}" ".env" | tr -d '\n' | tr -d '\r')

########################################################################################################################
# Konstanten definieren
########################################################################################################################
#--- Pfad zu PHP
PHP="/usr/local/bin/php"

########################################################################################################################
# Los gehts...
########################################################################################################################

# Der GitHub-Webhook ruft eine URL im Projekt auf. Diese setzt dann eine Semaphor-Datei.
# Wenn diese Semaphor-Datei existiert, wird das Deployment aufgerufen.
# Wenn nicht, wird das Script hier beendet.
if [ ! -f git-deploy.sem ]; then
    echo "Semaphor nicht gefunden. Es findet kein Deployment statt."
    exit 0
fi

#
# TEST!!!
#
exec > alle_befehle.txt 2>&1

#
# Die Semaphor-Datei sofort löschen, damit dieses Script auf keinen Fall noch einmal läuft.
#
rm git-deploy.sem

#
# Da jetzt gleich einiges passiert, wird zuerst die Anwendung heruntergefahren.
#
$PHP artisan down

#
# Nun werden eventuell geänderte Dateien rückgängig gemacht (um Merging-Fehler zu verhindern)
# und danach der aktive Branch neu eingespielt (alle Dateien aktualisiert).
#
git reset --hard
git pull origin $BRANCH

#
# Abhängigkeiten installieren, Skripte und CSS compilieren
#
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm install
npm run build

#
# Datenbank aktualisieren, Caches löschen und neu aufbauen.
#
$PHP artisan migrate --force
$PHP artisan config:clear
$PHP artisan config:cache
$PHP artisan route:clear
$PHP artisan route:cache
$PHP artisan view:clear
$PHP artisan view:cache
$PHP artisan filament:clear-cached-components
$PHP artisan filament:cache-components
$PHP artisan up

####################################################################################################
# Fertig...
####################################################################################################
exit 0
