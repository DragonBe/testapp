<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/global.php';

use \OAuth\OAuth2\Service\GitHub;
use \OAuth\Common\Storage\Session;
use \OAuth\Common\Consumer\Credentials;
use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;
use \PHPMentoring\Testapp\Account;
use \PHPMentoring\Testapp\AccountMapper;

session_id('PHPMENTSID');
session_start();

$pdo = new \PDO($config['db']['dsn'], $config['db']['username'], $config['db']['password']);

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

$app->get('/login', function () use ($app, $config, $serviceFactory, $pdo) {
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
            $result['github_id'] = $result['id'];
            $account = new Account();
            $accountMapper = new AccountMapper($pdo);
            $accountMapper->fetchRow($account, array ('`github_id` = ?' => $result['github_id']));
            if (0 === (int) $account->getAccountId()) {
                $account->populate($result);
                $accountMapper->save($account, 'account_id');
            }
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

$app->get('/profile', function () use ($app, $pdo) {
    $session = $_SESSION['github']['account'];
    $interest = array (
        'mentor' => false,
        'apprentice' => false,
    );
    $tags = array ();
    $query = 'SELECT * FROM `account` '
        . 'WHERE `github_id` = ?';
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $session['id']);
    $stmt->execute();
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);

    $interestQuery = 'SELECT `interest` FROM `account_interests` '
        . 'INNER JOIN `interest` USING (`interest_id`) '
        . 'WHERE `account_id` = ?';
    $interestStmt = $pdo->prepare($interestQuery);
    $interestStmt->bindParam(1, $result['account_id']);
    if ($interestStmt->execute()) {
        $interests = $interestStmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($interests as $interestItem) {
            if (isset ($interest[$interestItem['interest']])) {
                $interest[$interestItem['interest']] = true;
            }
        }
    }

    $tagsQuery = 'SELECT `tag` FROM `account_tags` '
        . 'INNER JOIN `tag` USING (`tag_id`) '
        . 'WHERE `account_id` = ?';
    $tagsStmt = $pdo->prepare($tagsQuery);
    $tagsStmt->bindParam(1, $result['account_id']);
    if ($tagsStmt->execute()) {
        $tagList = $tagsStmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($tagList as $tagItem) {
            $tags[] = $tagItem['tag'];
        }
    }

    $_SESSION['github']['account']['account_id'] = $result['account_id'];
    return $app['twig']->render('profile.twig', array (
        'account' => $session,
        'interest' => $interest,
        'tags' => implode(', ', $tags),
    ));
})
->bind('profile');

$app->post('/profile', function (Request $request) use ($app, $pdo) {
    $session = $_SESSION['github']['account'];
    $mentorship = $request->get('mentorship');
    $apprenticeship = $request->get('apprenticeship');
    $interestList = array ();
    if (1 === (int) $mentorship) {
        $interestList[] = 'mentor';
    }
    if (1 === (int) $apprenticeship) {
        $interestList[] = 'apprentice';
    }
    $tags = $request->get('interests');

    // process interests
    $removeStmt = $pdo->prepare('DELETE FROM `account_interests` WHERE `account_id` = ?');
    $removeStmt->bindParam(1, $session['account_id']);
    $removeStmt->execute();
    if (array () !== $interestList) {
        $query = sprintf(
            'SELECT `interest_id` FROM `interest` WHERE `interest` IN (%s)',
            implode(',', array_fill(0, count($interestList), '?'))
        );
        $interestsStmt = $pdo->prepare($query);
        if (true === ($interestsStmt->execute($interestList))) {
            $interests = $interestsStmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($interests as $interest) {
                $insertStmt = $pdo->prepare('INSERT INTO `account_interests` VALUES (?, ?)');
                $insertStmt->bindParam(1, $session['account_id']);
                $insertStmt->bindParam(2, $interest['interest_id']);
                $insertStmt->execute();
            }
        }
    }

    // process tags
    if ('' !== $tags) {
        $tagRemove = $pdo->prepare('DELETE FROM `account_tags` WHERE `account_id` = ?');
        $tagRemove->bindParam(1, $session['account_id']);
        $tagRemove->execute();

        $tagList = explode(',', $tags);
        foreach ($tagList as $tag) {
            $tag = trim($tag);
            $tagCheck = $pdo->prepare('SELECT `tag_id` FROM `tag` WHERE `tag` = ?');
            $tagCheck->bindParam(1, $tag);
            if ($tagCheck->execute()) {
                if (false === ($tagId = $tagCheck->fetchColumn())) {
                    $tagInsert = $pdo->prepare('INSERT INTO `tag` (`tag`) VALUES (?)');
                    $tagInsert->bindParam(1, $tag);
                    $tagInsert->execute();
                    $tagId = $pdo->lastInsertId();
                }
                $tagUpdate = $pdo->prepare('INSERT INTO `account_tags` VALUES (?, ?)');
                $tagUpdate->bindParam(1, $session['account_id']);
                $tagUpdate->bindParam(2, $tagId);
                $tagUpdate->execute();
            }
        }
    }
    return $app->redirect($app['url_generator']->generate('profile'));
})
    ->bind('profile-post');

$app->get('/list', function (Request $request) use ($app, $pdo) {
    $session = $_SESSION['github']['account'];
    $keywords = $request->get('keywords');
    $keywordList = explode(',', $keywords);
    for ($i = 0; $i < count($keywordList); $i++) {
        $keywordList[$i] = trim($keywordList[$i]);
    }
    $query = sprintf(
        'SELECT * FROM `tag` INNER JOIN `account_tags` USING (`tag_id`) INNER JOIN `account` USING (`account_id`) WHERE `tag` IN (%s)',
        implode(',', array_fill(0, count($keywordList), '?'))
    );
    $stmt = $pdo->prepare($query);
    $stmt->execute($keywordList);
    $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    return $app['twig']->render('list.twig', array (
        'account' => $session,
        'list' => $result,
        'keywordList' => implode(', ', $keywordList),
    ));
})
->bind('list');

$app->run();