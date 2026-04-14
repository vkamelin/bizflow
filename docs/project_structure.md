# Снимок структуры проекта

## Метаданные
- Проект: bizflow
- Дата генерации: 2026-04-14 11:41:36
- Формат: плоский список (tree)
- Назначение: анализ архитектуры

## Исключено из анализа
- vendor
- runtime/cache
- node_modules
- .git

## Структура проекта
```
.
./AGENTS.md
./assets
./assets/main
./assets/main/site.css
./c3.php
./codeception.yml
./composer-dependency-analyser.php
./composer.json
./composer.lock
./config
./config/common
./config/common/aliases.php
./config/common/application.php
./config/common/bootstrap.php
./config/common/di
./config/common/di/application.php
./config/common/di/database.php
./config/common/di/error-handler.php
./config/common/di/logger.php
./config/common/di/router.php
./config/common/params.php
./config/common/routes.php
./config/configuration.php
./config/console
./config/console/commands.php
./config/console/di
./config/console/params.php
./config/environments
./config/environments/dev
./config/environments/dev/params.php
./config/environments/prod
./config/environments/prod/params.php
./config/environments/test
./config/environments/test/params.php
./config/.gitignore
./config/.merge-plan.php
./config/web
./config/web/di
./config/web/di/application.php
./config/web/di/psr17.php
./config/web/params.php
./docker
./docker/compose.yml
./docker/dev
./docker/dev/compose.yml
./docker/dev/.env
./docker/dev/.gitignore
./docker/dev/override.env.example
./docker/Dockerfile
./docker/.env
./docker/prod
./docker/prod/compose.yml
./docker/prod/.env
./docker/prod/.gitignore
./docker/test
./docker/test/compose.yml
./docker/test/.env
./docker/test/.gitignore
./.dockerignore
./docs
./docs/project_structure.md
./docs/rules.md
./docs/Yii3.md
./.editorconfig
./.env
./.env.example
./.gitignore
./.idea
./.idea/bizflow.iml
./.idea/codeception.xml
./.idea/dataSources
./.idea/dataSources/data_sources_history.xml
./.idea/.gitignore
./.idea/laravel-idea.xml
./.idea/modules.xml
./.idea/php.xml
./.idea/vcs.xml
./.idea/workspace.xml
./KODA.md
./Makefile
./.php-cs-fixer.php
./psalm.xml
./public
./public/assets
./public/assets/.gitignore
./public/assets/main
./public/assets/main/4d28afc1
./public/assets/main/4d28afc1/site.css
./public/favicon.ico
./public/favicon.svg
./public/index.php
./public/robots.txt
./.qwenignore
./QWEN.md
./rector.php
./runtime
./runtime/.gitignore
./src
./src/bootstrap.php
./src/Console
./src/Console/Command
./src/Console/HelloCommand.php
./src/Database
./src/Database/Migration
./src/Database/Migration/M260412113841CreateUsersTable.php
./src/Database/Seeder
./src/Environment.php
./src/Shared
./src/Shared/ApplicationParams.php
./src/Web
./src/Web/HomePage
./src/Web/HomePage/Action.php
./src/Web/HomePage/template.php
./src/Web/NotFound
./src/Web/NotFound/NotFoundHandler.php
./src/Web/NotFound/template.php
./src/Web/Shared
./src/Web/Shared/Layout
./src/Web/Shared/Layout/Main
./src/Web/Shared/Layout/Main/layout.php
./src/Web/Shared/Layout/Main/MainAsset.php
./tests
./tests/bootstrap.php
./tests/Console
./tests/Console/HelloCest.php
./tests/Console/YiiCest.php
./tests/Console.suite.yml
./tests/Functional
./tests/Functional/HomePageCest.php
./tests/Functional.suite.yml
./tests/.gitignore
./tests/Support
./tests/Support/ConsoleTester.php
./tests/Support/Data
./tests/Support/Data/.gitkeep
./tests/Support/FunctionalTester.php
./tests/Support/_generated
./tests/Support/_generated/.gitignore
./tests/Support/UnitTester.php
./tests/Support/WebTester.php
./tests/Unit
./tests/Unit/EnvironmentTest.php
./tests/Unit.suite.yml
./tests/Web
./tests/Web/HomePageCest.php
./tests/Web/NotFoundHandlerCest.php
./tests/Web.suite.yml
./tree.sh
./yii
./yii.bat
```

