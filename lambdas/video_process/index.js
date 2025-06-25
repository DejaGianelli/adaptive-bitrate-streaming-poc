const {
    S3Client,
    S3ServiceException,
    NoSuchKey,
    GetObjectCommand,
} = require('@aws-sdk/client-s3')
const { promisify } = require('util')
const { createWriteStream, promises } = require('fs')
const path = require('path')
const { pipeline } = require('stream')

const BUCKET_NAME = 'videos'

/**
 * Lambda handler for processing videos in S3.
 * @param {Object} event - Input event containing media details
 * @param {string} event.body = Json Body
 * @returns {Promise<string>} Success message
 */
exports.handler = async (event) => {

    const body = JSON.parse(event.body);
    const key = body.key;

    try {
        // Execute the binary (must be in the same directory or provide full path)
        //const result = execSync('./ffmpeg/ffmpeg -version').toString();

        const s3Client = new S3Client({});

        const response = await s3Client.send(
            new GetObjectCommand({
                Bucket: BUCKET_NAME,
                Key: key,
            }),
        )
        const streamPipeline = promisify(pipeline)
        const downloadPath = "/tmp/" + key;
        await promises.mkdir(path.dirname(downloadPath), { recursive: true });
        await streamPipeline(response.Body, createWriteStream(downloadPath));
        console.log(`File downloaded to: ${downloadPath}. Starting representation generation`);

        return {
            statusCode: 200,
            body: JSON.stringify({
                message: 'Execution successful',
                output: 'Downloaded: ' + downloadPath
            })
        }

    } catch (caught) {
        if (caught instanceof NoSuchKey) {
            console.error(`Error from S3 while getting object "${key}" from "${bucketName}". No such key exists.`)
        } else if (caught instanceof S3ServiceException) {
            console.error(`Error from S3 while getting object from ${bucketName}.  ${caught.name}: ${caught.message}`)
        } else {
            throw caught;
        }

        return {
            statusCode: 500,
            body: JSON.stringify({
                error: error.message,
                stderr: error.stderr?.toString()
            })
        }
    }
}