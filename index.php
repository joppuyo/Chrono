<?php

require "vendor/autoload.php";
require "config.php";

session_start();

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

$view = $app->view();
$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
);

$app->hook('slim.before.dispatch', function() use ($app) {

});

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
  public function rememberMeTokens()
  {
    return $this->hasMany('RememberMeToken');
  }
}

class Time extends \Illuminate\Database\Eloquent\Model {}

class RememberMeToken extends \Illuminate\Database\Eloquent\Model {}

$app->user = function () {
  if (isset($_SESSION['user_id'])) {
    return User::find($_SESSION['user_id']);
  } else {
    return null;
  }
};


$app->get('/', function () use ($app) {
  if ($app->user) {
    $app->redirectTo('track');
  } else {
    $app->redirectTo('login');
  }
})->name("root");

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

        $token = new RememberMeToken();

        $factory = new RandomLib\Factory;
        $generator = $factory->getGenerator(new SecurityLib\Strength(SecurityLib\Strength::MEDIUM));
        $tokenString = $generator->generateString(128);
        $app->setCookie('chrono_remember_me_token',$tokenString,'1 year');

        $token->token = $tokenString;

        $user->rememberMeTokens()->save($token);
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
  $app->render('track.twig');
})->name('track');

$app->get('/log/', $isLoggedIn($app), function () use ($app) {
  $user = User::find($_SESSION['user_id']);
  $user->load('times');
  $app->render('log.twig', ['times' => $user->times]);
})->name('log');

$app->get('/user/', $isLoggedIn($app), function () {
  echo "user";
})->name('user');

$app->post('/api/time/', function () use ($app) {
  if(!isset($_SESSION['user_id'])){
    $app->halt(403);
  } else {
    $json = json_decode($app->request()->getBody());
    $time = new Time;
    $unixStartTime = strtotime($json->start_datetime);
    $unixStartTime = floor($unixStartTime/(60*5))*(60*5);
    $unixEndTime = strtotime($json->end_datetime);
    $unixEndTime = ceil($unixEndTime/(60*5))*(60*5);
    $time->start_datetime = \Carbon\Carbon::createFromTimestamp($unixStartTime)->toDateTimeString();
    $time->end_datetime = \Carbon\Carbon::createFromTimestamp($unixEndTime)->toDateTimeString();
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
    $table->integer('user_id')->unsigned();
    $table->dateTime('start_datetime');
    $table->dateTime('end_datetime');
    $table->timestamps();
    $table->foreign('user_id')->references('id')->on('users');
  });
  Capsule::schema()->create('remember_me_tokens', function($table)
  {
    $table->increments('id');
    $table->integer('user_id')->unsigned();
    $table->string('token')->unique();
    $table->timestamps();
    $table->foreign('user_id')->references('id')->on('users');
  });
});

$app->run();