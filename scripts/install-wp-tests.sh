#!/usr/bin/env bash
# WordPress Test Environment Installation Script
# Based on WordPress Core test suite installer
#
# This script installs WordPress and the WordPress Test Suite for running PHPUnit tests
# Usage: ./install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]

if [ $# -lt 3 ]; then
	echo ""
	echo "❌ ERROR: Missing required arguments"
	echo ""
	echo "Usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	echo ""
	echo "Arguments:"
	echo "  db-name     Database name for tests (will be created/recreated)"
	echo "  db-user     MySQL username"
	echo "  db-pass     MySQL password (use '' for empty password)"
	echo "  db-host     MySQL host (default: localhost)"
	echo "  wp-version  WordPress version to install (default: 6.7.1)"
	echo ""
	echo "Example:"
	echo "  $0 wordpress_test root '' localhost 6.7.1"
	echo ""
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-6.7.1}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress/}

echo ""
echo "🚀 WordPress Test Suite Installer"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Installing WordPress Test Environment..."
echo ""
echo "Configuration:"
echo "  Database Name:     $DB_NAME"
echo "  Database User:     $DB_USER"
echo "  Database Host:     $DB_HOST"
echo "  WordPress Version: $WP_VERSION"
echo "  WP Tests Dir:      $WP_TESTS_DIR"
echo "  WP Core Dir:       $WP_CORE_DIR"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
	WP_BRANCH=${WP_VERSION%\-*}
	WP_TESTS_TAG="branches/$WP_BRANCH"

elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"

elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
		WP_TESTS_TAG="tags/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi

elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' || $WP_VERSION == 'latest' ]]; then
	WP_TESTS_TAG="trunk"

else
	echo "Invalid version $WP_VERSION"
	exit 1
fi

set -ex

install_wp() {

	if [ -d $WP_CORE_DIR ]; then
		echo "✅ WordPress core already installed at $WP_CORE_DIR"
		return;
	fi

	echo "📥 Downloading WordPress $WP_VERSION..."
	mkdir -p $WP_CORE_DIR

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		mkdir -p $TMPDIR/wordpress-trunk
		rm -rf $TMPDIR/wordpress-trunk/*
		svn export --quiet https://core.svn.wordpress.org/trunk $TMPDIR/wordpress-trunk/wordpress
		mv $TMPDIR/wordpress-trunk/wordpress/* $WP_CORE_DIR
	else
		if [ $WP_VERSION == 'latest' ]; then
			local ARCHIVE_NAME='latest'
		elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
			if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
				local ARCHIVE_NAME="${WP_VERSION%??}"
			else
				local ARCHIVE_NAME=$WP_VERSION
			fi
		else
			local ARCHIVE_NAME="wordpress-$WP_VERSION"
		fi
		download https://wordpress.org/wordpress-${ARCHIVE_NAME}.tar.gz  $TMPDIR/wordpress.tar.gz
		tar --strip-components=1 -zxmf $TMPDIR/wordpress.tar.gz -C $WP_CORE_DIR
	fi

	echo "✅ WordPress $WP_VERSION downloaded successfully"

	echo "📥 Downloading mysqli drop-in..."
	download https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
	echo "✅ mysqli drop-in installed"
}

install_test_suite() {
	if [ -d $WP_TESTS_DIR ]; then
		echo "✅ WordPress Test Suite already installed at $WP_TESTS_DIR"
		return;
	fi

	echo "📥 Downloading WordPress Test Suite from SVN..."
	mkdir -p $WP_TESTS_DIR

	rm -rf $WP_TESTS_DIR/{includes,data}

	svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
	svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data

	echo "✅ WordPress Test Suite downloaded successfully"

	echo "📝 Generating wp-tests-config.php..."
	if [ ! -f wp-tests-config.php ]; then
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
		WP_CORE_DIR=$(echo $WP_CORE_DIR | sed 's:/\+$::')
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s:__FILE__:'$WP_TESTS_DIR/wp-tests-config.php':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
	fi
	echo "✅ wp-tests-config.php generated"
}

recreate_db() {
	shopt -s nocasematch
	if [[ $1 =~ ^(y|yes)$ ]]
	then
		mysqladmin drop $DB_NAME -f --user="$DB_USER" --password="$DB_PASS"$EXTRA
		create_db
		echo "Recreated the database ($DB_NAME)."
	else
		echo "Skipping database recreation."
		exit 1
	fi
	shopt -u nocasematch
}

create_db() {
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_db() {
	if [ ${SKIP_DB_CREATE} = "true" ]; then
		return 0
	fi

	# Use DB_HOST for compatibility (script historically used DB_HOSTNAME)
	DB_HOSTNAME=${DB_HOST}
	
	EXTRA=""

	if ! [ -z "$DB_HOSTNAME" ] ; then
		# If hostname starts with /, it's a socket path
		if [[ $DB_HOSTNAME == /* ]] ; then
			EXTRA=" --socket=$DB_HOSTNAME"
		# If hostname contains a colon, it's host:port
		elif [[ $DB_HOSTNAME == *:* ]] ; then
			EXTRA=" --host=$(echo $DB_HOSTNAME | cut -d: -f1) --port=$(echo $DB_HOSTNAME | cut -d: -f2) --protocol=tcp"
		# Otherwise it's just a hostname or IP - use TCP
		else
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	if [ -n "`mysql --user="$DB_USER" --password="$DB_PASS"$EXTRA --execute='show databases;' | grep ^$DB_NAME$`" ]
	then
		# In CI/CD or non-interactive mode, automatically recreate database
		if [ -t 0 ]; then
			# Interactive mode - ask for confirmation
			echo ""
			echo "⚠️  DATABASE ALREADY EXISTS"
			echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
			echo "The database '$DB_NAME' already exists in your MySQL server."
			echo ""
			echo "WordPress Test Suite requires a clean database installation."
			echo "The existing database will be DROPPED and recreated."
			echo ""
			echo "⚠️  WARNING: This will DELETE all data in the '$DB_NAME' database!"
			echo ""
			echo "If this is a production database or contains important data,"
			echo "press Ctrl+C now to cancel, or type 'N' below."
			echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
			echo ""
			read -p "Are you sure you want to proceed? [y/N]: " DELETE_EXISTING_DB
			recreate_db $DELETE_EXISTING_DB
		else
			# Non-interactive mode (CI/CD) - automatically recreate
			echo "🔄 Database already exists - automatically recreating for test environment..."
			mysqladmin drop $DB_NAME -f --user="$DB_USER" --password="$DB_PASS"$EXTRA
			create_db
			echo "✅ Database recreated successfully"
		fi
	else
		create_db
	fi
}

case $(uname -s) in
	Darwin)
		ioption='-i.bak'
		;;
	*)
		ioption='-i'
		;;
esac

install_wp
install_test_suite
install_db

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ WordPress Test Suite installed successfully!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Environment Variables:"
echo "  WP_TESTS_DIR: $WP_TESTS_DIR"
echo "  WP_CORE_DIR:  $WP_CORE_DIR"
echo ""
echo "Add this to your phpunit.xml.dist:"
echo "  <const name=\"WP_TESTS_DIR\" value=\"$WP_TESTS_DIR\"/>"
echo ""
echo "Now you can run tests with:"
echo "  vendor/bin/phpunit --testdox"
echo ""
