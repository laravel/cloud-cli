#!/usr/bin/env bash

set -e

# Run from the repository root so box.json and the source paths resolve.
cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 1

VERSION="${1:-dev}"

# The packages the built phar needs at runtime. They live in "require-dev" in the committed
# composer.json so `composer global require laravel/cloud-cli` has no dependency surface to
# collide with; the build promotes them into "require" for the duration of the build only.
RUNTIME_PACKAGES=(
    "illuminate/http"
    "illuminate/translation"
    "illuminate/validation"
    "laravel-zero/framework"
    "laravel-zero/phar-updater"
    "php-parallel-lint/php-console-highlighter"
    "saloonphp/cache-plugin"
    "saloonphp/pagination-plugin"
    "saloonphp/saloon"
    "shipfastlabs/agent-detector"
    "spatie/laravel-data"
)

COMPOSER_JSON_BACKUP=""
COMPOSER_LOCK_BACKUP=""
ENV_BACKUP=""

RED='\033[0;31m'
CYAN='\033[0;36m'
GREEN='\033[0;32m'
RESET='\033[0m'

abort() {
    echo -e "${RED}Error: $1${RESET}" >&2
    exit 1
}

info() {
    echo -e "${CYAN}$1${RESET}"
}

success() {
    echo -e "${GREEN}$1${RESET}"
}

cleanup() {
    # Restore anything we swapped out, including when the build is interrupted mid-flight.
    restored=0

    if [ -n "$ENV_BACKUP" ] && [ -f "$ENV_BACKUP" ]; then
        mv "$ENV_BACKUP" .env
    fi

    if [ -n "$COMPOSER_JSON_BACKUP" ] && [ -f "$COMPOSER_JSON_BACKUP" ]; then
        mv "$COMPOSER_JSON_BACKUP" composer.json
        restored=1
    fi

    if [ -n "$COMPOSER_LOCK_BACKUP" ] && [ -f "$COMPOSER_LOCK_BACKUP" ]; then
        mv "$COMPOSER_LOCK_BACKUP" composer.lock
        restored=1
    fi

    if [ "$restored" -eq 1 ]; then
        echo "Restored composer.json and composer.lock; run \`composer install\` to reinstall dev dependencies." >&2
    fi
}

trap cleanup EXIT

# A leftover backup means composer.json is currently the promoted copy from a crashed run,
# so backing up again would overwrite the only good manifest we have.
if [ -f "composer.json.bak" ] || [ -f "composer.lock.bak" ]; then
    abort "composer.json.bak or composer.lock.bak already exists from an interrupted build. Restore them before building."
fi

if [ -f ".env" ]; then
    ENV_BACKUP=".env.bak"
    mv .env "$ENV_BACKUP"
fi

info "Installing production dependencies..."

COMPOSER_JSON_BACKUP="composer.json.bak"
COMPOSER_LOCK_BACKUP="composer.lock.bak"

cp composer.json "$COMPOSER_JSON_BACKUP"
cp composer.lock "$COMPOSER_LOCK_BACKUP"

# Pin each runtime package to the version already in the lock so promoting them cannot
# resolve anything different from what the committed lock file describes.
php -r '
    $path = "composer.json";
    $data = json_decode(file_get_contents($path), true);
    $lock = json_decode(file_get_contents("composer.lock"), true);
    $locked = [];

    foreach (array_merge($lock["packages"] ?? [], $lock["packages-dev"] ?? []) as $package) {
        $locked[$package["name"]] = $package["version"];
    }

    foreach (array_slice($argv, 1) as $package) {
        if (! isset($data["require-dev"][$package])) {
            fwrite(STDERR, "Runtime package not found in require-dev: {$package}\n");

            exit(1);
        }

        if (! isset($locked[$package])) {
            fwrite(STDERR, "Runtime package not found in composer.lock: {$package}\n");

            exit(1);
        }

        $data["require"][$package] = $locked[$package];
    }

    unset($data["require-dev"]);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
' "${RUNTIME_PACKAGES[@]}"

composer update --no-dev --no-interaction --quiet

info "Building binary..."
php cloud app:build --build-version="$VERSION"

[ -f "builds/cloud" ] || abort "Build output builds/cloud not found."

# A runtime package left in require-dev builds and boots fine, then fatals the first time a
# command touches it. Check the phar itself rather than waiting for a user to find out.
info "Verifying bundled runtime packages..."

php -r '
    Phar::loadPhar("builds/cloud", "cloud-build.phar");
    $missing = [];

    foreach (array_slice($argv, 1) as $package) {
        if (! is_dir("phar://cloud-build.phar/vendor/{$package}")) {
            $missing[] = $package;
        }
    }

    if ($missing) {
        fwrite(STDERR, "Runtime packages missing from the built phar: " . implode(", ", $missing) . "\n");

        exit(1);
    }
' "${RUNTIME_PACKAGES[@]}"

# Catches the same mistake for anything the list does not name: resolve every class the
# application imports against the phar's own autoloader.
info "Verifying imported classes resolve inside the phar..."

php -r '
    $classes = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("app")) as $file) {
        if ($file->getExtension() !== "php") {
            continue;
        }

        preg_match_all("/^use\s+(?!function\s|const\s)([A-Za-z0-9_\\\\]+)(?:\s+as\s+\w+)?;/m", file_get_contents($file), $matches);

        foreach ($matches[1] as $class) {
            $classes[$class] = true;
        }
    }

    Phar::loadPhar("builds/cloud", "cloud-verify.phar");

    require "phar://cloud-verify.phar/vendor/autoload.php";

    $missing = [];

    foreach (array_keys($classes) as $class) {
        if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) {
            $missing[] = $class;
        }
    }

    if ($missing) {
        fwrite(STDERR, "Classes imported by app/ but missing from the built phar:\n  " . implode("\n  ", $missing) . "\n");

        exit(1);
    }
'

# Restore the committed manifest and reinstall the dev dependencies for local work.
info "Restoring development dependencies..."

mv "$COMPOSER_JSON_BACKUP" composer.json
COMPOSER_JSON_BACKUP=""

mv "$COMPOSER_LOCK_BACKUP" composer.lock
COMPOSER_LOCK_BACKUP=""

composer install --no-interaction --quiet

success "Built builds/cloud"
