## Adaptive Bitrate Streaming POC

A simple POC of Adaptive Bitrate Streaming with DASH protocol, PHP, ffmpeg and Google's Shaka Player

### Building the development image
```bash
docker build -t video-server .
```
Ps: ffmpeg folder with binaries must be in the root folder of the project to build image correctly. It can be downloaded [here](https://johnvansickle.com/ffmpeg/)

### Creating docker network
```bash
docker network create -d bridge video-server-net
```

### Running container for debug purposes
```bash
docker run --rm -it --network video-server-net --entrypoint /bin/bash video-server
```

### Running server container
```bash
docker run --rm -d \
    --name video-server \
    --mount type=bind,src=./storage/videos,dst=/storage/videos \
    --mount type=bind,src=./src,dst=/var/www/html \
    --network video-server-net \
    -p 8080:80 video-server
```

### Running database container

```bash
docker run --name video-server-mysql \
    --network video-server-net \
    -e MYSQL_DATABASE=video_server \
    -e MYSQL_USER=user \
    -e MYSQL_PASSWORD=password \
    -e MYSQL_ROOT_PASSWORD=root \
    -p 3306:3306 \
    -d mysql:8.0.40
```

### Database connection
```bash
mysql -u user -ppassword -h video-server-mysql
```