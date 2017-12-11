<?php

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

Stripe\Stripe::setApiKey(getenv('STRIPE_TEST_SECRET_KEY'));

$app = new Silex\Application();
$app['debug'] = true;

$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});

$app->get('/', function () use ($app) {
    return new Response('Great, your backend is set up. Now you can configure the Stripe example apps to point here.', 200);
});

$app->post('/ephemeral_keys', function (Request $request) use ($app) {
  if (!isset($request->request->get('api_version'))) {
      return new Response('Error creating ephemeral key', 400);
  }
  try {
    $key = \Stripe\EphemeralKey::create(
      array("customer" => $request->request->get('customer_id')),
      array("stripe_version" => $request->request->get('api_version'))
    );
    header('Content-Type: application/json');
    return new Response(json_encode($key), 200);
  } catch (Exception $e) {
      return new Response('Error creating ephemeral key: '.$e, 500);
  }
});

$app->post('/charge', function (Request $request) use ($app) {
  try {
    $charge = \Stripe\Charge::create(array(
      "amount" => $request->request->get('amount'), // Convert amount in cents to dollar
      "currency" => 'usd',
      "customer" => $request->request->get('customer_id'),
      "source" => $request->request->get('source'),
      "shipping" => $request->request->get('shipping'),
      "description" => 'Example Charge')
    );

    // Check that it was paid:
    if ($charge->paid == true) {
      $response = array( 'status'=> 'Success', 'message'=>'Payment has been charged!!' );
    } else { // Charge was not paid!
      $response = array( 'status'=> 'Failure', 'message'=>'Your payment could NOT be processed because the payment system rejected the transaction. You can try again or use another card.' );
    }
    header('Content-Type: application/json');
    return new Response(json_encode($response), 200);

  } catch(\Stripe\Error\Card $e) {
    return new Response('Error creating charge: '.$e, 500);
  }
});


$app->post('/webhook', function (Request $request) use ($app) {
  $event = json_decode($request->getContent());
  if ($event->type == 'source.chargeable') {
    $source = $event->data->object;
    $charge = \Stripe\Charge::create(array(
      'amount' => $source->amount,
      'currency' => $source->currency,
      'source' => $source->id,
      'description' => "Example Charge ",
    ));
  }
  return new Response('', 200);
});

$app->run();
