#!/bin/bash
cd /lambdas/video_process

# Put ffmpeg in the lambda
chmod +x ffmpeg/ffmpeg
zip -r -q function.zip index.js ffmpeg

echo "Creating lambda with 15 min timeout"

awslocal lambda create-function \
    --function-name video-processing-lambda \
    --runtime nodejs18.x \
    --zip-file fileb://function.zip \
    --handler index.handler \
    --role arn:aws:iam::000000000000:role/lambda-role \
    --timeout 900 \
    --environment "Variables={BUCKET=videos,AWS_REGION=us-east-1}" \
    --tags '{"_custom_id_":"video-processing"}'

sleep 5 # Wait until function is available

awslocal lambda create-function-url-config \
    --function-name video-processing-lambda \
    --auth-type NONE

awslocal lambda get-function \
    --function-name video-processing-lambda

awslocal lambda get-function-url-config \
    --function-name video-processing-lambda