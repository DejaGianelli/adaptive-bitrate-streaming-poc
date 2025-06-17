## Adaptive Bitrate Streaming POC

A simple POC of Adaptive Bitrate Streaming with DASH protocol, PHP, ffmpeg and Google's Shaka Player

### Building the image
```bash
docker build -t video-server .
```
Ps: ffmpeg folder with binaries must be in the root folder of the project to build image correctly. It can be downloaded [here](https://johnvansickle.com/ffmpeg/)

### Running container for debug purposes
```bash
docker run --rm -it --entrypoint /bin/bash video-server
```

### Running the container
```bash
docker run --rm -d \
    --name video-server \
    --mount type=bind,src=./storage/videos,dst=/storage/videos \
    --mount type=bind,src=./src,dst=/var/www/html \
    -p 8080:80 video-server
```

