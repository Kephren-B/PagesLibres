# ---------- Build : bundle statique (Vite) ----------
FROM node:22-alpine AS build
ARG VITE_API_URL=http://localhost:8090
ENV VITE_API_URL=$VITE_API_URL
RUN corepack enable && corepack prepare pnpm@latest --activate
WORKDIR /app
COPY package.json pnpm-lock.yaml* ./
RUN pnpm install --frozen-lockfile
COPY . .
RUN pnpm build

# ---------- Runtime : nginx statique (SPA) ----------
FROM nginx:1.27-alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
