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
use Aws\Lambda\LambdaClient;
use Cake\Log\Log;
use Cake\Routing\Router;
use Laminas\Diactoros\Stream;

class VideosController extends AppController
{
    public function createPage(string ...$path): ?Response
    {
        return $this->render("create", "main");
    }

    public function streaming(S3Client $s3Client, string ...$path): ?Response
    {
        try {
            $key = $this->request->getQuery('f');
            $bucket = Configure::read('AWS.s3.buckets.videos.name');

            $result = $s3Client->getObject([
                'Bucket' => $bucket,
                'Key' => $key,
                '@http' => [
                    'stream' => true,
                ],
            ]);

            $resource = $result['Body']->detach();
            $body = new Stream($resource);

            return $this->response
                    ->withType($result['ContentType'])
                    ->withHeader('Content-Length', $result['ContentLength'])
                    ->withBody($body);

        } catch (AwsException $e) {
            return $this->response
                ->withStatus(404)
                ->withStringBody('File not found');
        }
    }

    public function manifest(S3Client $s3Client, string ...$path): ?Response
    {
        try {
            if ($this->request->getMethod() == "HEAD") {
                return $this->response->withType("application/dash+xml");
            }
            
            $videoId = $this->request->getParam('videoId');
            $video = $this->fetchTable('Videos')
                ->find()
                ->where(['id' => $videoId])
                ->first();
            
            $key = $video->getObjectIdRootPath() . '/manifest.mpd';
            $bucket = Configure::read('AWS.s3.buckets.videos.name');

            $result = $s3Client->getObject([
                'Bucket' => $bucket,
                'Key' => $key,
                '@http' => [
                    'stream' => true,
                ],
            ]);

            $resource = $result['Body']->detach();
            $body = new Stream($resource);

            return $this->response->withType("application/dash+xml")
                    ->withBody($body);

        } catch (AwsException $e) {
            return $this->response
                ->withStatus(404)
                ->withStringBody($e->getMessage());
        }
    }

    public function view(S3Client $s3Client, string ...$path): ?Response
    {
        $videoId = $this->request->getParam('videoId');
        $video = $this->fetchTable('Videos')
            ->find()
            ->where(['id' => $videoId])
            ->first();

        $manifestUrl = Router::url([
            '_name' => 'videos:manifest',
            'videoId' => $videoId,
        ]);
        $this->set('videoTitle', $video->title);
        $this->set('videoManifestUrl', $manifestUrl);

        return $this->render("view", "main");
    }

    public function create(S3Client $s3Client, LambdaClient $lambdaClient, string ...$path): ?Response
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
            $bucket = Configure::read('AWS.s3.buckets.videos.name');

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

            $videosTable->getConnection()->transactional(function () use ($videosTable, $video, $key, $lambdaClient) {
                $videosTable->save($video);

                Log::debug('Invoking lambda to process video.');

                $body = json_encode(["key" => $key]);
                $lambdaClient->invoke([
                    'InvocationType' => 'Event',
                    'FunctionName' => Configure::read('AWS.lambda.video-processing-lambda.name'),
                    'Payload' => json_encode(["body" => $body]),
                    'LogType' => "None",
                ]);
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
        $bucket = Configure::read('AWS.s3.buckets.videos.name');
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
