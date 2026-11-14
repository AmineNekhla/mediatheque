#!/bin/sh
set -e

cd /var/www/html

echo "[entrypoint] Attente de la base de données..."
essais=0
until php bin/console doctrine:query:sql "SELECT 1" >/dev/null 2>&1; do
    essais=$((essais + 1))
    if [ "$essais" -ge 60 ]; then
        echo "[entrypoint] Base de données injoignable après 2 minutes." >&2
        exit 1
    fi
    sleep 2
done

echo "[entrypoint] Application des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

if [ "${LOAD_DEMO_DATA:-1}" = "1" ]; then
    echo "[entrypoint] Données de démonstration (uniquement si la base est vide)..."
    php bin/console app:demo-data
fi

# Les commandes ci-dessus tournent en root : on rend le cache à Apache (www-data)
chown -R www-data:www-data var

# Tâche de maintenance (expiration des réservations, prêts en retard), toutes les heures par défaut
(
    while true; do
        sleep "${MAINTENANCE_INTERVAL:-3600}"
        runuser -u www-data -- php bin/console app:maintenance || true
    done
) &

echo "[entrypoint] Prêt : http://localhost:${APP_PORT:-8000}"
exec "$@"
