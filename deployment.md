# Automatische Deployments mit GitHub

#### [1. Deployment Key einrichten](#1-deployment-key-einrichten)

#### [2. GitHub Repository initial einrichten](#2-github-repository-initial-einrichten)

#### [3. Deployment einrichten](#3-deployment-einrichten)

#### [4. Testen](#4-testen)

#### [5. Weitere Informationen](#5-weitere-informationen)

## Prinzipielle Vorgehensweise

Das vollautomatische Deployment besteht aus zwei Teilen:

* Einer Laravel-Route, deren URL von GitHub per Webhook immer dann aufgerufen wird, wenn etwas in das Repository
  gepushed wird. Diese Route legt dann eine Semaphor-Datei an. Mehr kann sie leider nicht tun, da sie sich sonst selbst
  beim Update den Boden unter den Füßen wegziehen würde.
* Einem Cronjob, der jede Minute läuft und der - sofern er die Semaphor-Datei findet - das Deployment vom
  GitHub-Repository anstößt.

Zusätzlich ist es nötig, Git, Composer und NPM, installiert zu haben

# 1. Deployment Key einrichten

## 1.1 SSH-Key erzeugen

Um (lesend) auf das GitHub-Repository zugreifen zu können, benötigen wir einen SSH-Key.
Diesen können wir erstellen, indem wir eine SSH-Sitzung auf dem gewünschten Server öffnen und im Terminal folgenden
Befehl ausführen:

```bash
ssh-keygen -t ed25519 -C "servername"
```

- Die Frage `Enter file in which to save the key (...)` wird mit dem gewünschten Verzeichnis und
  einem passenden Key-Namen beantwortet. Z.B. `~/.ssh/deploy_key_<repository-name>`.
- `Enter passphrase` wird mit Enter beantwortet (= KEINE Passphrase).
  Dies ist notwendig, da bei Eingabe einer Passphrase diese bei jeder Verwendung des Keys eingegeben werden muss, was
  einen automatisierten Aufruf aus einem Script heraus unmöglich macht.

Zu beachten ist, dass nur der aktuelle User
Zugriff auf den Private-Key haben darf.

```bash
chmod 600 deploy_key
```

## 1.2 SSH-Key aktivieren

Als Nächstes muss das Repository mit dem erzeugten SSH-Key verknüpft werden, damit OpenSSH weiß, welchen Key es beim
Verbindungsaufbau mit GitHub verwenden soll.
Diese Datei heißt `~/.ssh/config` und besitzt folgenden Inhalt:
(Dabei muss <repository-name> bzw. <deploy_key-path> durch die tatsächlichen Werte ersetzt werden.)

```txt
Host github.com
    HostName github.com
    IdentityFile <deploy_key_path>
```

## 1.3 SSH-Key als Deployment-Key bei Github hinterlegen

Gewünschtes GitHub-Repository im Browser öffnen und das Menü `Settings` aufrufen.
Im Menüpunkt `Security > Deploy keys` mit `Add deploy key` die `*.pub`-Datei des erzeugten Keys
hinzufügen.
Der Titel kann frei vergeben werden (am besten Server- und Projektname).
In das Feld `Key` wird der Inhalt der `deploy_key.pub` Datei kopiert.
Der Haken `Allow write access` darf ***NICHT*** gesetzt werden, da nur Lesezugriff benötigt wird.

# 2. GitHub Repository initial einrichten

## 2.1 Repository klonen

Projekt initial klonen:

```bash
git clone git@github.com-<repository-name>:<organisation>/<repository-name>.git
```

`github.com-<repository-name>` entspricht dabei dem Eintrag, der in [1.2](#12-ssh-key-aktivieren) als `Host` angegeben
wurde.

## 2.2 Projekt einrichten

Dazu muss zuerst die Datei '.env' erstellt und mit den "richtigen Werten" gefüllt werden. Die benötigten Werte befinden
sich in der `.env.example`.

```bash
cp .env.example .env
```

Danach muss das Projekt initialisiert werden:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm install
npm run build

php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan optimize
# php artisan filament:cache-components
```

## 2.3 Projekt testen

Jetzt müsste das Projekt über seine URL aufrufbar sein
(Achtung: Bei Konfiguration muss als **Verzeichnis** `/public` hinterlegt sein.)

# 3. Deployment einrichten

## 3.1 Webhook

Das Projekt im Browser innerhalb der GitHub-Seite aufrufen und im Reiter "Settings" den Menüpunkt "Webhooks" aufrufen.

- Neuen Webhook hinzufügen mit der URL `<domain>/git-deploy`
- `Content-Type` auf `application/json` setzen
- Das gewünschte Secret eingeben (Zufalls-String) und für den nächsten Schritt merken
- `SSL verification` auf `Enable SSL verification` setzen
- `Which events would you like to trigger this webhook?` auf `Just the push event` setzen

## 3.2 .env Datei

Die .env Datei um das Secret erweitern. An dieser Stelle kann auch der gewünschte Branch ausgewählt werden, falls dieser
vom Main Branch abweicht.

```dotenv
GITHUB_WEBHOOK_SECRET=secret
DEPLYOMENT_BRANCH=main
```

## 3.3 Cronjob

Einen Cronjob anlegen, der **jede Minute** ausgeführt wird und die Datei [git-deploy](git-deploy.sh) aufruft:

```cronexp
# GitHub Deplyoment
* * * * * username /project-path/git-deploy.sh
```

# 4. Testen

Ein Push auf den gewünschten Branch muss nun die Semaphor-Datei anlegen.
Der Cronjob muss dann deployen.

# 5. Weitere Informationen

- [GitHub SSH](https://docs.github.com/de/authentication/connecting-to-github-with-ssh/generating-a-new-ssh-key-and-adding-it-to-the-ssh-agent)

- [GitHub Deploy Keys](https://docs.github.com/de/authentication/connecting-to-github-with-ssh/managing-deploy-keys)
