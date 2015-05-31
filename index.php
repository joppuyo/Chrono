<?php

require "vendor/autoload.php";
require "config.php";

session_start();

$app = new \Slim\Slim();

use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => DB_HOST,
    'database'  => DB_NAME,
    'username'  => DB_USERNAME,
    'password'  => DB_PASSWORD,
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

$app = new \Slim\Slim(array(
    'view' => new \Slim\Views\Twig()
));

$app->view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
);

$isLoggedIn = function($app) {
  return function () use ($app) {
    if (!isset($_SESSION['user_id'])) {
      $app->redirectTo('login');
    }
  };
};

class User extends \Illuminate\Database\Eloquent\Model {
  public function times()
  {
    return $this->hasMany('Time');
  }
}

class Time extends \Illuminate\Database\Eloquent\Model {}

$app->user = function () {
  if ($_SESSION['user_id']) {
    return User::find($_SESSION['user_id']);
  } else {
    return null;
  }
};


$app->get('/', function () {
  echo "Hello world";
});

$app->map('/login/', function () use ($app) {
  if ($app->request->isPost()) {
    $email = $app->request->post('email');
    $password = $app->request->post('password');
    $user = User::where('email', $email)->get()->first();
    if (is_null($user)) {
      $app->flashNow('error', 'Email does not exist');
    } else {
      if (password_verify($password, $user->password)){
        $_SESSION['user_id'] = $user->id;
        $app->redirectTo('track');
      } else {
        $app->flashNow('error', 'Password incorrect');
      }
    }

  }
  $app->render("login.twig");
})->via('GET', 'POST')->name('login');

$app->map('/signup/', function () use ($app) {
  if ($app->request->isPost()) {
    $user = new User;
    $user->email = $app->request->post('email');
    $user->password = password_hash($app->request->post('password'),PASSWORD_BCRYPT);
    $user->save();
  }
  $app->render("signup.twig");
})->via('GET', 'POST');

$app->get('/track/', $isLoggedIn($app), function () use ($app) {
  echo $app->user->email;
  $app->render('track.twig');
})->name('track');

$app->get('/log/', $isLoggedIn($app), function () {
  echo "log";
});

$app->post('/api/time/', function () use ($app) {
  if(!isset($_SESSION['user_id'])){
    $app->halt(403);
  } else {
    $json = json_decode($app->request()->getBody());
    $time = new Time;
    $time->start_datetime = $json->start_datetime;
    $time->end_datetime = $json->end_datetime;
    $user = $app->user;
    $user->times()->save($time);
    $app->halt(200);
  }
});

$app->get('/create_db', function () {
  Capsule::schema()->create('users', function($table)
  {
    $table->increments('id');
    $table->string('email')->unique();
    $table->string('password');
    $table->timestamps();
  });
  Capsule::schema()->create('times', function($table)
  {
    $table->increments('id');
    $table->dateTime('start_datetime');
    $table->dateTime('end_datetime');
    $table->timestamps();
    $table->integer('user_id')->unsigned();
    $table->foreign('user_id')->references('id')->on('users');
  });
});

$app->run();