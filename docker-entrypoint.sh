#!/bin/sh
set -e

# Wait for database to be ready
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database connection..."
    until pg_isready -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USERNAME" 2>/dev/null; do
        sleep 2
    done
    echo "Database is ready."
fi

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:ReplaceWithGeneratedKey" ]; then
    echo "Generating application key..."
    php bin/console key:generate
fi

# Create .env from environment if not present
if [ ! -f .env ]; then
    echo "Creating .env from environment variables..."
    env | grep -E '^(APP_|DB_|SESSION_|COA_|COMPANY_|SAP_|MAIL_)' | while IFS='=' read -r key value; do
        echo "$key=$value"
    done > .env
    echo "APP_KEY=$(php -r 'echo "base64:" . base64_encode(random_bytes(32));')" >> .env
fi

# Run database migrations if schema is empty
php -r "
    \$db = new PDO(
        'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
    \$count = \$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = \\'public\\'')->fetchColumn();
    if (\$count == 0) {
        echo 'Running schema initialization...\n';
        \$schema = file_get_contents('/docker-entrypoint-initdb.d/01-schema.sql');
        \$db->exec(\$schema);
    }
" 2>/dev/null || echo "Schema already initialized or DB not yet available."

# Set permissions
chown -R www-data:www-data storage
chmod -R 775 storage

# Start Apache
exec "$@"
