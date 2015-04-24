<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/global.php';

use \OAuth\OAuth2\Service\GitHub;
use \OAuth\Common\Storage\Session;
use \OAuth\Common\Consumer\Credentials;
use Symfony\Component\HttpFoundation\Response;

session_id('PHPMENTSID');
session_start();
// Starting session management
$sessionOptions = array (
    'save_path' => __DIR__ . '/../data/session',
    'name' => 'PHPMENTSID',
    'cookie_path' => '/',
    'cookie_domain' => $_SERVER['HTTP_HOST'],
    'cookie_secure' => isset ($_SERVER['HTTPS']),
    'cookie_httponly' => true,
);

$app = new Silex\Application();
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views/templates',
));
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
//$app->register(new Silex\Provider\SessionServiceProvider($sessionOptions));
$app['debug'] = true;

// Defining the OAuth settings
$serviceFactory = new \OAuth\ServiceFactory();

// Home page
$app->get('/', function () use ($app) {
    $account = array (
        'avatar_url' => null,
        'login' => null,
        'name' => null,
        'authenticated' => false,
    );

    if (isset ($_SESSION['github'])) {
        $account = $_SESSION['github']['account'];
        $account['authenticated'] = true;
    }

    return $app['twig']->render('home.twig', array (
        'account' => $account,
    ));
})
->bind('home');

$app->get('/login', function () use ($app, $config, $serviceFactory) {
    $uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
    $currentUri = $uriFactory->createFromSuperGlobalArray($_SERVER);
    $currentUri->setQuery('');
    // Session storage
    $storage = new Session();
    // Setup the credentials for the requests
    $credentials = new Credentials(
        $config['github']['api_key'],
        $config['github']['api_secret'],
        $currentUri->getAbsoluteUri()
    );
    /** @var GitHub $gitHub */
    $gitHub = $serviceFactory->createService('GitHub', $credentials, $storage, array('user'));
    $message = null;

    // Do the OAuth dance
    if (!empty($_GET['code'])) {
        // This was a callback request from github, get the token
        $gitHub->requestAccessToken($_GET['code']);
        $result = json_decode($gitHub->request('user'), true);
        $result['authenticated'] = true;
        $_SESSION['github'] = array (
            'token' => $_SESSION['lusitanian_oauth_token']['GitHub'],
            'account' => $result,
        );

        if (isset ($result['id'])) {
            return $app->redirect($app['url_generator']->generate('profile'));
        }
    } else {
        $url = $gitHub->getAuthorizationUri();
        $response = new Response(
            '',
            301,
            array ('Location' => $url->__toString())
        );
        return $response;
    }
})
->bind('login');

$app->get('/logout', function () use ($app) {
    $cookie = session_get_cookie_params();
    $cookie['lifetime'] = time() - 3600;
    setcookie(session_name(), '', $cookie['lifetime'], $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
    session_destroy();
    return $app->redirect($app['url_generator']->generate('home'));
})
->bind('logout');

$app->get('/profile', function () use ($app) {
    return $app['twig']->render('profile.twig', array ('account' => $_SESSION['github']['account']));
})
->bind('profile');

$app->run();