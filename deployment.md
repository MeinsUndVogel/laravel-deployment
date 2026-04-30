# Automatisches Deployment mit GitHub

#### [1. Deployment Key einrichten](#1-deployment-key-einrichten)

#### [2. GitHub Repository initial einrichten](#2-github-repository-initial-einrichten)

#### [3. Deployment einrichten](#3-deployment-einrichten)

#### [4. Testen](#4-testen)

#### [5. Funktionsweise](#5-funktionsweise)

#### [6. Weitere Informationen](#6-weitere-informationen)

## Voraussetzungen

- SSH-Zugang zum Server
- Git auf dem Server installiert
- PHP und Composer auf dem Server installiert
- Ein GitHub Repository

## Prinzipielle Vorgehensweise

Das vollautomatische Deployment besteht aus einem PHP-Script (`deploy.php`), das von GitHub per Webhook aufgerufen wird,
wenn etwas auf einen bestimmten Branch (z.B. `deployment` oder `staging`) gepusht wird.

Das Script startet das Deployment asynchron im Hintergrund, damit:

* Schnell eine Antwort an GitHub gesendet wird (das Deployment kann je nach Änderungen länger dauern)
* Verhindert wird, dass das Deployment-Script sich selbst verändert, während es läuft

# 1. Deployment Key einrichten

## 1.1 SSH-Key erzeugen

Um (lesend) auf das GitHub-Repository zugreifen zu können, wird ein SSH-Key benötigt.
Dazu eine SSH-Sitzung auf dem Server öffnen und folgenden Befehl ausführen:

```bash
ssh-keygen -t ed25519 -C <repository-name>
```

- `<repository-name>` durch den Namen des Repositorys ersetzen, z.B. `muv-webshop`.
- `Enter file in which to save the key (...)`: Pfad und Key-Namen angeben, z.B. `~/.ssh/deploy_key_<repository-name>`.
- `Enter passphrase`: Leer lassen (einfach Enter drücken). Eine Passphrase würde den automatisierten Aufruf unmöglich
  machen.

Anschließend sicherstellen, dass nur der aktuelle User Zugriff auf den Private-Key hat:

```bash
chmod 600 ~/.ssh/deploy_key_<repository-name>
```

## 1.2 SSH-Key aktivieren

Damit OpenSSH den richtigen Key beim Verbindungsaufbau mit GitHub verwendet, muss die Datei `~/.ssh/config` angepasst
werden. Folgenden Eintrag hinzufügen (Platzhalter ersetzen):

```txt
Host github.com-<repository-name>
    HostName github.com
    IdentityFile ~/.ssh/deploy_key_<repository-name>
```

## 1.3 SSH-Key als Deployment-Key bei GitHub hinterlegen

1. GitHub Repository im Browser öffnen → **Settings**
2. **Security** → **Deploy keys** → **Add deploy key**
3. Titel vergeben (z.B. Server- und Projektname)
4. Inhalt der `*.pub`-Datei in das Feld **Key** kopieren
5. **Allow write access** darf ***NICHT*** aktiviert werden (nur Lesezugriff nötig)

# 2. GitHub Repository initial einrichten

## 2.1 Repository klonen

Folgenden Befehl innerhalb des leeren Projekt-Ordners auf dem Server ausführen:

```bash
git clone git@github.com-<repository-name>:<organisation>/<repository-name>.git .
```

`github.com-<repository-name>` entspricht dem `Host`-Eintrag aus [1.2](#12-ssh-key-aktivieren).

## 2.2 Projekt einrichten

Die Datei `.env` im Projekt-Root anlegen mit den Deployment-Variablen:

```dotenv
DEPLOYMENT_BRANCH=deployment
GITHUB_WEBHOOK_SECRET=<ein-sicheres-secret>
```

Danach die projektspezifischen Abhängigkeiten installieren:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
```

Falls das Projekt Node.js-Abhängigkeiten hat:

```bash
npm ci --omit=dev
npm run build
```

Weitere projektspezifische Schritte (z.B. Datenbank-Migrationen, Cache-Optimierungen) sind je nach Projekt
durchzuführen.

## 2.3 Projekt testen

Das Projekt sollte jetzt über seine URL erreichbar sein.

# 3. Deployment einrichten

Im GitHub Repository unter **Settings** → **Webhooks** → **Add webhook**:

1. **Payload URL**: `https://<domain>/deploy.php`
2. **Content type**: `application/json`
3. **Secret**: Wert aus `GITHUB_WEBHOOK_SECRET` in der `.env`
4. **Enable SSL verification**: aktiviert lassen
5. **Which events?**: `Just the push event`

# 4. Testen

Ein Push auf den konfigurierten Branch sollte das Deployment auslösen. Die Logs werden in `storage/logs/deployment.log`
geschrieben.

Falls es Probleme gibt, kann die Response des Webhooks in GitHub unter **Settings** → **Webhooks** → **Recent
Deliveries** eingesehen werden.

# 5. Funktionsweise

## 5.1 Konfiguration

Erforderliche Umgebungsvariablen in der `.env`:

```dotenv
DEPLOYMENT_BRANCH=deployment
GITHUB_WEBHOOK_SECRET=<secret>
```

## 5.2 Deployment-Ablauf

Bei jedem Push zum konfigurierten Branch läuft das Deployment im Hintergrund:

1. Lädt `deploy_pre.sh` (falls vorhanden)
2. Lädt `deploy_pre.php` (falls vorhanden, kann Standard-Befehle überschreiben)
3. Führt die Standard-Deployment-Befehle aus:
   ```sh
   php artisan down
   git reset --hard
   git pull
   composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress --quiet
   npm ci --omit=dev --ignore-scripts
   npm run build
   php artisan optimize
   php artisan migrate --force
   php artisan up
   ```
4. Lädt `deploy_post.sh` (falls vorhanden)
5. Lädt `deploy_post.php` (falls vorhanden)

Der Webhook antwortet sofort mit 200 OK; das Deployment läuft asynchron im Hintergrund. Alle Aktivitäten werden in
`storage/logs/deployment.log` protokolliert.

## 5.3 Hooks anpassen

Optional können folgende Dateien im Projekt-Root erstellt werden:

**`deploy_pre.sh`** – Shell-Script vor dem Deployment:

```bash
#!/usr/bin/env bash
tar -czf backup/$(date +%Y%m%d_%H%M%S).tar.gz .
```

**`deploy_pre.php`** – PHP-Script vor dem Deployment (kann Standard-Befehle überschreiben):

```php
<?php

declare(strict_types=1);

return [
    'git reset --hard',
    'git pull',
    'composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress --quiet',
];
```

**`deploy_post.sh`** – Shell-Script nach dem Deployment:

```bash
#!/usr/bin/env bash
curl -X POST https://monitoring.example.com/alert -d "status=deployed"
```

**`deploy_post.php`** – PHP-Script nach dem Deployment:

```php
<?php

declare(strict_types=1);

file_put_contents('deployment-log.txt', "Deployment erfolgreich\n", FILE_APPEND);
```

## 5.4 Manuelles Triggern

Deployment per Code auslösen (ohne Webhook-Validierung):

```php
MUV\LaravelDeployment\Deployer::triggerAsync();
```

# 6. Weitere Informationen

- [GitHub SSH](https://docs.github.com/de/authentication/connecting-to-github-with-ssh/generating-a-new-ssh-key-and-adding-it-to-the-ssh-agent)
- [GitHub Deploy Keys](https://docs.github.com/de/authentication/connecting-to-github-with-ssh/managing-deploy-keys)
