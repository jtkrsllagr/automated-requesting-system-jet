FROM node:22-alpine

WORKDIR /app
FROM node:22-alpine

RUN apk add --no-cache openssl
WORKDIR /app
RUN mkdir -p /certs
COPY server.js ./server.js
COPY entrypoint.sh /usr/local/bin/realtime-entrypoint
RUN chmod +x /usr/local/bin/realtime-entrypoint

EXPOSE 8443
ENTRYPOINT ["/usr/local/bin/realtime-entrypoint"]