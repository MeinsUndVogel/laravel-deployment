Da wir aktuell unsere Projekte beim Mittwald hosten, ist diese Readme aktuell auf Mittwald
ausgelegt und muss eventuell bei anderen Hostern angepasst werden.

Wir gehen bei der folgenden Beschreibung davon aus, dass das Projekt bereits bei Mittwald eingerichtet ist.
Der Github-Client und Open SSH wurden dabei bereits von Mittwald vollautomatisch installiert, sodass 
wir auf diese Befehle ohne weitere Vorarbeiten zugreifen können.

# 1. Prinzipielle Vorgehensweise

Das vollautomatische Deployment besteht aus zwei Teilen:

* Einer Laravel-Route, deren URL von Github per Webhook immer dann aufgerufen wird, wenn etwas in das Repository
  gepushed wird. Diese Route legt dann eine Semaphor-Datei an. Mehr kann sie leider nicht tun, da sie sich sonst selbst
  beim Update den Boden unter den Füßen wegziehen würde.
* Einem cron-Job, der jede Minute läuft und der - sofern er die Semaphor-Datei findet - das Deployment vom
  Github-Repository anstößt.

# 1. SSH-Key einrichten

## 1.1 SSH-Key erzeugen

Um (lesend) auf unser Github-Repository zugreifen zu können, benötigen wir einen SSH-Key.
Diesen können wir erstellen, indem wir eine SSH-Sitzung öffnen und im Terminal folgenden Befehl ausführen:

`ssh-keygen -t ed25519 -C "<deine-email@example.com>"`
Dabei muss natürlich eine geeignete E-MAil-Adresse verwendet werden.
Die Frage `Enter file in which to save the key (...)` wird mit dem gewünschten Verzeichnis und
einem passenden Key-Namen beantwortet. Z.B. `/.ssh/git_deploy_key_<repository-name>`.
Die Frage `Enter passphrase` wird mit Enter beantwortet (= KEINE Passphrase).
Dies ist notwendig, da bei Eingabe einer Passphrase diese bei jeder Verwendung des Keys eingegeben werden muss, was e
inen automatisierten Aufruf aus einem Script heraus unmöglich macht.

## 1.2 SSH-Key aktivieren

Als nächstes muss das Repository mit dem erzeugten SSH-Key verknüpft werden, damit OpenSSH weiß, welchen Key es beim
Verbindungsaufbau mit Github verwenden soll.
Diese Datei heißt `/.ssh/config` und besitzt folgenden Inhalt:
(Dabei muss <repository-name> bzw. <key-name> durch die tatsächlichen Werte ersetzt werden.)

```txt
Host github.com-<repository-name>
        Hostname github.com
        IdentityFile=/.ssh/<key-name>
```

## 1.3 SSH-Key als Deployment-Key bei Github hinterlegen

Gewünschtes Github-Repository im Browser öffnen und das Menü `Settings` aufrufen.
Den Menüpunkt `Security > Deploy keys` aufrufen und mit `Add deploy key` die *.pub-Datei des erzeugten Keys hinzufügen.
Der Titel kann frei vergeben werden (am Besten bezeichnend).
In das Feld `Key` wird der Inhalt der *.pub-Datei kopiert.
Der Haken `Allow write access` sollte *NICHT* gesetzt werden, da ein solcher Zugriff nicht benötigt wird.

# 2. Github Repository initial einrichten

## 2.1 Repository klonen

Zuerst innerhalb des SSH-Terminals in den App-Ordner wechseln
`cd /home/<projekt-Id>/html/<app-id>`
Dann mit
`git clone git@github.com-<repository-name>:<repository> .`
das Projekt initial klonen (Den "." am Ende nicht vergessen, da dieser dafür sorgt, dass das Projekt ins
aktuelle Verzeichnis geklont wird.

`github.com-<repository-name>` entspricht dabei dem Eintrag, der in 1.2 SSH-Key aktivieren als `Host` angegeben wurde.

`<repository>` entspricht dabei dem Namen des Repositorys, also dem Teil der URL hinter https://github.com **inkl.**
der Organisation bzw. dem Inhaber des Repositorys.

## 2.2 Projekt einrichten

Dazu muss zuerst die Datei '.env' erstellt bzw. mit den "richtigen Werten" gefüllt werden.
Welche dies sind, hängt vom jeweiligen Projekt ab.
Danach muss das Projekt initialisiert werden:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm install
npm run build

#
# Datenbank aktualisieren, Caches löschen und neu aufbauen.
#
php artisan migrate --force
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache
php artisan up  
```

## 2.3 Projekt testen

Jetzt müsste das Projekt über seine URL aufrufbar sein
(Achtung: Bei Konfiguration muss als **Verzeichnis** `/public` hinterlegt sein.)

# 3. Git Deployment einrichten

## 3.1 Webhook

Das Projekt im Browser innerhalb der github-Seite aufrufen.
Den Menüpunkt "Settings" anklicken und den Untern-Menüpunkt "Webhooks" aufrufen.
Neuen Webhook hinzufügen mit der URL `<domain>/git-deploy`.
`Content-Type` auf `application/json` setzen.
Das gewünschte Secret eingeben (Zufalls-String) und für den nächsten Schritt merken.
`SSL verification` auf `Enable SSL verification` setzen.
`Which events would you like to trigger this webhook?` auf `Just the push event.` setzen.


## 3.2 .env Datei

Das gemerkte Secret in der `.env`-Datei unter `GITHUB_WEBHOOK_SECRET="..."` eintragen.

## 3.3 cronjob

Einen Cronjob anlegen, der **jede Minute** ausgeführt wird.
Dieser soll die Datei `/html/<projekt-id>/git-deploy.sh` aufrufen.
D.h. `Befehl ausführen` `Interpteter = Bash`

# 4. Testen

Ein push auf den gewünschten Branch muss nun die Semaphor-Datei anlegen.
Der cronjob muss dann deployen.

# 5. Mehr Info...
https://docs.github.com/de/authentication/connecting-to-github-with-ssh/generating-a-new-ssh-key-and-adding-it-to-the-ssh-agent

https://docs.github.com/de/authentication/connecting-to-github-with-ssh/managing-deploy-keys
