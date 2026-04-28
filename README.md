# Automatisches Deployment mit GitHub für PHP-Projekte

[![Latest Stable Version](https://poser.pugx.org/muv/laravel-deployment/v/stable.svg)](https://packagist.org/packages/muv/laravel-deployment)
[![Latest Unstable Version](https://poser.pugx.org/muv/laravel-deployment/v/unstable.svg)](https://packagist.org/packages/muv/laravel-deployment)
[![License](https://poser.pugx.org/muv/laravel-deployment/license.svg)](https://packagist.org/packages/muv/laravel-deployment)

Mit diesem Package kann in einem PHP-Projekt automatisch ein Update bei Push-Events im GitHub Repository durchgeführt
werden.

## Installation

Einbindung mit Composer

```bash
composer require muv/laravel-deployment
```

Danach das Deploy-Script publizieren:

```bash
./vendor/bin/publish-deploy
```

Dies erstellt eine `deploy.php` Datei im `public` Verzeichnis.

## Konfiguration

Erforderliche Umgebungsvariablen:

```dotenv
DEPLOYMENT_BRANCH=deployment
GITHUB_WEBHOOK_CONTENT_TYPE=application/json
GITHUB_WEBHOOK_SECRET=
```

GitHub Webhook einrichten:

1. Repository **Settings** → **Webhooks** → **Add webhook**
2. **Payload URL**: `https://yourdomain.com/deploy.php`
3. **Secret**: Wert aus `GITHUB_WEBHOOK_SECRET`
4. **Content type**: `application/json`
5. Events: **Push events**

## Funktionsweise

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

### Hooks anpassen

Erstelle optional folgende Dateien im Root-Verzeichnis:

**`deploy_pre.sh`**: Shell-Script vor dem Deployment:

```bash
#!/usr/bin/env bash
tar -czf backup/$(date +%Y%m%d_%H%M%S).tar.gz .
```

**`deploy_pre.php`**: PHP-Script vor dem Deployment (überschreibt Standard-Befehle):

```php
<?php

declare(strict_types=1);

return [
    'git reset --hard',
    'git pull',
    'composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress --quiet',
];
```

**`deploy_post.sh`**: Shell-Script nach dem Deployment:

```bash
#!/usr/bin/env bash
curl -X POST https://monitoring.example.com/alert -d "status=deployed"
```

**`deploy_post.php`**: PHP-Script nach dem Deployment:

```php
<?php

declare(strict_types=1);

file_put_contents('deployment-log.txt', "Deployment erfolgreich\n", FILE_APPEND);
```

### Manuelles Triggern

Deployment per Code auslösen (ohne Webhook-Validierung):

```php
MUV\LaravelDeployment\Deployer::triggerAsync();
```

## Kontakt

Bei Fragen oder Anregungen: [muv.com/kontakt](https://muv.com/kontakt)

## Tests

```bash
composer test
```

## Lizenz

Das Package ist unter der [MIT-Lizenz](LICENSE) erhältlich.
