# to build this docker image use this:
# docker build --no-cache   -f docker/scada-frontend/Dockerfile   -t elashrypublic/scada-frontend:v1 .


# Build Stage
FROM node:20-alpine AS builder

WORKDIR /app

COPY Full-SCADA-system/package*.json ./
RUN npm ci

COPY Full-SCADA-system/ .
RUN npm run build


# Runtime Stage
FROM nginx:alpine

COPY docker/scada-frontend/nginx.conf /etc/nginx/conf.d/default.conf

COPY --from=builder /app/dist /usr/share/nginx/html

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]