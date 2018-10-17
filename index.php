<?php
  require 'vendor/autoload.php';

  // Create and configure Slim app
  $config = [
    'settings' => [
      'displayErrorDetails' => true,
      'addContentLengthHeader' => false,
      'constants' => [
        'base_url' => 'http://localhost:4200/',
        'static_url' => 'http://localhost:4200/public/',
        'ambiente_csrf' => 'activo',
        'csrf' => [
          'secret' => 'PKBcauXg6sTXz7Ddlty0nejVgoUodXL89KNxcrfwkEme0Huqtj6jjt4fP7v2uF4L',
          'key' => 'csrf_val'
        ],
      ],
      'renderer' => [
        'template_path' => __DIR__,
      ],
    ]
  ];
  // Iniciar la instancia de la aplicación Slim
  $app = new \Slim\App($config);
  // middleware
  $mw_csrf = function ($request, $response, $next) {
    $settings = $this->get('settings');
    $continuar = true;
    if($settings['constants']['ambiente_csrf'] == 'activo'){
      if($request->getHeader($settings['constants']['csrf']['key'])[0] != $settings['constants']['csrf']['secret']){
        $continuar = false;
      }
    }
    if($continuar == true){
      $response = $next($request, $response);
      return $response;
    }else{
      $status = 500;
      $rpta = json_encode(
        [
          'tipo_mensaje' => 'error',
          'mensaje' => [
            'No se puede acceder al recurso',
            'CSRF Token key error'
          ]
        ]
      );
      return $response->withStatus($status)->write($rpta);
    }
  };
  // Container para el error 404
  $container = $app->getContainer();
  $container['notFoundHandler'] = function ($c) {
  return function ($request, $response) use ($c) {
    /**/
      $method = $request->getMethod();
      if($method == 'GET'){
        return $response->withRedirect($c->get('settings')['constants']['base_url'] . 'error/access/404');
      }else{
        $rpta = json_encode(
          [
            'tipo_mensaje' => 'error',
            'mensaje' => [
              'Recurso no disponible',
              'Error 404'
            ]
          ]
        );
        return $c['response']
          ->withStatus(404)
          ->withHeader('Allow', implode(', ', $methods))
          ->withHeader('Content-type', 'text/html')
          ->write($rpta);
      }
    };
  };
  $container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
  };
  // Define app routes
  $app->get('/', function ($request, $response, $args) {
    return $this->renderer->render($response, '/public/index.html');
  });
  $app->get('/error/access/404', function ($request, $response, $args) {
    //return $response->withStatus(404)->write('Error 404 pe');
    return $this->renderer->render($response, '/public/error404.html');
  });
  $app->get('/key', function($request, $response, $args){
    $length = 13;
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $response->withStatus(200)->write($randomString);
  });
  $app->post('/encrypt', function($request, $response, $args){
    $key = $request->getParam('key');
    $data = $request->getParam('data');
    $rpta = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $data, MCRYPT_MODE_CBC, md5(md5($key))));
    return $response->withStatus(200)->write($rpta);
  })->add($mw_csrf);
  $app->post('/decrypt', function($request, $response, $args){
    $key = $request->getParam('key');
    $data = $request->getParam('data');
    $rpta = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), base64_decode($data), MCRYPT_MODE_CBC, md5(md5($key))), "\0");
    return $response->withStatus(200)->write($rpta);
  })->add($mw_csrf);
  // Run app
  $app->run();
