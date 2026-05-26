#!/usr/bin/env bash
# Installs WP-PHPUnit + creates the test DB.
# Usage: bash tests/install-wp-tests.sh [db-name] [db-user] [db-pass] [db-host] [wp-version]
set -e

DB_NAME=${1-wordpress_test}
DB_USER=${2-wordpress}
DB_PASS=${3-wordpress}
DB_HOST=${4-db:3306}
WP_VERSION=${5-6.7.2}
WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}

download() {
    if [ "$(which curl)" ]; then
        curl -fsSL "$1" > "$2"
    else
        wget -nv -O "$2" "$1"
    fi
}

install_wp() {
    if [ -d "$WP_CORE_DIR" ]; then return; fi
    mkdir -p "$WP_CORE_DIR"
    download "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" /tmp/wordpress.tar.gz
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
}

install_test_suite() {
    if [ ! -d "$WP_TESTS_DIR" ]; then
        mkdir -p "$WP_TESTS_DIR"
        svn co --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes" || {
            # fallback: download tarball
            download "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${WP_VERSION}.tar.gz" /tmp/wp-tests.tar.gz
            mkdir -p /tmp/wp-tests
            tar -xzf /tmp/wp-tests.tar.gz -C /tmp/wp-tests --strip-components=1
            cp -r /tmp/wp-tests/tests/phpunit/includes "$WP_TESTS_DIR/"
            cp -r /tmp/wp-tests/tests/phpunit/data "$WP_TESTS_DIR/"
        }
        svn co --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/data/" "$WP_TESTS_DIR/data" || true
    fi

    if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
        download "https://develop.svn.wordpress.org/tags/${WP_VERSION}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
        sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR':" "$WP_TESTS_DIR/wp-tests-config.php"
        sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
        sed -i "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
        sed -i "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
        sed -i "s|localhost|$DB_HOST|" "$WP_TESTS_DIR/wp-tests-config.php"
    fi
}

install_db() {
    local host="${DB_HOST%:*}"
    local port="${DB_HOST#*:}"
    if [ "$host" = "$port" ]; then port=3306; fi
    mysqladmin create "$DB_NAME" --user="root" --password="rootpass" --host="$host" --port="$port" --protocol=tcp 2>/dev/null || true
}

install_wp
install_test_suite
install_db
echo "Done. WP at $WP_CORE_DIR, tests at $WP_TESTS_DIR, DB $DB_NAME on $DB_HOST"
