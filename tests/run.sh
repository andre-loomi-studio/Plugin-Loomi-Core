#!/usr/bin/env bash
# Run the WP-PHPUnit suite inside the docker stack.
# Usage: bash tests/run.sh [-- --filter SomeTest]
set -e

PLUGIN_PATH=/var/www/html/wp-content/plugins/loomi-studio-setup
CONTAINER=loomi-clean-wp

# Ensure WP-PHPUnit + test DB are installed
docker exec --user root "$CONTAINER" bash "$PLUGIN_PATH/tests/install-wp-tests.sh" 2>/dev/null || true

# Ensure vendor/ is present
if ! docker exec "$CONTAINER" test -d "$PLUGIN_PATH/vendor"; then
    docker exec --user root "$CONTAINER" bash -c "cd $PLUGIN_PATH && composer install --no-interaction"
fi

# Run phpunit
docker exec --user www-data "$CONTAINER" \
    php "$PLUGIN_PATH/vendor/bin/phpunit" \
    -c "$PLUGIN_PATH/phpunit.xml.dist" "$@"
