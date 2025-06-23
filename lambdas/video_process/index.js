const { execSync } = require('child_process');

exports.handler = async (event) => {
    try {
        // Execute the binary (must be in the same directory or provide full path)
        const result = execSync('./ffmpeg/ffmpeg -version').toString();

        return {
            statusCode: 200,
            body: JSON.stringify({
                message: 'Execution successful',
                output: result
            })
        };
    } catch (error) {
        return {
            statusCode: 500,
            body: JSON.stringify({
                error: error.message,
                stderr: error.stderr?.toString()
            })
        };
    }
};