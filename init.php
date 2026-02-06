<?php
use Pixie\Connection;
use Pixie\QueryBuilder\QueryBuilderHandler;
use Aws\S3\S3Client;
use \watrlabs\authentication;
global $dotenv;
global $db;
global $currentuser;
global $s3_client;

spl_autoload_register(function ($class_name) {
    $directory = __DIR__ . '/classes/';
    $class_name = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
    $file = $directory . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
    else {
        throw new ErrorException("Failed to include class $class_name");
    }
});

// ВИПРАВЛЕНИЙ ОБРОБНИК ПОМИЛОК ДЛЯ PHP 8.4
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Ігноруємо зауваження про динамічні властивості, які викликає Pixie
    if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
        return true;
    }
    // Ігноруємо дрібні зауваження, щоб сайт не падав
    if ($errno === E_NOTICE || $errno === E_STRICT) {
        return true;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $dotenv->load();
} catch (Exception $e) {
    // Якщо файл .env не знайдено або він недоступний, ми побачимо це в логах Render
    error_log("RENDER DEBUG: Could not load .env file! Error: " . $e->getMessage());
}

// ПЕРЕВІРКА КОНКРЕТНОЇ ЗМІННОЇ
if (!isset($_ENV["DB_HOST"])) {
    error_log("RENDER DEBUG: .env loaded, but DB_HOST is missing. Check your Secret File content.");
} else {
    error_log("RENDER DEBUG: .env is working! DB_HOST is: " . $_ENV["DB_HOST"]);
}

try {

    $credentials = new Aws\Credentials\Credentials($_ENV["R2_KEY_ID"], $_ENV["R2_SECRET"]);

    $options = [
        'region' => 'auto',
        'endpoint' => "https://".$_ENV["R2_ACCOUNT_ID"].".r2.cloudflarestorage.com",
        'version' => 'latest',
        'credentials' => $credentials,
        'request_checksum_calculation' => 'when_required',
        'response_checksum_validation' => 'when_required',
        'use_aws_shared_config_files' => false,
    ];

    $s3_client = new Aws\S3\S3Client($options); // this is our s3/r2 connection.


    $config = [
        'driver'    => 'mysql',
        'host'      => $_ENV["DB_HOST"],
        'database'  => $_ENV["DB_NAME"],
        'username'  => $_ENV["DB_USER"],
        'password'  => $_ENV["DB_PASS"],
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '', // if you have a prefix for all your tables.
        'options'   => [
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            // PDO::MYSQL_ATTR_COMPRESS => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        ]
    ];

    $connection = new Connection('mysql', $config);
    $db = $connection->getQueryBuilder(); 

    $auth = new authentication();
    if($auth->hasaccount()){
        $currentuser = $auth->getuserinfo($_COOKIE["_ROBLOSECURITY"]);
    } else {
        $currentuser = null;
    }
    
} catch (PDOException $e){
    require("../views/really_bad_500.php");
    die();
}

date_default_timezone_set('America/New_York');
