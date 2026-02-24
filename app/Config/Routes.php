<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->get('bingo', 'BingoController::index');
$routes->post('bingo/room/create', 'BingoController::createRoom');
$routes->post('bingo/room/join', 'BingoController::joinRoom');
$routes->post('bingo/room/board', 'BingoController::updateBoard');
$routes->get('bingo/room/state', 'BingoController::getState');
$routes->post('bingo/room/call', 'BingoController::callNumber');
$routes->post('bingo/room/start', 'BingoController::startGame');
$routes->post('bingo/room/new', 'BingoController::newGame');
$routes->post('bingo/room/toggle-rule', 'BingoController::toggleRule');
$routes->post('bingo/room/kick', 'BingoController::kickPlayer');
$routes->post('bingo/room/leave', 'BingoController::leaveRoom');

$routes->get('esewa', 'EsewaController::index');
$routes->post('esewa/checkout', 'EsewaController::checkout');
$routes->match(['get', 'post'], 'esewa/success', 'EsewaController::success');
$routes->match(['get', 'post'], 'esewa/failure', 'EsewaController::failure');
$routes->post('webhooks/esewa', 'EsewaController::webhook');