## composer.json
```json
{
    "name": "vkamelin/bizflow",
    "type": "project",
    "description": "BizFlow application",
    "authors": [
        {"name":"Vitaliy Kamelin", "email" : "v.kamelin@gmail.com"}
    ],
    "keywords": [
        "yii3",
        "app"
    ],
    "license": "proprietary",
    "support": {
        "email": "v.kamelin@gmail.com"
    },
    "scripts": {
        "serve": [
            "Composer\\Config::disableProcessTimeout",
            "@php ./yii serve"
        ],
        "test": "codecept run"
    },
    "require": {
        "php": "8.4.*",
        "ext-filter": "*",
        "ext-pdo": "*",
        "ext-redis": "*",
        "httpsoft/http-message": "^1.1.6",
        "psr/container": "^2.0.2",
        "psr/http-factory": "^1.1",
        "psr/http-message": "^2.0",
        "psr/http-server-handler": "^1.0.2",
        "psr/log": "^3.0.2",
        "symfony/console": "^7.4.7 || ^8.0.8",
        "yiisoft/aliases": "^3.1.1",
        "yiisoft/assets": "^5.1.2",
        "yiisoft/cache": "^3.2",
        "yiisoft/cache-redis": "^2.0",
        "yiisoft/config": "^1.6.2",
        "yiisoft/csrf": "^2.2.3",
        "yiisoft/db-migration": "^2.0",
        "yiisoft/db-mysql": "^2.0",
        "yiisoft/definitions": "^3.4.1",
        "yiisoft/di": "^1.4.1",
        "yiisoft/error-handler": "^4.3",
        "yiisoft/html": "^4",
        "yiisoft/http": "^1.3",
        "yiisoft/input-http": "^1.0.1",
        "yiisoft/log": "^2.2.1",
        "yiisoft/middleware-dispatcher": "^5.4",
        "yiisoft/rbac-db": "^2.1",
        "yiisoft/rbac-php": "^2.0",
        "yiisoft/request-provider": "^1.3",
        "yiisoft/router": "^4.0.2",
        "yiisoft/router-fastroute": "^4.0.3",
        "yiisoft/security": "^1.2",
        "yiisoft/session": "^3.0",
        "yiisoft/user": "^2.3",
        "yiisoft/view": "^12.2.4",
        "yiisoft/yii-console": "^2.4.2",
        "yiisoft/yii-http": "^1.1.1",
        "yiisoft/yii-runner-console": "^2.2.1",
        "yiisoft/yii-runner-http": "^3.2.1",
        "yiisoft/yii-view-renderer": "^7.4.1"
    },
    "require-dev": {
        "codeception/c3": "^2.9",
        "codeception/codeception": "^5.3.5",
        "codeception/module-asserts": "^3.3.0",
        "codeception/module-cli": "^2.0.1",
        "codeception/module-phpbrowser": "^4",
        "friendsofphp/php-cs-fixer": "^3.95.0",
        "jetbrains/phpstorm-attributes": "^1.2",
        "phpunit/phpunit": "^11.5.55",
        "rector/rector": "^2.4.1",
        "shipmonk/composer-dependency-analyser": "^1.8.4",
        "vimeo/psalm": "^6.16.1",
        "vlucas/phpdotenv": "^5.6.3"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "App\\Tests\\": "tests"
        }
    },
    "extra": {
        "config-plugin-file": "config/configuration.php"
    },
    "config": {
        "sort-packages": true,
        "bump-after-update": true,
        "allow-plugins": {
            "yiisoft/config": true,
            "codeception/c3": true,
            "composer/installers": true
        }
    }
}

```

## .env (без чувствительных данных)
```
# Local environment configuration.
# Copy this file to .env and adjust as needed.
# In production, set environment variables via server or container configuration instead.
APP_ENV=dev
APP_DEBUG=true

# Database
DB_DSN=mysql:host=localhost;port=3306;dbname=app;charset=utf8mb4
DB_USER=root

# Redis
REDIS_DSN=redis://localhost:6379

```
