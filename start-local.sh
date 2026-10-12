#!/usr/bin/env bash
# One-command local start: no Docker, no MySQL.
#   bash start-local.sh
# Then open http://localhost:8000   (admin panel: http://localhost:8000/master/login.php)

set -e
cd "$(dirname "$0")"

PORT="${PORT:-8000}"

echo "== 1/3  Checking PHP"
if ! command -v php >/dev/null 2>&1 || ! php -r 'exit(extension_loaded("pdo_sqlite") && extension_loaded("gd") && extension_loaded("mbstring") ? 0 : 1);'; then
  if command -v apt-get >/dev/null 2>&1; then
    echo "PHP (with SQLite, GD and mbstring) is missing - installing it. Your password may be asked."
    sudo apt-get update
    sudo apt-get install -y php-cli php-sqlite3 php-gd php-mbstring php-xml
  else
    echo "PHP 8+ with the sqlite, gd and mbstring extensions is required, and it is not installed."
    echo "  macOS:   brew install php        (install Homebrew first from https://brew.sh)"
    echo "  Windows: use WSL (Ubuntu) and run this script there,"
    exit 1
  fi
fi
php -v | head -1

echo "== 2/3  Preparing the database (first run only)"
mkdir -p data www/upload www/logs
if [ ! -f data/exam.db ]; then
  HASH=$(grep -o '\$2y\$[^'"'"']*' db/02_seed.sql | head -1)
  php -r '
    $d = new PDO("sqlite:" . $argv[1]);
    $d->exec(file_get_contents($argv[2]));
    $s = $d->prepare("INSERT INTO admin_table (admin_email_address, admin_password, admin_type, admin_created_on, email_verified) VALUES (?,?,?,?,?)");
    $s->execute(array("admin@example.com", $argv[3], "master", date("Y-m-d H:i:s"), "yes"));
    echo "Database created.\n";
  ' data/exam.db tests/schema.sqlite.sql "$HASH"
else
  echo "Database already exists (data/exam.db) - keeping it."
fi

export DB_DRIVER=sqlite
export DB_SQLITE_PATH="$PWD/data/exam.db"
export APP_URL="http://localhost:$PORT"
export MAIL_DRIVER=log
export APP_TIMEZONE="${APP_TIMEZONE:-$(cat /etc/timezone 2>/dev/null || echo UTC)}"

echo "== 3/3  Starting the site"
cat <<EOF

  User site : http://localhost:$PORT
  Admin     : http://localhost:$PORT/master/login.php
              login  admin@example.com   password  Admin@12345

  Quick try : log in as admin -> dashboard -> "Create demo exam".
  New users : there is no real e-mail here. Log in as admin -> Users -> click "Verify".
              (the verification link is also saved in www/logs/mail.log)

  Stop the site with  Ctrl+C.   Time zone used for exam times: $APP_TIMEZONE

EOF

exec php -S "localhost:$PORT" -t www
