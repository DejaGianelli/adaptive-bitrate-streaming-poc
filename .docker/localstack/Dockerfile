FROM localstack/localstack:4.5.0

ENV SERVICES="s3,lambda"

# Network tools for debugging purposes
RUN apt-get update && \
    apt-get install -y iputils-ping net-tools traceroute curl dnsutils zip && \
    apt-get clean

EXPOSE 4566

VOLUME ["/var/run/docker.sock"]