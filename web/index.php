<?php

require('../vendor/autoload.php');

$app = new Silex\Application();

use Symfony\Component\HttpFoundation\Request;

$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// Register the Postgres database add-on
$dbopts = parse_url(getenv('DATABASE_URL'));
$app->register(new Herrera\Pdo\PdoServiceProvider(),
  array(
    'pdo.dsn' => 'pgsql:dbname='.ltrim($dbopts["path"],'/').';host='.$dbopts["host"],
    'pdo.port' => $dbopts["port"],
    'pdo.username' => $dbopts["user"],
    'pdo.password' => $dbopts["pass"]
  )
);

// Our web handlers
$app->post('/account-creation', function() use($app) {
    $type = "admin";

#    $name = $request->post('inputName');
#    $email = $request->post('inputEmail');
#    $password = $request->post('inputPassword');

    $name = $_POST['inputName'];
    $email = $_POST['inputEmail'];
    $password = $_POST['inputPassword'];

    echo $name;
    echo $email;
    echo $password;

    // Save account information into database
    #$stmt = $app['pdo']->prepare("INSERT INTO user SET type = ''".$type."' name= '".$name."' email='".$email."' password='".$password."';");
    $stmt = $app['pdo']->prepare("INSERT INTO user VALUES (DEFAULT, $type, $name, $email, $password, DEFAULT);");
    $stmt->execute();
    return $app['twig']->render('create-success.html');
});

$app->post('/account-login', function (Request $request) {
    $app['monolog']->addDebug('logging output.');
    $email = $request->get('email');
    $password = $request->get('password');

    // Save account information into database
    $st = $app['db']->prepare("SELECT password FROM user WHERE email $email;");
    $st->execute();

    $passGrab = $st->fetch(PDO::FETCH_ASSOC);

    if ($password == $passGrab) {
      return $app['twig']->render('login-success.html');
    }
    // Return account creation failure
    else {
      return $app['twig']->render('login-failure.html');
    }

    $names = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $app['monolog']->addDebug('Row ' . $row['name']);
    $names[] = $row;
  }

  return $app['twig']->render('database.twig', array(
    'names' => $names
  ));
});

$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('index.html');
});

$app->get('/create-account', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('create-account.html');
});

$app->get('/calendar', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('calendar.html');
});

$app->get('/login', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('login.html');
});

$app->get('/map', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('map.html');
});

$app->get('/search', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('search.html');
});

$app->run();
