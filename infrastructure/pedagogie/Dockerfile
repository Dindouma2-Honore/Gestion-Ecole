FROM sail-8.3/app
USER root
RUN apt-get update -qq && apt-get install -y --no-install-recommends antiword && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
