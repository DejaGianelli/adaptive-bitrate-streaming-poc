const { S3Client, S3ServiceException, NoSuchKey, GetObjectCommand } = require('@aws-sdk/client-s3')
const { Upload } = require('@aws-sdk/lib-storage')
const { promisify } = require('util')
const { createWriteStream, promises, readdirSync, createReadStream } = require('fs')
const path = require('path')
const { pipeline } = require('stream')
const { execSync } = require('child_process');

let BUCKET_NAME = process.env.BUCKET
let AWS_REGION = process.env.AWS_REGION
let BUCKET_HOST = process.env.BUCKET_HOST

/**
 * Lambda handler for processing videos in S3.
 * @param {Object} event - Input event containing media details
 * @param {string} event.body = Json Body
 * @returns {Promise<string>} Success message
 */
exports.handler = async (event) => {
    try {
        await process(event)
        return {
            statusCode: 200,
            body: JSON.stringify({
                message: 'Execution successful',
                output: 'Downloaded'
            })
        }
    } catch (caught) {
        if (caught instanceof NoSuchKey) {
            console.error(`Error from S3 while getting object "${key}" from "${BUCKET_NAME}". No such key exists.`)
        } else if (caught instanceof S3ServiceException) {
            console.error(`Error from S3 while getting object from ${BUCKET_NAME}.  ${caught.name}: ${caught.message}`)
        } else {
            console.error(`Error ${caught.name}: ${caught.message}`)
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

async function process(event) {

    const body = JSON.parse(event.body);
    const key = body.key;

    const s3Client = new S3Client({
        region: AWS_REGION
    })

    const response = await s3Client.send(
        new GetObjectCommand({
            Bucket: BUCKET_NAME,
            Key: key,
        }),
    )
    const streamPipeline = promisify(pipeline)
    const downloadedPath = "/tmp/" + key
    const downloadedDir = path.dirname(downloadedPath)
    await promises.mkdir(downloadedDir, { recursive: true })
    await streamPipeline(response.Body, createWriteStream(downloadedPath))

    console.log(`File downloaded to: ${downloadedPath}. Starting representation generation`)

    let bitRateLadder = {
        "240": { "resolution": "240p", "bitrate": "500k", "maxrate": "1000k" },
        "360": { "resolution": "360p", "bitrate": "1000k", "maxrate": "2000k" },
        "480": { "resolution": "480p", "bitrate": "1500k", "maxrate": "3000k" },
        "720": { "resolution": "720p", "bitrate": "3000k", "maxrate": "6000k" },
        "1080": { "resolution": "1080p", "bitrate": "5000k", "maxrate": "10000k" },
    }

    Object.entries(bitRateLadder).forEach(function ([key,]) {
        if (Number(key) > 720) {
            delete bitRateLadder[key]
        }
    })

    // Creating representation dirs
    Object.entries(bitRateLadder).forEach(async function ([, value]) {
        await promises.mkdir(downloadedDir + "/" + value.resolution, { recursive: true })
    })

    const cmd = buildFFmpegCommand(downloadedPath, downloadedDir, bitRateLadder, key)

    console.log("FFMPEG cmd: " + cmd);

    execSync(cmd, (error, stdout, stderr) => {
        if (error) {
            console.error(`Error: ${error.message}`);
            return;
        }
        if (stderr) {
            console.error(`FFmpeg stderr: ${stderr}`);
        }
        console.log(`Output: ${stdout}`);
    });

    console.log(`Starting uploading DASH files`)

    const files = readdirSync(downloadedDir, { withFileTypes: true })
        .filter(function (file) {
            if (file.isDirectory()) {
                return false
            }
            if (file.name == "upload.mp4") {
                return false
            }
            return true
        })

    await Promise.all(files.map(file => {

        const fullPath = `${downloadedDir}/${file.name}`
        const s3Key = `${path.dirname(key)}/${file.name}`

        console.log(`Uploading file ${fullPath} to S3 with key ${s3Key}`)

        return new Upload({
            client: s3Client,
            params: {
                Bucket: BUCKET_NAME,
                Key: s3Key,
                Body: createReadStream(fullPath),
            }
        }).done()
    }))

    console.log('Video processing completed!');

    return {
        statusCode: 200,
        body: JSON.stringify({
            message: 'Execution successful',
            output: 'Downloaded: ' + downloadedPath
        })
    }
}

function buildFFmpegCommand(uploadFile, uploadsDir, bitRateLadder, key) {
    const objectIdRootPath = key.split("/")[0]
    const pathParts = path.parse(uploadFile)
    const representationsCount = Object.keys(bitRateLadder).length

    let cmd = '( ';

    // Part 1: Create representation videos
    cmd += `/var/task/ffmpeg/ffmpeg -i ${uploadFile} `
    cmd += `-filter_complex "`
    cmd += `[0:v:0]split=${representationsCount}`

    // Add split outputs
    for (const [res] of Object.entries(bitRateLadder)) {
        cmd += `[v${res}]`
    }

    cmd += ';'

    // Add scale filters
    for (const [res] of Object.entries(bitRateLadder)) {
        cmd += `[v${res}]scale=-2:${res}[v${res}out];`
    }

    cmd += '" '
    cmd += '-c:a:0 aac -b:a:0 128k '
    cmd += '-g 48 -keyint_min 48 '
    cmd += '-crf 22 '
    cmd += '-profile:v high '
    cmd += '-movflags +faststart '

    // Add output configurations for each representation
    for (const [res, configs] of Object.entries(bitRateLadder)) {
        cmd += `-map [v${res}out] -map 0:a:0 `
        cmd += '-c:v:0 libx264 '
        cmd += `-b:v:0 ${configs.bitrate} `
        cmd += `-maxrate ${configs.bitrate} `
        cmd += `-bufsize ${configs.maxrate} `
        cmd += `-f ${pathParts.ext.slice(1)} `  // Remove dot from extension
        cmd += `${uploadsDir}/${res}p/output.${pathParts.ext.slice(1)} `
    }

    cmd += ') && ( '

    // Part 2: Create DASH segments
    cmd += '/var/task/ffmpeg/ffmpeg '

    // Add input files
    for (const [res] of Object.entries(bitRateLadder)) {
        cmd += `-i ${uploadsDir}/${res}p/output.mp4 `
    }

    // Add mapping
    for (let i = 0; i < representationsCount; i++) {
        cmd += `-map ${i} `
    }

    cmd += '-c copy '
    cmd += '-use_timeline 1 -use_template 1 '
    cmd += '-adaptation_sets "id=0,streams=v id=1,streams=a" '
    cmd += '-init_seg_name "init-stream\\$RepresentationID\\$.m4s" '
    cmd += '-media_seg_name "chunk-stream\\$RepresentationID\\$-\\$Number%05d\\$.m4s" '
    cmd += `-f dash ${uploadsDir}/manifest.mpd ) && `

    // Part 3: Modify manifest

    const initializationSedUrl = `${BUCKET_HOST}/${objectIdRootPath}/init-`
    const mediaSedUrl = `${BUCKET_HOST}/${objectIdRootPath}/chunk-`

    cmd += `sed -i 's|initialization="init-|initialization="${initializationSedUrl}|g' ${uploadsDir}/manifest.mpd && `;
    cmd += `sed -i 's|media="chunk-|media="${mediaSedUrl}|g' ${uploadsDir}/manifest.mpd`;

    return cmd;
}