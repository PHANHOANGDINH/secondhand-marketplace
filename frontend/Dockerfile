FROM node:24-bookworm-slim AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --include=dev
COPY frontend/ ./
ARG VITE_API_BASE_URL=""
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}
RUN npm run build

FROM nginx:1.28-alpine AS runtime
COPY --from=build /app/dist /usr/share/nginx/html
COPY backend/public/placeholders /usr/share/nginx/html/placeholders
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
ENV NGINX_ENVSUBST_FILTER="^PUBLIC_(HOST|SCHEME|PORT)$"
EXPOSE 80
