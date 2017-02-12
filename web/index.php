<?php

require('../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// Add PDO Connection
$dbopts = parse_url(getenv('DATABASE_URL'));
$app->register(new Herrera\Pdo\PdoServiceProvider(),
               array(
                   'pdo.dsn' => 'pgsql:dbname='.ltrim($dbopts["path"],'/').';host='.$dbopts["host"] . ';port=' . $dbopts["port"],
                   'pdo.username' => $dbopts["user"],
                   'pdo.password' => $dbopts["pass"]
               )
);

// Our web handlers
$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('index.html');
});

$app->get('/create-account', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('caccount.html');
});

$app->get('/calendar', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('cal.html');
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

$app->get('/admin', function() use($app) {
  $st = $app['pdo']->prepare('SELECT name FROM test_table');
  $st->execute();

  $names = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $app['monolog']->addDebug('Row ' . $row['id']);
    $names[] = $row;
  }

  return $app['twig']->render('database.twig', array(
    'names' => $names
  ));
});

$app->run();
