#!/bin/sh
set -eu

attempt=0
until [ -s /certs/ars-selfsigned.crt ] && [ -s /certs/ars-selfsigned.key ] \
	&& [ -s /shared-storage/realtime.secret ]; do
	attempt=$((attempt + 1))
	if [ "$attempt" -ge 30 ]; then
		echo "Realtime TLS certificate or signing key is unavailable." >&2
		exit 1
	fi
	sleep 1
done

exec node /app/server.js