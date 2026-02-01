FROM php:8.2-cli-alpine

# Install curl for Docker API communication
RUN apk add --no-cache curl

# Set working directory
WORKDIR /app

# Copy application files
COPY index.php /app/

EXPOSE 8080

# Run PHP built-in server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]
