<?php
use google\appengine\api\cloud_storage\CloudStorageTools;
use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\HttpFoundation\Request;
$app = new Application();
$app->register(new TwigServiceProvider());
$app['twig.path'] = [ __DIR__ ];
$app->get('/', function () use ($app) {
    $my_bucket = $app['bucket_name'];
    $default_bucket = CloudStorageTools::getDefaultGoogleStorageBucketName();
    if ($my_bucket == '<your-bucket-name>') {
        return 'Set <code>&lt;your-bucket-name&gt;</code> to the name of your '
            . 'cloud storage bucket in <code>index.php</code>';
        }
        if (!in_array('gs', stream_get_wrappers())) {
            return 'This application can only run in AppEngine or the Dev AppServer environment.';
        }
    
        # [START user_upload]
        $options = ['gs_bucket_name' => $my_bucket];
        $upload_url = CloudStorageTools::createUploadUrl('/upload/handler', $options);
        # [END user_upload]
    
        $buckets = [
            $my_bucket => ['hello', 'hello_options', 'hello_stream', 'hello_caching', 'hello_metadata'],
            $default_bucket => ['hello_default', 'hello_default_stream'],
        ];
        $params['upload_url'] = $upload_url;
        foreach ($buckets as $bucket => $files) {
            foreach ($files as $file) {
                $params[$file] = '';
                if (file_exists("gs://${bucket}/${file}.txt")) {
                    $params[$file] = file_get_contents("gs://${bucket}/${file}.txt");
                }
            }
        }
    
        // load file metadata
        $content_type = '';
        $metadata = [];
        if (file_exists("gs://${my_bucket}/hello_metadata.txt")) {
            # [START read_metadata]
            $fp = fopen("gs://${my_bucket}/hello_metadata.txt", 'r');
            $content_type = CloudStorageTools::getContentType($fp);
            $metadata = CloudStorageTools::getMetaData($fp);
            # [END read_metadata]
        }
    
        $params['metadata'] = $metadata;
        $params['metadata_content_type'] = $content_type;
    
        return $app['twig']->render('storage.html.twig', $params);
    });
    $app->get('/file.txt', function () use ($app) {
        $filePath = __DIR__ . '/file.txt';
        # [START read_simple]
        $fileContents = file_get_contents($filePath);
        # [END read_simple]
        return $fileContents;
    });
    $app->post('/write', function (Request $request) use ($app) {
        $newFileContent = $request->get('content');
        $my_bucket = $app['bucket_name'];
        # [START write_simple]
        file_put_contents("gs://${my_bucket}/hello.txt", $newFileContent);
        # [END write_simple]
        return $app->redirect('/');
    });
    $app->post('/write/options', function (Request $request) use ($app) {
        $newFileContent = $request->get('content');
        $my_bucket = $app['bucket_name'];
        # [START write_options]
        $options = ['gs' => ['Content-Type' => 'text/plain']];
        $context = stream_context_create($options);
        file_put_contents("gs://${my_bucket}/hello_options.txt", $newFileContent, 0, $context);
        # [END write_options]
        return $app->redirect('/');
    });
    $app->post('/write/stream', function (Request $request) use ($app) {
        $newFileContent = $request->get('content');
        $my_bucket = $app['bucket_name'];
        # [START write_stream]
        $fp = fopen("gs://${my_bucket}/hello_stream.txt", 'w');
        fwrite($fp, $newFileContent);
        fclose($fp);
        # [END write_stream]
        return $app->redirect('/');
    });
    $app->post('/write/caching', function (Request $request) use ($app) {
        $newFileContent = $request->get('content');
        $my_bucket = $app['bucket_name'];
        # [START write_caching]
        $options = [
            'gs' => [
                'enable_cache' => true,
                'enable_optimistic_cache' => true,
                'read_cache_expiry_seconds' => 300,
            ]
        ];
        $context = stream_context_create($options);
        file_put_contents("gs://${my_bucket}/hello_caching.txt", $newFileContent, 0, $context);
        return $app->redirect('/');
    });
    $app->post('/write/default', function (Request $request) use ($app) {
        $newFileContent = $request->get('content');
        # [START write_default]
        $default_bucket = CloudStorageTools::getDefaultGoogleStorageBucketName();
        file_put_contents("gs://${default_bucket}/hello_default.txt", $newFileContent);
        # [END write_default]
        return $app->redirect('/');
    });
    $app->post('/write/default/stream', function (Request $request) use ($app) {
        $newFileContent = $request->get('content');
        $default_bucket = CloudStorageTools::getDefaultGoogleStorageBucketName();
        $fp = fopen("gs://${default_bucket}/hello_default_stream.txt", 'w');
        fwrite($fp, $newFileContent);
        fclose($fp);
        return $app->redirect('/');
    });
    $app->get('/serve', function () use ($app) {
        $my_bucket = $app['bucket_name'];
        if (!file_exists("gs://${my_bucket}/serve.txt")) {
            file_put_contents("gs://${my_bucket}/serve.txt",
            return '/';
        });
        $app->get('/write/public', function () use ($app) {
            $my_bucket = $app['bucket_name'];
            $publicFileText = sprintf('new file written at %s', date('Y-m-d H:i:s'));
            # [START write_public]
            $options = ['gs' => ['acl' => 'public-read']];
            $context = stream_context_create($options);
            $fileName = "gs://${my_bucket}/public_file.txt";
            file_put_contents($fileName, $publicFileText, 0, $context);
        
            $publicUrl = CloudStorageTools::getPublicUrl($fileName, false);
            return $app->redirect($publicUrl);
        });
        $app->post('/upload/handler', function () use ($app) {
            $my_bucket = $app['bucket_name'];
            $file_name = $_FILES['uploaded_files']['name'];
            $temp_name = $_FILES['uploaded_files']['tmp_name'];
            move_uploaded_file($temp_name, "gs://${my_bucket}/${file_name}.txt");
            return sprintf('Your file "%s" has been uploaded.', $file_name);
        });
        $app->get('/serve/image', function () use ($app) {
            $my_bucket = $app['bucket_name'];
            if (!file_exists("gs://${my_bucket}/image.jpg")) {
                copy(__DIR__ . '/image.jpg', "gs://${my_bucket}/image.jpg");
            }
            $options = ['size' => 400, 'crop' => true];
            $image_file = "gs://${my_bucket}/image.jpg";
            $image_url = CloudStorageTools::getImageServingUrl($image_file, $options);
            return $app->redirect($image_url);
        });
        return $app;            