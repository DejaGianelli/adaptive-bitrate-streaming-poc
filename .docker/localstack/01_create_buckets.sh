#!/bin/bash
awslocal s3api create-bucket \
    --bucket videos \
    --region us-east-1

# Configure bucket CORS to enable request using the browser’s XMLHttpRequest capability
awslocal s3api put-bucket-cors \
    --bucket videos \
    --cors-configuration file://cors-config.json

awslocal s3api list-buckets