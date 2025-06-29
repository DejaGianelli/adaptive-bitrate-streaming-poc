<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Response;
use Aws\S3\S3Client;
use Cake\Core\Configure;
use Cake\Validation\Validator;
use DateTime;
use Aws\Exception\AwsException;
use Ramsey\Uuid\Uuid;
use Cake\Http\Client;
use Cake\Log\Log;

class VideosController extends AppController
{
    public function createPage(string ...$path): ?Response
    {
        return $this->render("create", "main");
    }

    public function view(S3Client $s3Client, string ...$path): ?Response
    {
        $videoId = $this->request->getParam('videoId');
        $video = $this->fetchTable('Videos')
            ->find()
            ->where(['id' => $videoId])
            ->first();

        $manifestUrl = Configure::read('AWS.s3.videoBucketHost') . "/" . $video->getObjectIdRootPath() . "/manifest.mpd";
        $this->set('videoTitle', $video->title);
        $this->set('videoManifestUrl', $manifestUrl);

        return $this->render("view", "main");
    }

    public function create(S3Client $s3Client, string ...$path): ?Response
    {
        try {
            $isPost = $this->request->is('post');
            if (!$isPost) {
                throw new MethodNotAllowedException();
            }

            $validator = new Validator();
            $validator->requirePresence('key');

            $errors = $validator->validate($this->request->getData());
            if (!empty($errors)) {
                $errorDto = new ProblemDetails('Invalid data in the request', 400, $this->formatValidationErrors($errors));
                return $this->response
                    ->withType('application/json')
                    ->withStatus(400)
                    ->withStringBody(json_encode($errorDto->toArray()));
            }

            $key = $this->request->getData('key');
            $bucket = Configure::read('AWS.s3.bucket');

            $s3Client->headObject([
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);

            $videosTable = $this->fetchTable('Videos');
            $video = $videosTable->newEmptyEntity();

            $video->id = Uuid::uuid4()->toString();
            $video->title = 'Default Title';
            $video->object_id = $key;
            $video->status = "pending_processing";

            $videosTable->getConnection()->transactional(function () use ($videosTable, $video, $key) {
                $videosTable->save($video);

                $http = new Client();
                $host = Configure::read('AWS.lambda.video-processing-lambda.host');
                Log::debug('Calling lambda to process video. Url: ' . $host);
                $http->post(
                    $host,
                    json_encode(["key" => $key]),
                    [
                        'timeout' => 30,
                        'type' => 'json',
                        'headers' => [
                            'Accept' => 'application/json',
                            'X-Amz-Invocation-Type' => 'Event'
                        ]
                    ]
                );
            });

            return $this->response->withStatus(204)->withType('application/json');
        } catch (AwsException $e) {
            $errorDto = new ProblemDetails($e->getMessage(), 400, $this->formatValidationErrors($errors));
            Log::error('Error: ' . $e->getMessage());
            return $this->response
                ->withType('application/json')
                ->withStatus(400)
                ->withStringBody(json_encode($errorDto->toArray()));
        } catch (\Exception $e) {
            $errorDto = new ProblemDetails("Internal Server Error", 500, $this->formatValidationErrors($errors));
            Log::error('Error: ' . $e->getMessage());
            return $this->response
                ->withType('application/json')
                ->withStatus(500)
                ->withStringBody(json_encode($errorDto->toArray()));
        }
    }

    public function createPreSignedUrl(S3Client $s3Client, string ...$path): ?Response
    {
        $validator = new Validator();
        $validator->requirePresence('mimeType')
            ->add('mimeType', 'custom', [
                'rule' => ['custom', '/^video\/mp4$/'],
                'message' => 'MIME type not supported'
            ]);

        $errors = $validator->validate($this->request->getData());
        if (!empty($errors)) {
            $errorDto = new ProblemDetails('Invalid data in the request', 400, $this->formatValidationErrors($errors));
            return $this->response
                ->withType('application/json')
                ->withStatus(400)
                ->withStringBody(json_encode($errorDto->toArray()));
        }

        $expiration = new DateTime("+5 minutes");
        $key = Uuid::uuid4()->toString() . "/upload.mp4";
        $bucket = Configure::read('AWS.s3.bucket');
        $command = $s3Client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => 'video/mp4',

        ]);
        $request = $s3Client->createPresignedRequest($command, $expiration);
        try {
            $presignedUrl = (string) $request->getUri();
        } catch (AwsException $exception) {
            $error = new ProblemDetails('Internal server error', 500);
            return $this->response
                ->withType('application/json')
                ->withStatus(500)
                ->withStringBody(json_encode($error->toArray()));
        }
        return $this->response
            ->withType('application/json')
            ->withStatus(201)
            ->withStringBody(json_encode(["url" => $presignedUrl, "key" => $key]));
    }
}
