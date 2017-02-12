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

$app->run();
