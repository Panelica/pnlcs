# Enough for a registry's introspection sandbox and for anyone who prefers a
# container: configuration still arrives through the same three environment
# variables at run time.
FROM node:22-alpine
WORKDIR /app
COPY server.js ./
COPY lib/ ./lib/
ENTRYPOINT ["node", "/app/server.js"]
