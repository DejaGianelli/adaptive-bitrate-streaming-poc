# Adaptive Bitrate Streaming POC

A simple POC of Adaptive Bitrate Streaming with DASH protocol, PHP, ffmpeg and Google's Shaka Player

## Host Machine DNS

This allows host machine to resolve localstack hostname to localhost

```txt
# Windows, hosts file
127.0.0.1 video-server-localstack
```

## Docker Configuration

### Network

Creating bridge network to allow containers to resolve their hosts and comunicate with each other

```bash
docker network create -d bridge video-server-net
```

### Server (Apache)

```bash
docker build -t video-server -f .docker/apache/Dockerfile .

docker run --rm -d \
    --name video-server \
    --mount type=bind,src=./webapp,dst=/var/www \
    --network video-server-net \
    --add-host=host.docker.internal:host-gateway \
    --add-host=video-processing.lambda-url.us-east-1.localhost.localstack.cloud:host-gateway \
    -p 8080:80 video-server

# To debug purposes
docker run --rm -it --network video-server-net --entrypoint /bin/bash video-server
```

### Database (MySQL)

```bash
docker run --name video-server-mysql \
    --network video-server-net \
    -e MYSQL_DATABASE=video_server \
    -e MYSQL_USER=user \
    -e MYSQL_PASSWORD=password \
    -e MYSQL_ROOT_PASSWORD=root \
    -p 3306:3306 \
    -d mysql:8.0.40

mysql -u user -ppassword -h video-server-mysql
```

### AWS (Localstack)

Ps: ffmpeg folder with binaries must exist in the video_process lambda folder to deploy lambda correctly.

You can download ffmpeg [here](https://johnvansickle.com/ffmpeg/)

Link of the static build of ffmpeg compatible with lambda image [here](https://johnvansickle.com/ffmpeg/builds/ffmpeg-git-amd64-static.tar.xz)

```bash
docker build -t video-server-localstack -f .docker/localstack/Dockerfile .

docker run --name video-server-localstack --rm -d \
    --network video-server-net \
    --add-host=host.docker.internal:host-gateway \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v ./.docker/localstack:/etc/localstack/init/ready.d \
    -v ./lambdas:/lambdas \
    -p 4566:4566 \
    -p 4510-4559:4510-4559 \
    video-server-localstack

# Update Lambda
chmod +x ffmpeg/ffmpeg && \
zip -r -q function.zip index.js package.json package-lock.json node_modules ffmpeg && \
awslocal lambda update-function-code \
    --function-name video-processing-lambda \
    --zip-file fileb://function.zip

# Invoking lambda via CLI
awslocal lambda invoke \
    --function-name video-processing-lambda \
    --payload '{"key":"<OBJECT_KEY>"}' \
    response.json

# Check localstack Health
curl -v --request GET http://localhost:4566/_localstack/health
```